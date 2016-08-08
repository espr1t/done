<?php
require_once('common.php');
require_once('page.php');

class RankingPage extends Page {
    public function getTitle() {
        return 'O(N)::Ranking';
    }
    
    private function getRanking() {
        $ranking = '';

        $place = 0;
        $entries = scandir($GLOBALS['PATH_USERS']);
        foreach ($entries as $entry) {
            if (!preg_match(User::$user_info_re, basename($entry))) {
                continue;
            }

            $json = file_get_contents($GLOBALS['PATH_USERS'] . $entry);
            $info = json_decode($json, true);

            if ($info['username'] == 'anonymous') {
                continue;
            }

            $place = $place + 1;
            $username = '<a href="/users/' . $info['username'] . '"><div class="user">' . $info['username'] . '</div></a>';
            $solved = 1;
            $achievements = 1;
            $score = 42;
            if ($info['town'] == '') {
                $info['town'] = '-';
            }
            $row = '
                <tr ' . ($place % 2 == 0 ? 'class="ranking-row-even"' : '') . '>
                    <td>' . $place . '</td><td>' . $username . '</td><td>' . $info['name'] . '</td><td>' . $info['town'] . '</td><td>' . $solved . '</td><td>' . $achievements . '</td><td>' . $score . '</td>
                </tr>
            ';
            $ranking .= $row;
        }
        return $ranking;
    }

    public function getContent() {
        $ranking = $this->getRanking();
        $table = '
            <table class="ranking">
                <tr>
                    <th>#</th><th>Потребител</th><th>Име</th><th>Град</th><th>Задачи</th><th>Постижения</th><th>Точки</th>
                </tr>
                ' . $ranking . '
            </table>
        ';
        $content = '
            <h1>Класиране</h1>
            <div class="separator"></div>
            <br>
        ';
        return inBox($content . $table);
    }
    
}

?>