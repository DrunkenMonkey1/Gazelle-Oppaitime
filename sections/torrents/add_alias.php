<?php

declare(strict_types=1);

authorize();

$UserID = $LoggedUser['ID'];
$GroupID = db_string($_POST['groupid']);
$ArtistNames = $_POST['artistname'];

if (!is_number($GroupID) || !$GroupID) {
    error(0);
}

$DB->query("
  SELECT Name
  FROM torrents_group
  WHERE ID = {$GroupID}");
if (!$DB->has_results()) {
    error(404);
}
[$GroupName] = $DB->next_record(MYSQLI_NUM, false);

foreach ($ArtistNames as $i => $ArtistName) {
    $ArtistName = Artists::normalise_artist_name($ArtistName);
    if (strlen($ArtistName) > 0) {
        $DB->query("
      SELECT ArtistID
      FROM artists_group
      WHERE Name = ?", $ArtistName);

        if ($DB->has_results()) {
            [$ArtistID] = $DB->next_record(MYSQLI_NUM, false);
        }

        if (!$ArtistID) {
            $ArtistName = db_string($ArtistName);
            $DB->query("
        INSERT INTO artists_group (Name)
        VALUES ( ? )", $ArtistName);
            $ArtistID = $DB->inserted_id();
        }

        $DB->query("
      INSERT IGNORE INTO torrents_artists
        (GroupID, ArtistID, UserID)
      VALUES
        ('{$GroupID}', '{$ArtistID}', '{$UserID}')");

        if ($DB->affected_rows()) {
            Misc::write_log(sprintf('Artist %s (%s) was added to the group %s (%s) by user ', $ArtistID, $ArtistName, $GroupID, $GroupName) . $LoggedUser['ID'] . ' (' . $LoggedUser['Username'] . ')');
            Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'], sprintf('added artist %s', $ArtistName), 0);
            $Cache->delete_value(sprintf('torrents_details_%s', $GroupID));
            $Cache->delete_value(sprintf('groups_artists_%s', $GroupID)); // Delete group artist cache
      $Cache->delete_value(sprintf('artist_groups_%s', $ArtistID)); // Delete artist group cache
      Torrents::update_hash($GroupID);
        }
    }
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
