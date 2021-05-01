<?php

declare(strict_types=1);

authorize();

$CollageID = $_POST['collageid'];
if (!is_number($CollageID) || !$CollageID) {
    error(404);
}

$DB->query("
  SELECT Name, CategoryID, UserID
  FROM collages
  WHERE ID = '{$CollageID}'");
[$Name, $CategoryID, $UserID] = $DB->next_record(MYSQLI_NUM, false);

if (!check_perms('site_collages_delete') && $UserID !== $LoggedUser['ID']) {
    error(403);
}

$Reason = trim($_POST['reason']);
if ('' === $Reason) {
    error('You must enter a reason!');
}

$DB->query("
  SELECT GroupID
  FROM collages_torrents
  WHERE CollageID = '{$CollageID}'");
while ([$GroupID] = $DB->next_record()) {
    $Cache->delete_value(sprintf('torrents_details_%s', $GroupID));
    $Cache->delete_value(sprintf('torrent_collages_%s', $GroupID));
    $Cache->delete_value(sprintf('torrent_collages_personal_%s', $GroupID));
}

//Personal collages have CategoryID 0
if (0 == $CategoryID) {
    $DB->query("
    DELETE FROM collages
    WHERE ID = '{$CollageID}'");
    $DB->query("
    DELETE FROM collages_torrents
    WHERE CollageID = '{$CollageID}'");
    Comments::delete_page('collages', $CollageID);
} else {
    $DB->query("
    UPDATE collages
    SET Deleted = '1'
    WHERE ID = '{$CollageID}'");
    Subscriptions::flush_subscriptions('collages', $CollageID);
    Subscriptions::flush_quote_notifications('collages', $CollageID);
}

Misc::write_log(sprintf('Collage %s (%s) was deleted by ', $CollageID, $Name) . $LoggedUser['Username'] . sprintf(': %s', $Reason));

$Cache->delete_value(sprintf('collage_%s', $CollageID));
header('Location: collages.php');
