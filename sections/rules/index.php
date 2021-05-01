<?php

declare(strict_types=1);

//Include all the basic stuff...
enforce_login();
if (!isset($_GET['p'])) {
    require SERVER_ROOT . '/sections/rules/rules.php';
} else {
    match ($_GET['p']) {
        'ratio' => require SERVER_ROOT . '/sections/rules/ratio.php',
        'clients' => require SERVER_ROOT . '/sections/rules/clients.php',
        'chat' => require SERVER_ROOT . '/sections/rules/chat.php',
        'upload' => require SERVER_ROOT . '/sections/rules/upload.php',
        'requests' => require SERVER_ROOT . '/sections/rules/requests.php',
        'collages' => require SERVER_ROOT . '/sections/rules/collages.php',
        'tag' => require SERVER_ROOT . '/sections/rules/tag.php',
        default => error(0),
    };
}
