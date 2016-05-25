<?php
require_once('common.php');
require_once('page.php');

class ErrorPage extends Page {
    public function getTitle() {
        return 'O(N)::Error';
    }
    
    public function getContent() {
        $userInfo = userInfo($this->user);
        $content = inBox('Error Page (404).');
        return $userInfo . $content;
    }
    
}

?>