<?php

declare(strict_types=1);

enforce_login();

if (!check_perms('users_mod')) {
    error(403);
}
if (!empty($_POST['action'])) {
    match ($_POST['action']) {
        'take_create' => include SERVER_ROOT . '/sections/sitehistory/take_create.php',
        'take_edit' => include SERVER_ROOT . '/sections/sitehistory/take_edit.php',
        default => error(404),
    };
} elseif (!empty($_GET['action'])) {
    match ($_GET['action']) {
        'search' => include SERVER_ROOT . '/sections/sitehistory/history.php',
        'edit' => include SERVER_ROOT . '/sections/sitehistory/edit.php',
        default => error(404),
    };
} else {
    include SERVER_ROOT . '/sections/sitehistory/history.php';
}
