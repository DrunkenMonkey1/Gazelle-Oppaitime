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


$GroupID = $_POST['groupid'];
if (!is_number($GroupID)) {
    error(404);
}

if ('Remove' === $_POST['submit']) {
    $DB->query("
    DELETE FROM collages_torrents
    WHERE CollageID = '{$CollageID}'
      AND GroupID = '{$GroupID}'");
    $Rows = $DB->affected_rows();
    $DB->query("
    UPDATE collages
    SET NumTorrents = NumTorrents - {$Rows}
    WHERE ID = '{$CollageID}'");
    $Cache->delete_value(sprintf('torrents_details_%s', $GroupID));
    $Cache->delete_value(sprintf('torrent_collages_%s', $GroupID));
    $Cache->delete_value(sprintf('torrent_collages_personal_%s', $GroupID));
} elseif (isset($_POST['drag_drop_collage_sort_order'])) {
    @parse_str($_POST['drag_drop_collage_sort_order'], $Series);
    $Series = @array_shift($Series);
    if (is_array($Series)) {
        $SQL = [];
        foreach ($Series as $Sort => $GroupID) {
            if (is_number($Sort) && is_number($GroupID)) {
                $Sort = ($Sort + 1) * 10;
                $SQL[] = sprintf('(%d, %d, %d)', $GroupID, $Sort, $CollageID);
            }
        }

        $SQL = '
      INSERT INTO collages_torrents
        (GroupID, Sort, CollageID)
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
    UPDATE collages_torrents
    SET Sort = '{$Sort}'
    WHERE CollageID = '{$CollageID}'
      AND GroupID = '{$GroupID}'");
}

$Cache->delete_value(sprintf('collage_%s', $CollageID));
header(sprintf('Location: collages.php?action=manage&collageid=%s', $CollageID));
