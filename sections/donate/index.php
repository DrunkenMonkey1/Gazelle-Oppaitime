<?php

declare(strict_types=1);

if (!FEATURE_DONATE) {
    header('Location: index.php');
    die();
}

//Module mini-config
include SERVER_ROOT . '/sections/donate/config.php';

if (!isset($_REQUEST['action'])) {
    include SERVER_ROOT . '/sections/donate/donate.php';
} else {
    match ($_REQUEST['action']) {
        'ipn' => include SERVER_ROOT . '/sections/donate/ipn.php',
        'complete' => include SERVER_ROOT . '/sections/donate/complete.php',
        'cancel' => include SERVER_ROOT . '/sections/donate/cancel.php',
    };
}
