<?php

declare(strict_types=1);

enforce_login();

if ($_REQUEST['action']) {
    match ($_REQUEST['action']) {
        'email' => include __DIR__ . '/delete_email.php',
        'takeemail' => include __DIR__ . '/take_delete_email.php',
        'ip' => include __DIR__ . '/delete_ip.php',
        'takeip' => include __DIR__ . '/take_delete_ip.php',
        default => header('Location: index.php'),
    };
} else {
    header('Location: index.php');
}
