import os
import unittest
from validator import Validator
from run_info import RunInfo

PATH_FLOAT_COMPARISON = "tests/fixtures/FloatComparison"
PATH_TEXT_COMPARISON = "tests/fixtures/TextComparison"


class TestValidator(unittest.TestCase):
    def setUp(self):
        self.run_info = RunInfo(
            time_limit=0.5,
            memory_limit=65536,
            solution_path="blabla.py",
            compare_floats=False
        )

    def test_absolute_or_relative_comparison(self):
        self.run_info.compare_floats = True

        # Absolute difference is less than 10-9
        message, score, info = Validator.validate_output(
            submit_id=42,
            inp_file="",
            out_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatAbsoluteOK.out"),
            sol_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatAbsolute.sol"),
            run_info=self.run_info,
            result=None
        )
        self.assertEqual("", message)
        self.assertEqual(1.0, score)

        # Absolute difference is greater than 10-9
        message, score, info = Validator.validate_output(
            submit_id=42,
            inp_file="",
            out_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatAbsoluteWA.out"),
            sol_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatAbsolute.sol"),
            run_info=self.run_info,
            result=None
        )
        self.assertNotEqual("", message)
        self.assertEqual(0.0, score)

        # Relative difference is less than 10-9
        message, score, info = Validator.validate_output(
            submit_id=42,
            inp_file="",
            out_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatRelativeOK.out"),
            sol_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatRelative.sol"),
            run_info=self.run_info,
            result=None
        )
        self.assertEqual("", message)
        self.assertEqual(1.0, score)

        # Relative difference is greater than 10-9
        message, score, info = Validator.validate_output(
            submit_id=42,
            inp_file="",
            out_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatRelativeWA.out"),
            sol_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatRelative.sol"),
            run_info=self.run_info,
            result=None
        )
        self.assertNotEqual("", message)
        self.assertEqual(0.0, score)

    def test_floats_and_leading_zeroes_okay(self):
        # Answers with missing leading zeroes are treated okay if floating point comparison is enabled
        self.run_info.compare_floats = True
        message, score, info = Validator.validate_output(
            submit_id=42,
            inp_file="",
            out_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatLeadingZeroes.out"),
            sol_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatLeadingZeroes.sol"),
            run_info=self.run_info,
            result=None
        )
        self.assertEqual("", message)
        self.assertEqual(1.0, score)

    def test_floats_and_leading_zeroes_fail(self):
        # Answers with missing leading zeroes are not okay if abs/rel comparison is disabled
        self.run_info.compare_floats = False
        message, score, info = Validator.validate_output(
            submit_id=42,
            inp_file="",
            out_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatLeadingZeroes.out"),
            sol_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatLeadingZeroes.sol"),
            run_info=self.run_info,
            result=None
        )
        self.assertNotEqual("", message)
        self.assertEqual(0.0, score)

    def test_floats_and_long_longs_okay(self):
        # Differences in the last digits of large numbers (> 2^53) are okay if abs/rel comparison is enabled
        self.run_info.compare_floats = True
        message, score, info = Validator.validate_output(
            submit_id=42,
            inp_file="",
            out_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatLongLongs.out"),
            sol_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatLongLongs.sol"),
            run_info=self.run_info,
            result=None
        )
        self.assertEqual("", message)
        self.assertEqual(1.0, score)

    def test_floats_and_long_longs_fail(self):
        # Differences in the last digits of large numbers (> 2^53) are not okay if abs/rel comparison is disabled
        self.run_info.compare_floats = False
        message, score, info = Validator.validate_output(
            submit_id=42,
            inp_file="",
            out_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatLongLongs.out"),
            sol_file=os.path.join(PATH_FLOAT_COMPARISON, "FloatLongLongs.sol"),
            run_info=self.run_info,
            result=None
        )
        self.assertNotEqual("", message)
        self.assertEqual(0.0, score)

    def test_presentation_difference_comparison(self):
        # Trailing spaces at the end of the lines or after the last line are okay
        self.run_info.compare_floats = True
        message, score, info = Validator.validate_output(
            submit_id=42,
            inp_file="",
            out_file=os.path.join(PATH_TEXT_COMPARISON, "TextComparisonPE.out"),
            sol_file=os.path.join(PATH_TEXT_COMPARISON, "TextComparisonOK.sol"),
            run_info=self.run_info,
            result=None
        )
        self.assertEqual("", message)
        self.assertEqual(1.0, score)
