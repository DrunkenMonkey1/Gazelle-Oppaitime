<?php

declare(strict_types=1);

enforce_login();
if (!check_perms('site_upload')) {
    error(403);
}
if ($LoggedUser['DisableUpload']) {
    error('Your upload privileges have been revoked.');
}
// build the page

if (!empty($_POST['submit'])) {
    include __DIR__ . '/upload_handle.php';
} else {
    include SERVER_ROOT . '/sections/upload/upload.php';
}
