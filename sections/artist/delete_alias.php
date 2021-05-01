<?php

declare(strict_types=1);

authorize();
if (!check_perms('torrents_edit')) {
    error(403);
}

$AliasID = $_GET['aliasid'];

if (!is_number($AliasID)) {
    error(0);
}

$DB->query("
  SELECT aa.AliasID
  FROM artists_alias AS aa
    JOIN artists_alias AS aa2 ON aa.ArtistID=aa2.ArtistID
  WHERE aa.AliasID=" . $AliasID);

if (1 === $DB->record_count()) {
    //This is the last alias on the artist
    error("That alias is the last alias for that artist; removing it would cause bad things to happen.");
}

$DB->query("
  SELECT GroupID
  FROM torrents_artists
  WHERE AliasID='{$AliasID}'");
if ($DB->has_results()) {
    [$GroupID] = $DB->next_record();
    if (0 != $GroupID) {
        error(sprintf('That alias still has the group (<a href="torrents.php?id=%s">%s</a>) attached. Fix that first.', $GroupID, $GroupID));
    }
}

$DB->query("
  SELECT aa.ArtistID, ag.Name, aa.Name
  FROM artists_alias AS aa
    JOIN artists_group AS ag ON aa.ArtistID=ag.ArtistID
  WHERE aa.AliasID={$AliasID}");
[$ArtistID, $ArtistName, $AliasName] = $DB->next_record(MYSQLI_NUM, false);

$DB->query("
  DELETE FROM artists_alias
  WHERE AliasID='{$AliasID}'");
$DB->query("
  UPDATE artists_alias
  SET Redirect='0'
  WHERE Redirect='{$AliasID}'");

Misc::write_log(sprintf('The alias %s (%s) was removed from the artist %s (%s) by user %s (%s)', $AliasID, $AliasName, $ArtistID, $ArtistName, $LoggedUser[ID], $LoggedUser[Username]));

header(sprintf('Location: %s', $_SERVER[HTTP_REFERER]));
