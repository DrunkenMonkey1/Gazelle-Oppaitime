<?php

declare(strict_types=1);

enforce_login();

if (isset($_REQUEST['action'])) {
    if ('' == $_REQUEST['action']) {
        include __DIR__ . '/upload_1GB.php';
    } else {
        error(404);
    }
} else {
    require SERVER_ROOT . '/sections/slaves/slaves.php';
}
