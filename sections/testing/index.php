<?php

declare(strict_types=1);

enforce_login();
if (!check_perms('users_mod')) {
    error(404);
} else {
    Testing::init();

    if (!empty($_REQUEST['action'])) {
        match ($_REQUEST['action']) {
            'class' => include SERVER_ROOT . '/sections/testing/class.php',
            'ajax_run_method' => include SERVER_ROOT . '/sections/testing/ajax_run_method.php',
            'comments' => include SERVER_ROOT . '/sections/testing/comments.php',
            default => include SERVER_ROOT . '/sections/testing/classes.php',
        };
    } else {
        include SERVER_ROOT . '/sections/testing/classes.php';
    }
}
