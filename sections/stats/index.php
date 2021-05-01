<?php

declare(strict_types=1);

enforce_login();
if (!isset($_REQUEST['action'])) {
    error(404);
} else {
    match ($_REQUEST['action']) {
        'users' => include SERVER_ROOT . '/sections/stats/users.php',
        'torrents' => include SERVER_ROOT . '/sections/stats/torrents.php',
        'network' => include SERVER_ROOT . '/sections/stats/network.php',
    };
}
