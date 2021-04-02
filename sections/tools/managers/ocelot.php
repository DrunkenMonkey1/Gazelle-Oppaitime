<?php

$Key = $_REQUEST['key'];
$Type = $_REQUEST['type'];

if ((TRACKER_SECRET != $Key) || TRACKER_HOST != $_SERVER['REMOTE_ADDR']) {
    send_irc('PRIVMSG ' . LAB_CHAN . ' :Ocelot Auth Failure ' . $_SERVER['REMOTE_ADDR']);
    error(403);
}

switch ($Type) {
  case 'expiretoken':
    if (isset($_GET['tokens'])) {
        $Tokens = explode(',', $_GET['tokens']);
        if (empty($Tokens)) {
            error(0);
        }
        $Cond = $UserIDs = [];
        foreach ($Tokens as $Key => $Token) {
            [$UserID, $TorrentID] = explode(':', $Token);
            if (!is_number($UserID) || !is_number($TorrentID)) {
                continue;
            }
            $Cond[] = "(UserID = $UserID AND TorrentID = $TorrentID)";
            $UserIDs[] = $UserID;
        }
        if (!empty($Cond)) {
            $Query = "
          UPDATE users_freeleeches
          SET Expired = TRUE
          WHERE " . implode(" OR ", $Cond);
            $DB->query($Query);
            foreach ($UserIDs as $UserID) {
                $Cache->delete_value("users_tokens_$UserID");
            }
        }
    } else {
        $TorrentID = $_REQUEST['torrentid'];
        $UserID = $_REQUEST['userid'];
        if (!is_number($TorrentID) || !is_number($UserID)) {
            error(403);
        }
        $DB->query("
        UPDATE users_freeleeches
        SET Expired = TRUE
        WHERE UserID = $UserID
          AND TorrentID = $TorrentID");
        $Cache->delete_value("users_tokens_$UserID");
    }
    break;
}
