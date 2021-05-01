<?php

declare(strict_types=1);

enforce_login();
if (isset($_GET['method'])) {
    match ($_GET['method']) {
        'screenshots' => include SERVER_ROOT . '/sections/better/screenshots.php',
        'covers' => include SERVER_ROOT . '/sections/better/covers.php',
        'encode' => include SERVER_ROOT . '/sections/better/encode.php',
        'snatch' => include SERVER_ROOT . '/sections/better/snatch.php',
        'upload' => include SERVER_ROOT . '/sections/better/upload.php',
        'tags' => include SERVER_ROOT . '/sections/better/tags.php',
        'folders' => include SERVER_ROOT . '/sections/better/folders.php',
        'files' => include SERVER_ROOT . '/sections/better/files.php',
        default => error(404),
    };
} else {
    include SERVER_ROOT . '/sections/better/better.php';
}
