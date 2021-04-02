<?php

authorize();

if (!isset($_REQUEST['page']) || !in_array($_REQUEST['page'], ['artist', 'collages', 'requests', 'torrents'], true) || !isset($_POST['pageid']) || !is_number($_POST['pageid']) || !isset($_POST['body']) || '' === trim($_POST['body'])) {
    error(0);
}

if ($LoggedUser['DisablePosting']) {
    error('Your posting privileges have been removed.');
}

$Page = $_REQUEST['page'];
$PageID = (int)$_POST['pageid'];
if (!$PageID) {
    error(404);
}

if (isset($_POST['subscribe']) && false === Subscriptions::has_subscribed_comments($Page, $PageID)) {
    Subscriptions::subscribe_comments($Page, $PageID);
}

$PostID = Comments::post($Page, $PageID, $_POST['body']);

header("Location: " . Comments::get_url($Page, $PageID, $PostID));
die();
