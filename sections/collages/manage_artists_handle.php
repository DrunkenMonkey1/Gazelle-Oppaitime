<?php

declare(strict_types=1);

authorize();

$CollageID = $_POST['collageid'];
if (!is_number($CollageID)) {
    error(404);
}

$DB->query("
  SELECT UserID, CategoryID
  FROM collages
  WHERE ID = '{$CollageID}'");
[$UserID, $CategoryID] = $DB->next_record();
if ('0' === $CategoryID && $UserID !== $LoggedUser['ID'] && !check_perms('site_collages_delete')) {
    error(403);
}
if ($CategoryID !== array_search(ARTIST_COLLAGE, $CollageCats, true)) {
    error(403);
}

$ArtistID = $_POST['artistid'];
if (!is_number($ArtistID)) {
    error(404);
}

if ('Remove' === $_POST['submit']) {
    $DB->query("
    DELETE FROM collages_artists
    WHERE CollageID = '{$CollageID}'
      AND ArtistID = '{$ArtistID}'");
    $Rows = $DB->affected_rows();
    $DB->query("
    UPDATE collages
    SET NumTorrents = NumTorrents - {$Rows}
    WHERE ID = '{$CollageID}'");
    $Cache->delete_value(sprintf('artists_collages_%s', $ArtistID));
    $Cache->delete_value(sprintf('artists_collages_personal_%s', $ArtistID));
} elseif (isset($_POST['drag_drop_collage_sort_order'])) {
    @parse_str($_POST['drag_drop_collage_sort_order'], $Series);
    $Series = @array_shift($Series);
    if (is_array($Series)) {
        $SQL = [];
        foreach ($Series as $Sort => $ArtistID) {
            if (is_number($Sort) && is_number($ArtistID)) {
                $Sort = ($Sort + 1) * 10;
                $SQL[] = sprintf('(%d, %d, %d)', $ArtistID, $Sort, $CollageID);
            }
        }

        $SQL = '
      INSERT INTO collages_artists
        (ArtistID, Sort, CollageID)
      VALUES
        ' . implode(', ', $SQL) . '
      ON DUPLICATE KEY UPDATE
        Sort = VALUES (Sort)';

        $DB->query($SQL);
    }
} else {
    $Sort = $_POST['sort'];
    if (!is_number($Sort)) {
        error(404);
    }
    $DB->query("
    UPDATE collages_artists
    SET Sort = '{$Sort}'
    WHERE CollageID = '{$CollageID}'
      AND ArtistID = '{$ArtistID}'");
}

$Cache->delete_value(sprintf('collage_%s', $CollageID));
header(sprintf('Location: collages.php?action=manage_artists&collageid=%s', $CollageID));
