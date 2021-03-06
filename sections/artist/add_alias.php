<?php

declare(strict_types=1);

authorize();

if (!check_perms('torrents_edit')) {
    error(403);
}
$ArtistID = $_POST['artistid'];
$Redirect = $_POST['redirect'];
$AliasName = Artists::normalise_artist_name($_POST['name']);
$DBAliasName = db_string($AliasName);
if (!$Redirect) {
    $Redirect = 0;
}

if (!is_number($ArtistID) || 0 !== $Redirect && !is_number($Redirect) || !$ArtistID) {
    error(0);
}

if ('' == $AliasName) {
    error('Blank artist name.');
}

/*
 * In the case of foo, who released an album before changing his name to bar and releasing another
 * the field shared to make them appear on the same artist page is the ArtistID
 * 1. For a normal artist, there'll be one entry, with the ArtistID, the same name as the artist and a 0 redirect
 * 2. For Celine Dion (C�line Dion), there's two, same ArtistID, diff Names, one has a redirect to the alias of the first
 * 3. For foo, there's two, same ArtistID, diff names, no redirect
 */

$DB->query("
  SELECT AliasID, ArtistID, Name, Redirect
  FROM artists_alias
  WHERE Name = '{$DBAliasName}'");
if ($DB->has_results()) {
    while ([$CloneAliasID, $CloneArtistID, $CloneAliasName, $CloneRedirect] = $DB->next_record(MYSQLI_NUM, false)) {
        if (0 === strcasecmp($CloneAliasName, $AliasName)) {
            break;
        }
    }
    if ($CloneAliasID) {
        if ($ArtistID == $CloneArtistID && 0 == $Redirect) {
            if (0 != $CloneRedirect) {
                $DB->query("
          UPDATE artists_alias
          SET ArtistID = '{$ArtistID}', Redirect = 0
          WHERE AliasID = '{$CloneAliasID}'");
                Misc::write_log(sprintf('Redirection for the alias %s (%s) for the artist %s was removed by user %s (%s)', $CloneAliasID, $DBAliasName, $ArtistID, $LoggedUser[ID], $LoggedUser[Username]));
            } else {
                error('No changes were made as the target alias did not redirect anywhere.');
            }
        } else {
            error('An alias by that name already exists <a href="artist.php?id=' . $CloneArtistID . '">here</a>. You can try renaming that artist to this one.');
        }
    }
}
if (!$CloneAliasID) {
    if ($Redirect) {
        $DB->query("
      SELECT ArtistID, Redirect
      FROM artists_alias
      WHERE AliasID = {$Redirect}");
        if (!$DB->has_results()) {
            error('Cannot redirect to a nonexistent artist alias.');
        }
        [$FoundArtistID, $FoundRedirect] = $DB->next_record();
        if ($ArtistID != $FoundArtistID) {
            error('Redirection must target an alias for the current artist.');
        }
        if (0 != $FoundRedirect) {
            $Redirect = $FoundRedirect;
        }
    }
    $DB->query("
    INSERT INTO artists_alias
      (ArtistID, Name, Redirect, UserID)
    VALUES
      ({$ArtistID}, '{$DBAliasName}', {$Redirect}, " . $LoggedUser['ID'] . ')');
    $AliasID = $DB->inserted_id();

    $DB->query("
    SELECT Name
    FROM artists_group
    WHERE ArtistID = {$ArtistID}");
    [$ArtistName] = $DB->next_record(MYSQLI_NUM, false);

    Misc::write_log(sprintf('The alias %s (%s) was added to the artist %s (', $AliasID, $DBAliasName, $ArtistID) . db_string($ArtistName) . ') by user ' . $LoggedUser['ID'] . ' (' . $LoggedUser['Username'] . ')');
}
header('Location: ' . $_SERVER['HTTP_REFERER']);
