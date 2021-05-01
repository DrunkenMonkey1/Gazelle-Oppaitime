<?php

declare(strict_types=1);

// Already done in /sections/ajax/index.php
//enforce_login();

if (!empty($LoggedUser['DisableForums'])) {
    print json_encode(['status' => 'failure']);
    die();
} else {
    // Replace the old hard-coded forum categories
    $ForumCats = Forums::get_forum_categories();
    
    //This variable contains all our lovely forum data
    $Forums = Forums::get_forums();
    
    if (empty($_GET['type']) || 'main' == $_GET['type']) {
        include SERVER_ROOT . '/sections/ajax/forum/main.php';
    } else {
        match ($_GET['type']) {
            'viewforum' => include SERVER_ROOT . '/sections/ajax/forum/forum.php',
            'viewthread' => include SERVER_ROOT . '/sections/ajax/forum/thread.php',
            default => print json_encode(['status' => 'failure']),
        };
    }
}
