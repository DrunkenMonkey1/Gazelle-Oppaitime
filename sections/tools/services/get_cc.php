<?php

declare(strict_types=1);

if (isset($_SERVER['http_if_modified_since'])) {
    header('Status: 304 Not Modified');
    die();
}

header('Expires: ' . date('D, d-M-Y H:i:s \U\T\C', time() + 3600 * 24 * 120)); //120 days
header('Last-Modified: ' . date('D, d-M-Y H:i:s \U\T\C', time()));

if (!check_perms('users_view_ips')) {
    die('Access denied.');
}

if (empty($_GET['ip'])) {
    die('Invalid IP address.');
}

die(Tools::geoip($_GET['ip']));
