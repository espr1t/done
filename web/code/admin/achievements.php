<?php
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../page.php');
require_once(__DIR__ . '/../games.php');
require_once(__DIR__ . '/../ranking.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/user.php');
require_once(__DIR__ . '/../entities/problem.php');

class AdminAchievementsPage extends Page {
    private $PRIME_NUMBERS = array();
    private $FIBONACCI_NUMBERS = array();
    private $PERFECT_NUMBERS = array();
    private $POWER_OF_TWO_NUMBERS = array();
    private $POWER_OF_TEN_NUMBERS = array();
    private $PI_PREFIX_NUMBERS = array();
    private $E_PREFIX_NUMBERS = array();

    public function getTitle() {
        return 'O(N)::Admin';
    }

    public function getExtraScripts() {
        return array('/scripts/admin.js');
    }

    public function initSpecialNumbers() {
        $limit = 100000;
        $isPrime = array_fill(0, $limit, true);
        $isPrime[0] = $isPrime[1] = false;
        for ($num = 2; $num * $num < $limit; $num += 1) {
            if ($isPrime[$num]) {
                for ($comp = $num * $num; $comp < $limit; $comp += $num)
                    $isPrime[$comp] = false;
            }
        }

        for ($num = 0; $num < $limit; $num += 1)
            if ($isPrime[$num]) array_push($this->PRIME_NUMBERS, $num);

        $this->FIBONACCI_NUMBERS = array(0, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144, 233, 377,
            610, 987, 1597, 2584, 4181, 6765, 10946, 17711, 28657, 46368, 75025, 121393,
            196418, 317811, 514229, 832040, 1346269, 2178309, 3524578, 5702887, 9227465,
            14930352, 24157817, 39088169, 63245986, 102334155);

        $this->PERFECT_NUMBERS = array(6, 28, 496, 8128, 33550336);
        $this->POWER_OF_TWO_NUMBERS = array(1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024, 2048,
            4096, 8192, 16384, 32768, 65536, 131072, 262144, 524288, 1048576, 2097152, 4194304,
            8388608, 16777216, 33554432, 67108864, 134217728, 268435456, 536870912);
        $this->POWER_OF_TEN_NUMBERS = array(1, 10, 100, 1000, 10000, 100000, 1000000, 10000000, 100000000);
        $this->PI_PREFIX_NUMBERS = array(3, 31, 314, 3141, 31415, 314159, 3141592, 31415926, 314159265);
        $this->E_PREFIX_NUMBERS = array(2, 27, 271, 2718, 27182, 271828, 2718281, 27182818, 271828182);
    }

    // Has submitted X times
    public function achievementSubmitted($brain, $achieved, $user, $submits, $key, $limit) {
        if (!in_array($key, $achieved)) {
            if (count($submits) >= $limit) {
                $brain->addAchievement($user->id, $key, $submits[$limit - 1]['submitted']);
            }
        }
    }

    // Has solved X problems
    public function achievementSolved($brain, $achieved, $user, $solved, $key, $limit) {
        if (!in_array($key, $achieved)) {
            if (count($solved) >= $limit) {
                $brain->addAchievement($user->id, $key, $solved[$limit - 1]['submitted']);
            }
        }
    }

    // Has solved X trivial/easy/medium/hard/brutal problem(s)
    public function achievementDifficulty($brain, $achieved, $user, $solved, $key, $difficulty, $limit) {
        if (!in_array($key, $achieved)) {
            if (count($solved[$difficulty]) >= $limit) {
                $brain->addAchievement($user->id, $key, $solved[$difficulty][$limit - 1]['submitted']);
            }
        }
    }

    // Has solved problems of all difficulties
    public function achievementAllDiff($brain, $achieved, $user, $solved) {
        if (!in_array('ALLDIF', $achieved)) {
            $date = '';
            foreach ($solved as $diff => $submits) {
                if (count($submits) == 0)
                    return;
                $date = max(array($date, $submits[0]['submitted']));
            }
            $brain->addAchievement($user->id, 'ALLDIF', $date);
        }
    }

    // Has solved X problem(s) by given tags
    public function achievementTags($brain, $achieved, $user, $solved, $key, $tag, $limit) {
        if (!in_array($key, $achieved)) {
            if (count($solved[$tag]) >= $limit) {
                $brain->addAchievement($user->id, $key, $solved[$tag][$limit - 1]['submitted']);
            }
        }
    }

    // Has solved problems of all tags
    public function achievementAllTags($brain, $achieved, $user, $solved) {
        if (!in_array('ALLTAG', $achieved)) {
            $date = '';
            foreach ($solved as $tag => $submits) {
                if (count($submits) == 0)
                    return;
                $date = max(array($date, $submits[0]['submitted']));
            }
            $brain->addAchievement($user->id, 'ALLTAG', $date);
        }
    }

    // Solved X problems in Y minutes
    public function achievementSpeed($brain, $achieved, $user, $solved, $key, $count, $limit) {
        if (!in_array($key, $achieved)) {
            $date = '';
            $solvedCount = count($solved);
            for ($i = 0; $i + $count <= $solvedCount; $i += 1) {
                $ts1 = strtotime($solved[$i]['submitted']);
                $ts2 = strtotime($solved[$i + $count - 1]['submitted']);
                if ($ts2 - $ts1 <= $limit) {
                    $date = $solved[$i + $count - 1]['submitted'];
                    break;
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, $key, $date);
            }
        }
    }

    // Solved various training sections
    public function achievementTraining($brain, $achieved, $user, $solved, $training) {
        $solvedDate = array();
        foreach ($solved as $submit) {
            $solvedDate[$submit['problemId']] = $submit['submitted'];
        }
        $completed = 0;
        $latestDate = '';
        foreach ($training as $section) {
            $key = 'T_' . $section['key'];
            if (!in_array($key, $achieved)) {
                $date = '';
                $problems = explode(',', $section['problems']);
                foreach ($problems as $problemId) {
                    if (!array_key_exists($problemId, $solvedDate)) {
                        $date = '';
                        break;
                    }
                    if ($date == '' || $date < $solvedDate[$problemId])
                        $date = $solvedDate[$problemId];
                }
                if ($date != '') {
                    $brain->addAchievement($user->id, $key, $date);
                    $completed += 1;
                    if ($latestDate == '' || $latestDate < $date)
                        $latestDate = $date;
                }
            } else {
                $completed += 1;
            }
        }
        if (!in_array('GRADU8', $achieved)) {
            if ($completed == count($training)) {
                $brain->addAchievement($user->id, 'GRADU8', $latestDate);
            }
        }
    }

    // Has solved all problems
    public function achievementAllTasks($brain, $achieved, $user, $solved, $problems) {
        if (!in_array('ALLTSK', $achieved)) {
            if (count($solved) == count($problems)) {
                $brain->addAchievement($user->id, 'ALLTSK', $solved[count($solved) - 1]['submitted']);
            }
        }
    }

    // Has played X games
    public function achievementPlayedGame($brain, $achieved, $user, $standings, $submits, $key, $limit) {
        if (!in_array($key, $achieved)) {
            $played = 0;
            $submitId = -1;
            foreach ($standings as $game => $ranking) {
                foreach ($ranking as $player) {
                    if ($player['user'] == $user->id) {
                        $played += 1;
                        $submitId = max(array($submitId, $player['submit']));
                        if ($played >= $limit)
                            break;
                    }
                }
            }
            if ($played >= $limit) {
                foreach ($submits as $submit) {
                    if ($submit['id'] == $submitId) {
                        $brain->addAchievement($user->id, $key, $submit['submitted']);
                        return;
                    }
                }
            }
        }
    }

    // Has won a game
    public function achievementWonGame($brain, $achieved, $user, $standings, $submits) {
        if (!in_array('WINNER', $achieved)) {
            foreach ($standings as $game => $ranking) {
                if ($ranking[0]['user'] == $user->id) {
                    foreach ($submits as $submit) {
                        if ($submit['id'] == $ranking[0]['submit']) {
                            $brain->addAchievement($user->id, 'WINNER', $submit['submitted']);
                            return;
                        }
                    }
                }
            }
        }
    }

    // Has registered
    public function achievementRegistered($brain, $achieved, $user) {
        if (!in_array('RGSTRD', $achieved)) {
            $brain->addAchievement($user->id, 'RGSTRD', $user->registered);
        }
    }

    // Has reported a problem
    public function achievementReported($brain, $achieved, $user, $reports) {
        if (!in_array('REPORT', $achieved)) {
            if (count($reports) >= 1) {
                $brain->addAchievement($user->id, 'REPORT', $reports[0]['date']);
            }
        }
    }

    // Has over 1000 actions on the site
    public function achievementActive($brain, $achieved, $user) {
        if (!in_array('ACTIVE', $achieved)) {
            if ($user->actions >= 1000) {
                $brain->addAchievement($user->id, 'ACTIVE', date('Y-m-d H:i:s'));
            }
        }
    }

    // Has ran over 10000 tests
    public function achievementTested($brain, $achieved, $user, $submits) {
        if (!in_array('TESTED', $achieved)) {
            $total = $idx = 0;
            $submitCount = count($submits);
            while ($idx < $submitCount) {
                $total += count(explode(',', $submits[$idx]['results']));
                if ($total >= 10000)
                    break;
                $idx += 1;
            }
            if ($total >= 10000) {
                $brain->addAchievement($user->id, 'TESTED', $submits[$idx]['submitted']);
            }
        }
    }

    // Ranked in top X
    public function achievementRanked($brain, $achieved, $user, $ranking, $key, $limit) {
        if (!in_array($key, $achieved)) {
            $pos = 0;
            $rankingCount = count($ranking);
            while ($pos < $rankingCount && $ranking[$pos]['id'] != $user->id)
                $pos++;
            if ($pos < $rankingCount && $pos < $limit) {
                $brain->addAchievement($user->id, $key, date('Y-m-d H:i:s'));
            }
        }
    }

    // Was the first to solve a problem
    public function achievementVirgin($brain, $achieved, $user, $accepted) {
        if (!in_array('VIRGIN', $achieved)) {
            $date = '';
            $solved = array();
            foreach ($accepted as $submit) {
                // Skip system and admin submissions
                if ($submit['userId'] < 2)
                    continue;

                if (!array_key_exists($submit['problemId'], $solved)) {
                    $solved[$submit['problemId']] = true;
                    if ($submit['userId'] == $user->id) {
                        $date = $submit['submitted'];
                        break;
                    }
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, 'VIRGIN', $date);
            }
        }
    }

    // Submitted a problem in unusual time (late in the night or early in the morning)
    public function achievementUnusualTime($brain, $achieved, $user, $submits, $key, $lower, $upper) {
        if (!in_array($key, $achieved)) {
            $date = '';
            foreach ($submits as $submit) {
                $hour = date('H', strtotime($submit['submitted']));
                if ($hour >= $lower && $hour < $upper) {
                    $date = $submit['submitted'];
                    break;
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, $key, $date);
            }
        }
    }

    // Solved problems in 3 different languages
    public function achievementRainbow($brain, $achieved, $user, $submits) {
        if (!in_array('3LANGS', $achieved)) {
            $date = '';
            $langs = array();
            foreach ($submits as $submit) {
                if ($submit['status'] == 'AC') {
                    if (!array_key_exists($submit['language'], $langs)) {
                        $langs[$submit['language']] = true;
                        if (count($langs) >= 3) {
                            $date = $submit['submitted'];
                            break;
                        }
                    }
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, '3LANGS', $date);
            }
        }
    }

    // Registered more than a year ago
    public function achievementOldtimer($brain, $achieved, $user) {
        if (!in_array('OLDREG', $achieved)) {
            $anniversary = strtotime($user->registered) + 365 * 24 * 60 * 60;
            if (time() >= $anniversary) {
                $date = date('Y-m-d', $anniversary);
                $brain->addAchievement($user->id, 'OLDREG', $date);
            }
        }
    }

    // Submit on Christmas, New Year, or user's birthday
    public function achievementDate($brain, $achieved, $user, $submits, $key, $target) {
        if (!in_array($key, $achieved)) {
            $date = '';
            foreach ($submits as $submit) {
                if (date('m-d', strtotime($submit['submitted'])) == $target) {
                    $date = $submit['submitted'];
                    break;
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, $key, $date);
            }
        }
    }

    // Got three different types of errors on a problem
    public function achievementSoWrong($brain, $achieved, $user, $submits) {
        if (!in_array('WARETL', $achieved)) {
            $date = '';
            $errors = array();
            foreach ($submits as $submit) {
                if (!array_key_exists($submit['problemId'], $errors))
                    $errors[$submit['problemId']] = 0;
                if ($submit['status'] == 'WA')
                    $errors[$submit['problemId']] |= (1 << 0);
                if ($submit['status'] == 'RE')
                    $errors[$submit['problemId']] |= (1 << 1);
                if ($submit['status'] == 'TL')
                    $errors[$submit['problemId']] |= (1 << 2);
                if ($submit['status'] == 'CE')
                    $errors[$submit['problemId']] |= (1 << 3);
                if ($submit['status'] == 'ML')
                    $errors[$submit['problemId']] |= (1 << 4);
                if ($submit['status'] == 'IE')
                    $errors[$submit['problemId']] |= (1 << 5);

                if (popcount($errors[$submit['problemId']]) >= 3) {
                    $date = $submit['submitted'];
                    break;
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, 'WARETL', $date);
            }
        }
    }

    // Got several different types of errors in a single submission
    public function achievementDisaster($brain, $achieved, $user, $submits) {
        foreach ($submits as $submit) {
            $results = explode(',', $submit['results']);
            $errorMask = 0;
            foreach($results as $result) {
                if (is_numeric($result))
                    $errorMask |= (1 << 0);
                else if ($result == 'WA')
                    $errorMask |= (1 << 1);
                else if ($result == 'RE')
                    $errorMask |= (1 << 2);
                else if ($result == 'TL')
                    $errorMask |= (1 << 3);
                else if ($result == 'CE')
                    $errorMask |= (1 << 4);
                else if ($result == 'ML')
                    $errorMask |= (1 << 5);
                else if ($result == 'IE')
                    $errorMask |= (1 << 6);
            }
            if (popcount($errorMask) >= 3 && !in_array('HATTRK', $achieved))
                $brain->addAchievement($user->id, 'HATTRK', $submit['submitted']);
            if (popcount($errorMask) >= 4 && !in_array('QUATRO', $achieved))
                $brain->addAchievement($user->id, 'QUATRO', $submit['submitted']);
            if (popcount($errorMask) >= 5 && !in_array('PNTGRM', $achieved))
                $brain->addAchievement($user->id, 'PNTGRM', $submit['submitted']);
        }
    }

    // 10 unsuccessful submits on a problem
    public function achievementUnsuccess($brain, $achieved, $user, $submits, $key, $target) {
        if (!in_array($key, $achieved)) {
            $date = '';
            $unsuccessful = array();
            foreach ($submits as $submit) {
                if (!array_key_exists($submit['problemId'], $unsuccessful))
                    $unsuccessful[$submit['problemId']] = 0;
                if ($submit['status'] != 'AC')
                    $unsuccessful[$submit['problemId']]++;
                if ($unsuccessful[$submit['problemId']] >= $target) {
                    $date = $submit['submitted'];
                    break;
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, $key, $date);
            }
        }
    }

    // Solved 30 tasks from first try
    public function achievementAccurate($brain, $achieved, $user, $submits, $key, $target) {
        if (!in_array($key, $achieved)) {
            $date = '';
            $first = 0;
            $submitted = array();
            foreach ($submits as $submit) {
                if (!array_key_exists($submit['problemId'], $submitted)) {
                    $submitted[$submit['problemId']] = true;
                    if ($submit['status'] == 'AC') {
                        $first += 1;
                        if ($first >= $target) {
                            $date = $submit['submitted'];
                            break;
                        }
                    }
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, $key, $date);
            }
        }
    }

    // Solved 5 tricky problems on the first try
    public function achievementPedantic($brain, $achieved, $user, $submits, $problems) {
        if (!in_array('TRICKY', $achieved)) {
            $trickyNames = array(
                'Sheep', 'Ssssss', 'Bribes', 'Sequence Members', 'Wordrow', 'Next', 'Shades', 'Seats',
                'Bazinga!', 'Crosses', 'Collatz', 'Passwords', 'Digit Holes', 'Directory Listing'
            );
            $trickyIds = array();
            foreach ($problems as $problem) {
                if (in_array($problem['name'], $trickyNames))
                    $trickyIds[$problem['id']] = true;
            }
            $date = '';
            $first = 0;
            $submitted = array();
            foreach ($submits as $submit) {
                if (!array_key_exists($submit['problemId'], $submitted)) {
                    $submitted[$submit['problemId']] = true;
                    if ($submit['status'] == 'AC') {
                        if (array_key_exists($submit['problemId'], $trickyIds)) {
                            $first += 1;
                            if ($first >= 5) {
                                $date = $submit['submitted'];
                                break;
                            }
                        }
                    }
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, 'TRICKY', $date);
            }
        }
    }

    // Filled all profile information
    public function achievementProfile($brain, $achieved, $user) {
        if (!in_array('PROFIL', $achieved)) {
            if ($user->email != '' && $user->town != '' && $user->country != '' && $user->gender != '' && $user->birthdate != '0000-00-00') {
                // TODO: Set the achievemnt date to the one this actually happened once info edit is available
                $brain->addAchievement($user->id, 'PROFIL', $user->registered);
            }
        }
    }

    // Solved again an already accepted task with a new solution
    public function achievementReimplemented($brain, $achieved, $user, $submits, $sources) {
        if (!in_array('ACCAGN', $achieved)) {
            $date = '';
            $solved = array();
            $submitCount = count($submits);
            for ($i = 0; $i < $submitCount; $i += 1) {
                if ($submits[$i]['status'] == 'AC') {
                    if (array_key_exists($submits[$i]['problemId'], $solved)) {
                        if ($solved[$submits[$i]['problemId']] != $sources[$i]) {
                            $date = $submits[$i]['submitted'];
                            break;
                        }
                    }
                    $solved[$submits[$i]['problemId']] = $sources[$i];
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, 'ACCAGN', $date);
            }
        }
    }

    private function isOffByOne($str1, $str2) {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        if (abs($len1 - $len2) > 1 || $str1 == $str2)
            return false;

        $idx1 = $idx2 = 0;
        while ($idx1 < $len1 && $idx2 < $len2 && $str1[$idx1] == $str2[$idx2]) {
            $idx1 += 1;
            $idx2 += 1;
        }
        if ($idx1 >= $len1 || $idx2 >= $len2)
            return true;
        if ($len1 > $len2) {
            $idx2 += 1;
        } else if ($len2 > $len1) {
            $idx1 += 1;
        } else {
            $idx1 += 1;
            $idx2 += 1;
        }
        while ($idx1 < $len1 && $idx2 < $len2 && $str1[$idx1] == $str2[$idx2]) {
            $idx1 += 1;
            $idx2 += 1;
        }
        if ($idx1 < $len1 || $idx2 < $len2)
            return false;
        return true;
    }

    // Fixed an off-by-one error to get a problem accepted
    public function achievementOffByOne($brain, $achieved, $user, $submits, $sources) {
        if (!in_array('OFFBY1', $achieved)) {
            $date = '';
            $byProblem = array();
            $submitCount = count($submits);
            for ($i = 0; $i < $submitCount; $i += 1) {
                $submits[$i]['source'] = $sources[$i]['source'];
                if (!array_key_exists($submits[$i]['problemId'], $byProblem))
                    $byProblem[$submits[$i]['problemId']] = array();
                array_push($byProblem[$submits[$i]['problemId']], $submits[$i]);
            }
            foreach ($byProblem as $id => $submits) {
                $submitCount = count($submits);
                for ($i = 1; $i < $submitCount; $i += 1) {
                    if ($submits[$i]['status'] == 'AC' && $submits[$i - 1]['status'] != 'AC' && $submits[$i - 1]['status'] != 'CE') {
                        if ($this->isOffByOne($submits[$i - 1]['source'], $submits[$i]['source'])) {
                            if ($date == '' || $date > $submits[$i]['submitted']) {
                                $date = $submits[$i]['submitted'];
                            }
                        }
                    }
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, 'OFFBY1', $date);
            }
        }
    }

    // Sent solutions from 3 different IPs (hopefully locations, but not necessarily)
    public function achievementDifferentLocations($brain, $achieved, $user, $submits) {
        if (!in_array('3DIFIP', $achieved)) {
            $date = '';
            $ips = array();
            foreach ($submits as $submit) {
                if ($submit['ip'] != '' && !in_array($submit['ip'], $ips)) {
                    array_push($ips, $submit['ip']);
                    if (count($ips) >= 3) {
                        $date = $submit['submitted'];
                        break;
                    }
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, '3DIFIP', $date);
            }
        }
    }

    // Solved a specific problem
    public function achievementProblem($brain, $achieved, $user, $solved, $problems, $key, $name) {
        if (!in_array($key, $achieved)) {
            $date = '';
            foreach ($problems as $problem) {
                if ($problem['name'] == $name) {
                    foreach ($solved as $sol) {
                        if ($problem['id'] == $sol['problemId']) {
                            $date = $sol['submitted'];
                            break;
                        }
                    }
                    break;
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, $key, $date);
            }
        }
    }

    public function achievementSpecialIDSubmit($brain, $achieved, $user, $userAllSubmits, $key, $numbers) {
        if (!in_array($key, $achieved)) {
            $date = '';
            foreach ($userAllSubmits as $submit) {
                if (in_array($submit['id'], $numbers)) {
                    $date = $submit['submitted'];
                    break;
                }
            }
            if ($date != '') {
                $brain->addAchievement($user->id, $key, $date);
            }
        }
    }

    public function updateAll($user, $games, $standings, $problems, $submits, $accepted, $sources, $ranking,
            $problemTags, $problemTagsCnt, $problemDifficulties, $problemDifficultiesCnt, $userAllSubmits, $userProblemSubmits, $userProblemSources) {
        $brain = new Brain();

        // Already achieved
        $userAchievements = $brain->getAchievements($user->id);
        $achieved = array();
        foreach ($userAchievements as $achievement) {
            array_push($achieved, $achievement['achievement']);
        }

        // Sent reports
        $userReports = $brain->getReports($user->id);

        // Solved problems
        $userSolved = array();
        $userSolvedIds = array();
        foreach ($userProblemSubmits as $submit) {
            if ($submit['status'] == 'AC') {
                if (!array_key_exists($submit['problemId'], $userSolvedIds)) {
                    $userSolvedIds[$submit['problemId']] = true;
                    array_push($userSolved, $submit);
                }
            }
        }

        // Solved per problem difficulty and tags
        $userSolvedPerTag = array();
        foreach ($problemTagsCnt as $tag => $cnt)
            $userSolvedPerTag[$tag] = array();

        $userSolvedPerDiff = array();
        foreach ($problemDifficultiesCnt as $difficulty => $cnt)
            $userSolvedPerDiff[$difficulty] = array();

        foreach ($userProblemSubmits as $submit) {
            if ($submit['status'] == 'AC') {
                // Tags
                $tags = $problemTags[$submit['problemId']];
                foreach ($tags as $tag) {
                    $alreadyIn = false;
                    foreach ($userSolvedPerTag[$tag] as $solved)
                        $alreadyIn = $alreadyIn || $solved['problemId'] == $submit['problemId'];
                    if (!$alreadyIn)
                        array_push($userSolvedPerTag[$tag], $submit);
                }

                // Difficulties
                $difficulty = $problemDifficulties[$submit['problemId']];
                $alreadyIn = false;
                foreach ($userSolvedPerDiff[$difficulty] as $solved)
                    $alreadyIn = $alreadyIn || $solved['problemId'] == $submit['problemId'];
                if (!$alreadyIn)
                    array_push($userSolvedPerDiff[$difficulty], $submit);
            }
        }

        // Training sections and problems
        $training = $brain->getTrainingTopics();

        // Number of submits and solutions achievements
        $this->achievementSubmitted($brain, $achieved, $user, $userProblemSubmits, 'SUB1E0', 1);
        $this->achievementSubmitted($brain, $achieved, $user, $userProblemSubmits, 'SUB1E1', 10);
        $this->achievementSubmitted($brain, $achieved, $user, $userProblemSubmits, 'SUB1E2', 100);
        $this->achievementSubmitted($brain, $achieved, $user, $userProblemSubmits, 'SUB1E3', 1000);
        $this->achievementSolved($brain, $achieved, $user, $userSolved, 'SOL1E0', 1);
        $this->achievementSolved($brain, $achieved, $user, $userSolved, 'SOL1E1', 10);
        $this->achievementSolved($brain, $achieved, $user, $userSolved, 'SOL1E2', 100);

        // Problems difficulty achievements
        $this->achievementDifficulty($brain, $achieved, $user, $userSolvedPerDiff, '1TRIVL', 'trivial', 1);
        $this->achievementDifficulty($brain, $achieved, $user, $userSolvedPerDiff, '1EASYY', 'easy', 1);
        $this->achievementDifficulty($brain, $achieved, $user, $userSolvedPerDiff, '1MEDIU', 'medium', 1);
        $this->achievementDifficulty($brain, $achieved, $user, $userSolvedPerDiff, '1HARDD', 'hard', 1);
        $this->achievementDifficulty($brain, $achieved, $user, $userSolvedPerDiff, '1BRUTL', 'brutal', 1);

        $this->achievementDifficulty($brain, $achieved, $user, $userSolvedPerDiff, 'ALLTRV', 'trivial', $problemDifficultiesCnt['trivial']);
        $this->achievementDifficulty($brain, $achieved, $user, $userSolvedPerDiff, 'ALLESY', 'easy', $problemDifficultiesCnt['easy']);
        $this->achievementDifficulty($brain, $achieved, $user, $userSolvedPerDiff, 'ALLMED', 'medium', $problemDifficultiesCnt['medium']);
        $this->achievementDifficulty($brain, $achieved, $user, $userSolvedPerDiff, 'ALLHRD', 'hard', $problemDifficultiesCnt['hard']);
        $this->achievementDifficulty($brain, $achieved, $user, $userSolvedPerDiff, 'ALLBRL', 'brutal', $problemDifficultiesCnt['brutal']);
        $this->achievementAllDiff($brain, $achieved, $user, $userSolvedPerDiff);

        // Problems tags achievements
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLIMP', 'implement', $problemTagsCnt['implement']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLSRC', 'search', $problemTagsCnt['search']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLDPR', 'dp', $problemTagsCnt['dp']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLGRF', 'graph', $problemTagsCnt['graph']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLMAT', 'math', $problemTagsCnt['math']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLGEO', 'geometry', $problemTagsCnt['geometry']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLADH', 'ad-hoc', $problemTagsCnt['ad-hoc']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLFLW', 'flow', $problemTagsCnt['flow']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLDAC', 'divconq', $problemTagsCnt['divconq']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLSTR', 'strings', $problemTagsCnt['strings']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLSOR', 'sorting', $problemTagsCnt['sorting']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLGRD', 'greedy', $problemTagsCnt['greedy']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLGAM', 'game', $problemTagsCnt['game']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLDST', 'datastruct', $problemTagsCnt['datastruct']);
        $this->achievementTags($brain, $achieved, $user, $userSolvedPerTag, 'ALLPNP', 'np', $problemTagsCnt['np']);
        $this->achievementAllTags($brain, $achieved, $user, $userSolvedPerTag);

        // Solving speed achievements
        $this->achievementSpeed($brain, $achieved, $user, $userSolved, 'TWOTEN', 2, 10 * 60);
        $this->achievementSpeed($brain, $achieved, $user, $userSolved, '03IN24', 3, 24 * 60 * 60);
        $this->achievementSpeed($brain, $achieved, $user, $userSolved, '05IN24', 5, 24 * 60 * 60);
        $this->achievementSpeed($brain, $achieved, $user, $userSolved, '10IN24', 10, 24 * 60 * 60);

        // Training section achievements
        $this->achievementTraining($brain, $achieved, $user, $userSolved, $training);

        // Games
        $this->achievementPlayedGame($brain, $achieved, $user, $standings, $userAllSubmits, 'PLAYED', 1);
        $this->achievementPlayedGame($brain, $achieved, $user, $standings, $userAllSubmits, 'GAMERR', count($standings));
        $this->achievementWonGame($brain, $achieved, $user, $standings, $userAllSubmits);

        // TODO: Competitions

        // Unusual dates
        $this->achievementDate($brain, $achieved, $user, $userProblemSubmits, 'BIRTHD', date('m-d', strtotime($user->birthdate)));
        $this->achievementDate($brain, $achieved, $user, $userProblemSubmits, 'CHRSTM', '12-25');
        $this->achievementDate($brain, $achieved, $user, $userProblemSubmits, 'NUYEAR', '01-01');

        // Unusual times
        $this->achievementUnusualTime($brain, $achieved, $user, $userAllSubmits, 'NIGHTY', 2, 6);
        $this->achievementUnusualTime($brain, $achieved, $user, $userAllSubmits, 'MORNIN', 6, 10);

        // Ad-hoc achievements
        $this->achievementAllTasks($brain, $achieved, $user, $userSolved, $problemDifficulties);
        $this->achievementRegistered($brain, $achieved, $user);
        $this->achievementReported($brain, $achieved, $user, $userReports);
        $this->achievementActive($brain, $achieved, $user);
        $this->achievementTested($brain, $achieved, $user, $userProblemSubmits);
        $this->achievementRanked($brain, $achieved, $user, $ranking, 'RANK01', 1);
        $this->achievementRanked($brain, $achieved, $user, $ranking, 'RANK10', 10);
        $this->achievementVirgin($brain, $achieved, $user, $accepted);
        $this->achievementRainbow($brain, $achieved, $user, $userAllSubmits);
        $this->achievementOldtimer($brain, $achieved, $user);
        $this->achievementSoWrong($brain, $achieved, $user, $userProblemSubmits);
        $this->achievementDisaster($brain, $achieved, $user, $userProblemSubmits);
        $this->achievementUnsuccess($brain, $achieved, $user, $userProblemSubmits, '10FAIL', 10);
        $this->achievementUnsuccess($brain, $achieved, $user, $userProblemSubmits, '20FAIL', 20);
        $this->achievementAccurate($brain, $achieved, $user, $userProblemSubmits, '20FRST', 20);
        $this->achievementAccurate($brain, $achieved, $user, $userProblemSubmits, '50FRST', 50);
        $this->achievementAccurate($brain, $achieved, $user, $userProblemSubmits, '100FST', 100);
        $this->achievementPedantic($brain, $achieved, $user, $userProblemSubmits, $problems);
        // TODO: AVATAR
        $this->achievementProfile($brain, $achieved, $user);
        $this->achievementReimplemented($brain, $achieved, $user, $userProblemSubmits, $userProblemSources);
        $this->achievementOffByOne($brain, $achieved, $user, $userProblemSubmits, $userProblemSources);
        $this->achievementDifferentLocations($brain, $achieved, $user, $userProblemSubmits);

        // Problem-specific achievements
        $this->achievementProblem($brain, $achieved, $user, $userSolved, $problems, 'SHEEPS', 'Sheep');
        $this->achievementProblem($brain, $achieved, $user, $userSolved, $problems, 'TOWERS', 'Radio Towers');
        $this->achievementProblem($brain, $achieved, $user, $userSolved, $problems, 'DTHSTR', 'Deathstars');
        $this->achievementProblem($brain, $achieved, $user, $userSolved, $problems, 'SNWCLN', 'Snow Cleaning');
        $this->achievementProblem($brain, $achieved, $user, $userSolved, $problems, 'SHADES', 'Shades');

        // Submission ID achievements
        $this->achievementSpecialIDSubmit($brain, $achieved, $user, $userAllSubmits, 'PRMSUB', $this->PRIME_NUMBERS);
        $this->achievementSpecialIDSubmit($brain, $achieved, $user, $userAllSubmits, 'FIBSUB', $this->FIBONACCI_NUMBERS);
        $this->achievementSpecialIDSubmit($brain, $achieved, $user, $userAllSubmits, 'PRFSUB', $this->PERFECT_NUMBERS);
        $this->achievementSpecialIDSubmit($brain, $achieved, $user, $userAllSubmits, '124SUB', $this->POWER_OF_TWO_NUMBERS);
        $this->achievementSpecialIDSubmit($brain, $achieved, $user, $userAllSubmits, '110SUB', $this->POWER_OF_TEN_NUMBERS);
        $this->achievementSpecialIDSubmit($brain, $achieved, $user, $userAllSubmits, '314SUB', $this->PI_PREFIX_NUMBERS);
        $this->achievementSpecialIDSubmit($brain, $achieved, $user, $userAllSubmits, '271SUB', $this->E_PREFIX_NUMBERS);

        // Code length achievements
    }

    private function recalcAll() {
        $start = microtime(true);

        $this->initSpecialNumbers();

        $brain = new Brain();
        $games = $brain->getAllGames();
        $problems = $brain->getAllProblems();
        $submits = $brain->getAllSubmits();
        $sources = $brain->getAllSources();
        $ranking = RankingPage::getRanking();

        $standings = array();
        foreach ($games as $game) {
            $problem = Problem::instanceFromArray($game);
            if ($problem->type == 'game') {
                $standings[$game['id']] = GamesPage::getGameRanking($problem);
            } else if ($problem->type == 'relative') {
                $standings[$game['id']] = GamesPage::getRelativeRanking($problem);
            }
        }

        $accepted = array();
        foreach ($submits as $submit) {
            if ($submit['status'] == 'AC') {
                array_push($accepted, $submit);
            }
        }

        $users = $brain->getUsers();

        // Problems info
        $problemTags = array();
        $problemTagsCnt = array();
        $problemDifficulties = array();
        $problemDifficultiesCnt = array();
        foreach ($problems as $problem) {
            // Tags
            $tags = explode(',', $problem['tags']);
            $problemTags[$problem['id']] = $tags;
            foreach ($tags as $tag) {
                if (!array_key_exists($tag, $problemTagsCnt))
                    $problemTagsCnt[$tag] = 0;
                $problemTagsCnt[$tag] += 1;
            }

            // Difficulties
            $difficulty = $problem['difficulty'];
            $problemDifficulties[$problem['id']] = $difficulty;
            if (!array_key_exists($difficulty, $problemDifficultiesCnt))
                $problemDifficultiesCnt[$difficulty] = 0;
            $problemDifficultiesCnt[$difficulty] += 1;
        }

        // Sent submissions on problems
        $userAllSubmits = array();
        $userProblemSubmits = array();
        $userProblemSources = array();
        foreach ($users as $user) {
            $userAllSubmits[$user['id']] = array();
            $userProblemSubmits[$user['id']] = array();
            $userProblemSources[$user['id']] = array();
        }

        $submitCount = count($submits);
        for ($i = 0; $i < $submitCount; $i++) {
            if ($submits[$i]['id'] != $sources[$i]['submitId'])
                error_log('Mismatch in submits and sources at index ' . $i . '!');
            array_push($userAllSubmits[$submits[$i]['userId']], $submits[$i]);
            if (array_key_exists($submits[$i]['problemId'], $problemDifficulties)) {
                array_push($userProblemSubmits[$submits[$i]['userId']], $submits[$i]);
                array_push($userProblemSources[$submits[$i]['userId']], $sources[$i]);
            }
        }

        // Skip service user and admin
        $userCount = count($users);
        for ($i = 2; $i < $userCount; $i += 1) {
            $user = User::instanceFromArray($users[$i]);
            $this->updateAll($user, $games, $standings, $problems, $submits, $accepted, $sources, $ranking,
                $problemTags, $problemTagsCnt, $problemDifficulties, $problemDifficultiesCnt,
                $userAllSubmits[$user->id], $userProblemSubmits[$user->id], $userProblemSources[$user->id]);
        }
        return microtime(true) - $start;
    }

    private function getAchievementsList() {
        $achievementsFile = file_get_contents($GLOBALS['PATH_ACHIEVEMENTS'] . '/achievements.json');
        $achievementsData = json_decode($achievementsFile, true);

        $brain = new Brain();

        $userName = array();
        foreach ($brain->getUsers() as $user)
            $userName[$user['id']] = $user['username'];

        $perType = array();
        foreach ($achievementsData as $achievement)
            $perType[$achievement['key']] = array();

        $achievements = $brain->getAchievements();
        foreach ($achievements as $achievement)
            array_push($perType[$achievement['achievement']], $userName[$achievement['user']]);

        $content = '<div>';
        foreach ($achievementsData as $achievement) {
            $users = '';
            for ($i = 0; $i < min(5, count($perType[$achievement['key']])); $i += 1) {
                if ($i > 0) $users .= ', ';
                $users .= $perType[$achievement['key']][$i];
            }
            $info = $achievement['description'] . PHP_EOL . $users;
            $content .= '
                <div class="achievement" title="' . $info . '">
                    <i class="' . $achievement['icon'] . ' fa-fw"></i>
                    <span>' . $achievement['title'] . ' | ' . count($perType[$achievement['key']]) . '</span>
                </div>
            ';
        }
        $content .= '</div>';
        $content .= '<div style="text-align: center; font-weight: bold;">All achievements: ' . count($achievements) . '</div>';

        return $content;
    }

    public function getContent() {
        $elapsed = '';
        if (isset($_GET['recalc']) && $_GET['recalc'] == 'true') {
            $execTime = $this->recalcAll();
            $elapsed = sprintf('<p>Calculated in %.2f seconds.</p>', $execTime);
        }
        $allAchievements = $this->getAchievementsList();
        $content = inBox('
            <h1>Админ::Постижения</h1>
            ' . $elapsed . '
            <br>
            ' . $allAchievements . '
            <div class="centered">
                <a href="/admin/achievements/recalc">
                    <input type="submit" id="recalc-button" value="Преизчисли" class="button button-color-blue button-large">
                </a>
            </div>
        ');
        return $content;
    }
}

?>
