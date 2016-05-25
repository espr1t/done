<?php
require_once('common.php');
require_once('page.php');

class RankingPage extends Page {
    public function getTitle() {
        return 'O(N)::Ranking';
    }
    
    public function getContent() {
        $userInfo = userInfo($this->user);
        $content = inBox('Under construction.');
        return $userInfo . $content;
    }
    
}

?>