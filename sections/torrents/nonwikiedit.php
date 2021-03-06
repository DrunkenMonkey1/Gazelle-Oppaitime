<?php

declare(strict_types=1);

authorize();

//Set by system
if (!$_POST['groupid'] || !is_number($_POST['groupid'])) {
    error(404);
}
$GroupID = $_POST['groupid'];

//Usual perm checks
if (!check_perms('torrents_edit')) {
    $DB->query("
    SELECT UserID
    FROM torrents
    WHERE GroupID = {$GroupID}");
    $DBCollect = $DB->collect('UserID');
    if (!in_array($LoggedUser['ID'], $DBCollect, true)) {
        error(403);
    }
}


if (check_perms('torrents_freeleech') && (isset($_POST['freeleech']) xor isset($_POST['neutralleech']) xor isset($_POST['unfreeleech']))) {
    if (isset($_POST['freeleech'])) {
        $Free = 1;
    } elseif (isset($_POST['neutralleech'])) {
        $Free = 2;
    } else {
        $Free = 0;
    }

    if (isset($_POST['freeleechtype']) && in_array($_POST['freeleechtype'], [0, 1, 2, 3], true)) {
        $FreeType = $_POST['freeleechtype'];
    } else {
        error(404);
    }

    Torrents::freeleech_groups($GroupID, $Free, $FreeType);
}

$Artists = $_POST['idols'];

//Escape fields
$Studio = db_string($_POST['studio']);
$Series = db_string($_POST['series']);
$DLsiteID = db_string($_POST['dlsiteid']);
$Year = db_string((int)$_POST['year']);
$CatalogueNumber = db_string($_POST['catalogue']);
$Pages = db_string($_POST['pages']);

// Get some info for the group log
$DB->query("
  SELECT Year
  FROM torrents_group
  WHERE ID = {$GroupID}");
[$OldYear] = $DB->next_record();



$DB->query("
  UPDATE torrents_group
  SET
    Year = '{$Year}',
    CatalogueNumber = '" . $CatalogueNumber . "',
    Pages = '" . $Pages . "',
    Studio = '{$Studio}',
    Series = '{$Series}',
    DLsiteID = '{$DLsiteID}'
  WHERE ID = {$GroupID}");

if ($OldYear != $Year) {
    $DB->query("
    INSERT INTO group_log (GroupID, UserID, Time, Info)
    VALUES ('{$GroupID}', " . $LoggedUser['ID'] . ", NOW(), '" . db_string(sprintf('Year changed from %s to %s', $OldYear, $Year)) . "')");
}

$DB->query("
  SELECT ag.Name
  FROM artists_group AS ag
    JOIN torrents_artists AS ta ON ag.ArtistID = ta.ArtistID
  WHERE ta.GroupID = " . $GroupID);

while ($r = $DB->next_record(MYSQLI_ASSOC, true)) {
    $CurrArtists[] = $r['Name'];
}

foreach ($Artists as $Artist) {
    if (!in_array($Artist, $CurrArtists, true)) {
        $DB->query("
      SELECT ArtistID
      FROM artists_group
      WHERE Name = '" . db_string($Artist) . "'");
        if ($DB->has_results()) {
            [$ArtistID] = $DB->next_record();
        } else {
            $DB->query("
        INSERT INTO artists_group
        (Name)
        VALUES
        ('" . db_string($Artist) . "')");
            $ArtistID = $DB->inserted_id();
        }
        $DB->query("
      INSERT INTO torrents_artists
      (GroupID, ArtistID, UserID)
      VALUES
      (" . $GroupID . ", " . $ArtistID . ", " . $LoggedUser['ID'] . ")
      ON DUPLICATE KEY UPDATE UserID=" . $LoggedUser['ID']); // Why does this even happen
        $Cache->delete_value('artist_groups_' . $ArtistID);
    }
}

foreach ($CurrArtists as $CurrArtist) {
    if (!in_array($CurrArtist, $Artists, true)) {
        $DB->query("
      SELECT ArtistID
      FROM artists_group
      WHERE Name = '" . db_string($CurrArtist) . "'");
        if ($DB->has_results()) {
            [$ArtistID] = $DB->next_record();

            $DB->query("
        DELETE FROM torrents_artists
        WHERE ArtistID = " . $ArtistID . "
          AND GroupID = " . $GroupID);

            $DB->query("
        SELECT GroupID
        FROM torrents_artists
        WHERE ArtistID = " . $ArtistID);

            $Cache->delete_value('artist_groups_' . $ArtistID);

            if (!$DB->has_results()) {
                $DB->query("
          SELECT RequestID
          FROM requests_artists
          WHERE ArtistID = " . $ArtistID . "
            AND ArtistID != 0");
                if (!$DB->has_results()) {
                    Artists::delete_artist($ArtistID);
                }
            }
        }
    }
}

$DB->query("
  SELECT ID
  FROM torrents
  WHERE GroupID = '{$GroupID}'");
while ([$TorrentID] = $DB->next_record()) {
    $Cache->delete_value(sprintf('torrent_download_%s', $TorrentID));
}
Torrents::update_hash($GroupID);
$Cache->delete_value(sprintf('torrents_details_%s', $GroupID));

header(sprintf('Location: torrents.php?id=%s', $GroupID));
