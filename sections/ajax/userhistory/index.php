<?php

declare(strict_types=1);

if ($_GET['type']) {
    if ('posts' == $_GET['type']) {
        // Load post history page
        include __DIR__ . '/post_history.php';
    } else {
        print json_encode(['status' => 'failure'], JSON_THROW_ON_ERROR);
    }
} else {
    print json_encode(['status' => 'failure'], JSON_THROW_ON_ERROR);
}
