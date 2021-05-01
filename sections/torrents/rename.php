<?php

declare(strict_types=1);

authorize();

$GroupID = $_POST['groupid'];
$OldGroupID = $GroupID;
$NewName = $_POST['name'];


$DB->query("
  SELECT ID
  FROM torrents
  WHERE GroupID = " . db_string($GroupID) . "
    AND UserID = " . $LoggedUser['ID']);
$Contributed = $DB->has_results();

if (!$GroupID || !is_number($GroupID)) {
    error(404);
}

if (!$Contributed && !check_perms('torrents_edit')) {
    error(403);
}

if (empty($NewName)) {
    error('Torrent groups must have a name');
}

$DB->query("
  UPDATE torrents_group
  SET Name = '" . db_string($NewName) . "'
  WHERE ID = '{$GroupID}'");
$Cache->delete_value(sprintf('torrents_details_%s', $GroupID));

Torrents::update_hash($GroupID);

$DB->query("
  SELECT Name
  FROM torrents_group
  WHERE ID = {$GroupID}");
[$OldName] = $DB->next_record(MYSQLI_NUM, false);

if ($OldName != $NewName) {
    Misc::write_log(sprintf('Torrent Group %s (%s)\'s title was changed to "%s" from "%s" by ', $GroupID, $OldName,
            $NewName, $OldName) . $LoggedUser['Username']);
    Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'],
        sprintf('title changed to "%s" from "%s"', $NewName, $OldName), 0);
}


//if ($OldJP != $NewJP) {
//    Misc::write_log(sprintf('Torrent Group %s (%s)\'s japanese title was changed to "%s" from "%s" by ', $GroupID,
//            $OldJP, $NewJP, $OldJP) . $LoggedUser['Username']);
//    Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'],
//        sprintf('japanese title changed to "%s" from "%s"', $NewJP, $OldJP), 0);
//}

header(sprintf('Location: torrents.php?id=%s', $GroupID));
