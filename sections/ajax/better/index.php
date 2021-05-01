<?php

declare(strict_types=1);

//Include all the basic stuff...

enforce_login();
if (isset($_GET['method'])) {
    match ($_GET['method']) {
        'transcode' => include SERVER_ROOT . '/sections/ajax/better/transcode.php',
        'single' => include SERVER_ROOT . '/sections/ajax/better/single.php',
        'snatch' => include SERVER_ROOT . '/sections/ajax/better/snatch.php',
        'artistless' => include SERVER_ROOT . '/sections/ajax/better/artistless.php',
        'tags' => include SERVER_ROOT . '/sections/ajax/better/tags.php',
        'folders' => include SERVER_ROOT . '/sections/ajax/better/folders.php',
        'files' => include SERVER_ROOT . '/sections/ajax/better/files.php',
        'upload' => include SERVER_ROOT . '/sections/ajax/better/upload.php',
        default => print json_encode(['status' => 'failure',], JSON_THROW_ON_ERROR),
    };
} else {
    print json_encode(['status' => 'failure'], JSON_THROW_ON_ERROR);
}
