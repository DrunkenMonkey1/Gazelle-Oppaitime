<?php

if (empty($_GET['type']) || 'inbox' == $_GET['type'] || 'sentbox' == $_GET['type']) {
    require SERVER_ROOT . '/sections/ajax/inbox/inbox.php';
} elseif ('viewconv' == $_GET['type']) {
    require SERVER_ROOT . '/sections/ajax/inbox/viewconv.php';
} else {
    print json_encode(['status' => 'failure']);
    die();
}
