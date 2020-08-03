"""
Tests whether the evaluator is behaving as expected.
"""
import os
import shutil
import vcr
import logging
import json
from unittest import TestCase, mock
from time import perf_counter

import config
from common import TestStatus
from compiler import Compiler
from evaluator import Evaluator
import initializer


class TestEvaluator(TestCase):
    PATH_FIXTURES = os.path.abspath("tests/fixtures/evaluator")

    # Do it this way instead of using a class decorator since otherwise the patching
    # is not active in the setUp() / tearDown() methods -- and we need it there as well
    patch_tests = mock.patch("config.PATH_TESTS", os.path.abspath("tests/test_data/"))
    patch_sandbox = mock.patch("config.PATH_SANDBOX", os.path.abspath("tests/test_sandbox/"))

    @classmethod
    def setUpClass(cls):
        initializer.init()
        logging.getLogger("vcr").setLevel(logging.FATAL)

        cls.patch_sandbox.start()
        if not os.path.exists(config.PATH_SANDBOX):
            os.makedirs(config.PATH_SANDBOX)
        cls.patch_tests.start()
        if not os.path.exists(config.PATH_TESTS):
            os.makedirs(config.PATH_TESTS)

        tasks = [
            ("tests_runner_three.json", "ThreeSum"),
            ("tests_runner_sheep.json", "Sheep"),
            ("tests_runner_ruler.json", "Ruler"),
        ]

        # Create an Evaluator objects for each task and copy its tests in the test_data folder
        for task in tasks:
            evaluator = cls.get_evaluator(os.path.join(cls.PATH_FIXTURES, task[0]), config.LANGUAGE_CPP)
            for test in evaluator.tests:
                shutil.copy(os.path.join(cls.PATH_FIXTURES, "{}/Tests".format(task[1]), test.inpFile), test.inpPath)
                shutil.copy(os.path.join(cls.PATH_FIXTURES, "{}/Tests".format(task[1]), test.solFile), test.solPath)

    @classmethod
    def tearDownClass(cls):
        shutil.rmtree(config.PATH_SANDBOX)
        cls.patch_sandbox.stop()
        shutil.rmtree(config.PATH_TESTS)
        cls.patch_tests.stop()

    @classmethod
    def get_evaluator(cls, data_file, language) -> Evaluator:
        with open(os.path.join(cls.PATH_FIXTURES, data_file)) as file:
            data = json.loads(file.read())
            data["language"] = language
            return Evaluator(data)

    def test_create_sandbox_dir(self):
        evaluator = self.get_evaluator("problem_submit_ok.json", config.LANGUAGE_CPP)
        self.assertFalse(os.path.exists(evaluator.path_sandbox))
        evaluator.create_sandbox_dir()
        self.assertTrue(os.path.exists(evaluator.path_sandbox))

    @vcr.use_cassette("tests/fixtures/cassettes/download_tests.yaml")
    def test_download_tests(self):
        evaluator = self.get_evaluator("problem_submit_ok.json", config.LANGUAGE_CPP)

        # Assert none of the files is already present
        for test in evaluator.tests:
            self.assertFalse(os.path.exists(test.inpPath))
            self.assertFalse(os.path.exists(test.solPath))

        # Do the actual download
        evaluator.download_tests()

        # Assert all of the files are now present
        for test in evaluator.tests:
            self.assertTrue(os.path.exists(test.inpPath))
            self.assertTrue(os.path.exists(test.solPath))

    def test_write_source(self):
        evaluator = self.get_evaluator("problem_submit_ok.json", config.LANGUAGE_CPP)
        self.assertFalse(os.path.isfile(evaluator.path_source))
        evaluator.create_sandbox_dir()
        evaluator.write_source(evaluator.source, evaluator.path_source)
        self.assertTrue(os.path.isfile(evaluator.path_source))
        with open(evaluator.path_source, "rt") as file:
            self.assertEqual(evaluator.source, file.read())

    def test_cleanup(self):
        # Create a new instance and write the source
        evaluator = self.get_evaluator("problem_submit_ok.json", config.LANGUAGE_CPP)
        evaluator.create_sandbox_dir()
        evaluator.write_source(evaluator.source, evaluator.path_source)

        # Assert the submit directory and source file are created
        self.assertTrue(os.path.exists(evaluator.path_sandbox))
        self.assertTrue(os.path.isfile(evaluator.path_source))

        # Do the cleanup
        evaluator.cleanup()

        # Assert the submit directory and source file are removed
        self.assertFalse(os.path.isfile(evaluator.path_source))
        self.assertFalse(os.path.exists(evaluator.path_sandbox))

    @mock.patch("updater.Updater.add_info")
    def full_run_helper(self, add_info, evaluator, language, source, time_lb, memory_lb, expected_counts={}):
        evaluator.create_sandbox_dir()

        compilation_status = Compiler.compile(
            language=language,
            path_source=source,
            path_executable=evaluator.path_executable
        )
        self.assertEqual(compilation_status, "")

        updater_results = []
        add_info.side_effect = lambda result: updater_results.append(result)

        self.assertTrue(evaluator.run_solution())
        self.assertEqual(add_info.call_count, len(evaluator.tests) * 2)

        actual_non_ok = {}
        max_time, max_memory = -1e100, -1e100
        for res in updater_results:
            if res["status"] != TestStatus.TESTING.name:
                # print(res)
                if res["status"] != TestStatus.ACCEPTED.name:
                    # This is only because we only test wrong answers from the task with checkers
                    # In real tasks this is usually empty
                    if res["status"] == TestStatus.WRONG_ANSWER.name:
                        self.assertNotEqual(res["info"], "")

                    if res["status"] not in actual_non_ok:
                        actual_non_ok[res["status"]] = 1
                    else:
                        actual_non_ok[res["status"]] += 1

                max_time = max(max_time, res["exec_time"])
                max_memory = max(max_memory, res["exec_memory"])

        time_upper_bound = evaluator.time_limit + max(0.2, evaluator.time_limit * 0.2)
        self.assertGreaterEqual(max_time, time_lb)
        self.assertLessEqual(max_time, time_upper_bound)
        self.assertGreater(max_memory, memory_lb)  # Returned memory is converted back to megabytes
        self.assertLessEqual(max_memory, evaluator.memory_limit)

        for key in actual_non_ok.keys():
            if key not in expected_counts:
                self.fail("Got status {} which was not expected.".format(key))
            if actual_non_ok[key] != expected_counts[key]:
                self.fail("Expected {} results with status {} but got {}.".format(expected_counts[key], key, actual_non_ok[key]))
        for key in expected_counts.keys():
            if key not in actual_non_ok:
                self.fail("Expected status {} but didn't receive it.".format(key))

    ########################################################
    #                      Three Sum                       #
    #                   (sample problem)                   #
    ########################################################
    def test_full_run_three_sum_cpp(self):
        evaluator = self.get_evaluator("tests_runner_three.json", config.LANGUAGE_CPP)
        self.full_run_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "ThreeSum/Solutions/ThreeSum.cpp"),
            time_lb=0.0,
            memory_lb=1.0
        )

    def test_full_run_three_sum_java(self):
        evaluator = self.get_evaluator("tests_runner_three.json", config.LANGUAGE_JAVA)
        self.full_run_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_JAVA,
            source=os.path.join(self.PATH_FIXTURES, "ThreeSum/Solutions/ThreeSum.java"),
            time_lb=0.0,
            memory_lb=0.0
        )

    def test_full_run_three_sum_python(self):
        evaluator = self.get_evaluator("tests_runner_three.json", config.LANGUAGE_PYTHON)
        self.full_run_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_PYTHON,
            source=os.path.join(self.PATH_FIXTURES, "ThreeSum/Solutions/ThreeSum.py"),
            time_lb=0.1,
            memory_lb=1.0
        )

    ########################################################
    #                        Sheep                         #
    #             (real problem, many tests)               #
    ########################################################
    def test_full_run_sheep_cpp(self):
        evaluator = self.get_evaluator("tests_runner_sheep.json", config.LANGUAGE_CPP)
        self.full_run_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "Sheep/Solutions/Sheep.cpp"),
            time_lb=0.1,
            memory_lb=1.0
        )

    def test_full_run_sheep_java(self):
        evaluator = self.get_evaluator("tests_runner_sheep.json", config.LANGUAGE_JAVA)
        self.full_run_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_JAVA,
            source=os.path.join(self.PATH_FIXTURES, "Sheep/Solutions/Sheep.java"),
            time_lb=0.2,
            memory_lb=1.0
        )

    ########################################################
    #                        Ruler                         #
    #                    (has checker)                     #
    ########################################################
    def test_full_run_ruler_cpp(self):
        evaluator = self.get_evaluator("tests_runner_ruler.json", config.LANGUAGE_CPP)

        # Configure fake paths to source and executable of the checker and compile it
        evaluator.path_checker_source = os.path.join(self.PATH_FIXTURES, "Ruler/Checker/RulerChecker.cpp")
        evaluator.path_checker_executable = os.path.join(config.PATH_TESTS, "RulerChecker.o")
        compilation_status = Compiler.compile(
            language=config.LANGUAGE_CPP,
            path_source=evaluator.path_checker_source,
            path_executable=evaluator.path_checker_executable
        )
        self.assertEqual(compilation_status, "")

        # AC
        self.full_run_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "Ruler/Solutions/Ruler.cpp"),
            time_lb=0.0,
            memory_lb=0.0
        )

        # TL
        self.full_run_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "Ruler/Solutions/RulerTL.cpp"),
            time_lb=0.2,
            memory_lb=1.0,
            expected_counts={"TIME_LIMIT": 7}
        )

        # WA
        self.full_run_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "Ruler/Solutions/RulerWA.cpp"),
            time_lb=0.0,
            memory_lb=0.0,
            expected_counts={"WRONG_ANSWER": 4}
        )
