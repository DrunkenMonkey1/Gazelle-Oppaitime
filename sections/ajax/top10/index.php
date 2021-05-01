<?php

declare(strict_types=1);

// Already done in /sections/ajax/index.php
//enforce_login();

if (!check_perms('site_top10')) {
    print json_encode(['status' => 'failure'], JSON_THROW_ON_ERROR);
    die();
}

if (empty($_GET['type']) || 'torrents' == $_GET['type']) {
    include SERVER_ROOT . '/sections/ajax/top10/torrents.php';
} else {
    match ($_GET['type']) {
        'users' => include SERVER_ROOT . '/sections/ajax/top10/users.php',
        'tags' => include SERVER_ROOT . '/sections/ajax/top10/tags.php',
        'history' => include SERVER_ROOT . '/sections/ajax/top10/history.php',
        default => print json_encode(['status' => 'failure'], JSON_THROW_ON_ERROR),
    };
}
