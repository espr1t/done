<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/problem.php');
require_once(__DIR__ . '/../entities/submit.php');
require_once(__DIR__ . '/../entities/user.php');

global $user;

// User doesn't have access level needed for uploading solutions
if ($user->access < $GLOBALS['ACCESS_EDIT_PROBLEM']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да качвате решения.'
    ));
}

$problemId = $_POST['problemId'];
$problem = Problem::get($problemId);
if ($problem == null) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Няма задача с ID "' . $problemId . '"!'
    ));
}

$solutionName = $_POST['solutionName'];

// Determine language by the file extention
$language = lastElement(explode('.', $solutionName));
if (!array_key_exists($language, $GLOBALS['SUPPORTED_LANGUAGES'])) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Неразпознат програмен език на файл с име: "' . $solutionName . '"!'
    ));
}
$language = $GLOBALS['SUPPORTED_LANGUAGES'][$language];

// Generate the path where to store the solution
$solutionPath = sprintf("%s/%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                                       $GLOBALS['PROBLEM_SOLUTIONS_FOLDER'], $solutionName);
// TODO: Maybe replace CRLF only if Linux is detected?
$solutionSource = base64_decode($_POST['solutionSource']);
$solutionSource = preg_replace('~\R~u', "\n", $solutionSource);
file_put_contents($solutionPath, $solutionSource);

// Solutions should be uploaded with System's user
$user = User::get(0);
$submit = Submit::create($user, $problemId, $language, $solutionSource, false);

// Add the submit to the database and queue it for grading.
if (!$submit->add()) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Възникна проблем при изпращането на решението.'
    ));
}

// Record that this is an author's solution in the correct table
$brain = new Brain();
$brain->addSolution($problemId, $solutionName, $submit->id, $solutionSource, $language);

// Return the relative path to the solution so it is displayed properly on the frontend
$solutionPath = explode($_SERVER['DOCUMENT_ROOT'], $solutionPath)[1];

// Otherwise print success and return the submit ID
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Решението беше предадено успешно.',
    'id' => $submit->id,
    'path' => $solutionPath
));

?>