<?php

declare(strict_types=1);
if (isset($LoggedUser['ID'])) {
    if (!isset($_REQUEST['action'])) {
        include __DIR__ . '/private.php';
    } else {
        if ('poll' === $_REQUEST['action']) {
            include SERVER_ROOT . '/sections/forums/poll_vote.php';
        } else {
            error(0);
        }
    }
} else {
    header('Location: login.php');
}
