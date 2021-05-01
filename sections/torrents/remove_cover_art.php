<?php

declare(strict_types=1);

authorize();
if (!check_perms('site_edit_wiki')) {
    error(403);
}

$ID = $_GET['id'];
$GroupID = $_GET['groupid'];


if (!is_number($ID) || !is_number($ID) || !is_number($GroupID) || !is_number($GroupID)) {
    error(404);
}

$DB->query("
  SELECT Image, Summary
  FROM cover_art
  WHERE ID = '{$ID}'");
[$Image, $Summary] = $DB->next_record();

$DB->query("
  DELETE FROM cover_art
  WHERE ID = '{$ID}'");

$DB->query("
  INSERT INTO group_log
    (GroupID, UserID, Time, Info)
  VALUES
    ('{$GroupID}', " . $LoggedUser['ID'] . ", NOW(), '" . db_string(sprintf('Additional cover "%s - %s" removed from group', $Summary, $Image)) . "')");

$Cache->delete_value(sprintf('torrents_cover_art_%s', $GroupID));
header('Location: ' . $_SERVER['HTTP_REFERER']);
