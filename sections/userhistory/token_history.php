<?php declare(strict_types=1);
$UserID = isset($_GET['userid']) ? $_GET['userid'] : $LoggedUser['ID'];
if (!is_number($UserID)) {
    error(404);
}

$UserInfo = Users::user_info($UserID);
$Perms = Permissions::get_permissions($UserInfo['PermissionID']);
$UserClass = $Perms['Class'];

if (!check_perms('users_mod') && ($LoggedUser['ID'] != $UserID && !check_paranoia(false, $User['Paranoia'], $UserClass, $UserID))) {
    error(403);
}

if (isset($_GET['expire'])) {
    if (!check_perms('users_mod')) {
        error(403);
    }
    $UserID = $_GET['userid'];
    $TorrentID = $_GET['torrentid'];

    if (!is_number($UserID) || !is_number($TorrentID)) {
        error(403);
    }
    $DB->query("
    SELECT HEX(info_hash)
    FROM torrents
    WHERE ID = {$TorrentID}");
    if ([$InfoHash] = $DB->next_record(MYSQLI_NUM, false)) {
        $DB->query("
      UPDATE users_freeleeches
      SET Expired = TRUE
      WHERE UserID = {$UserID}
        AND TorrentID = {$TorrentID}");
        $Cache->delete_value(sprintf('users_tokens_%s', $UserID));
        Tracker::update_tracker('remove_token', ['info_hash' => substr('%' . chunk_split($InfoHash, 2, '%'), 0, -1), 'userid' => $UserID]);
    }
    header(sprintf('Location: userhistory.php?action=token_history&userid=%s', $UserID));
}

View::show_header('Freeleech token history');

[$Page, $Limit] = Format::page_limit(25);

/*
$DB->query("
  SELECT
    SQL_CALC_FOUND_ROWS
    f.TorrentID,
    t.GroupID,
    f.Time,
    f.Expired,
    f.Downloaded,
    f.Uses,
    g.Name,
    t.Format,
    t.Encoding
  FROM users_freeleeches AS f
    JOIN torrents AS t ON t.ID = f.TorrentID
    JOIN torrents_group AS g ON g.ID = t.GroupID
  WHERE f.UserID = $UserID
  ORDER BY f.Time DESC
  LIMIT $Limit");
*/
$DB->query("
  SELECT
    SQL_CALC_FOUND_ROWS
    f.TorrentID,
    t.GroupID,
    f.Time,
    f.Expired,
    f.Downloaded,
    f.Uses,
    g.Name
  FROM users_freeleeches AS f
    JOIN torrents AS t ON t.ID = f.TorrentID
    JOIN torrents_group AS g ON g.ID = t.GroupID
  WHERE f.UserID = {$UserID}
  ORDER BY f.Time DESC
  LIMIT {$Limit}");
$Tokens = $DB->to_array();

$DB->query('SELECT FOUND_ROWS()');
[$NumResults] = $DB->next_record();
$Pages = Format::get_pages($Page, $NumResults, 25);

?>
<div class="header">
  <h2>Freeleech token history for <?=Users::format_username($UserID, false, false, false)?></h2>
</div>
<div class="linkbox"><?=$Pages?></div>
<table>
  <tr class="colhead_dark">
    <td>Torrent</td>
    <td>Time</td>
    <td>Expired</td>
<?php if (check_perms('users_mod')) { ?>
    <td>Downloaded</td>
    <td>Tokens used</td>
<?php } ?>
  </tr>
<?php
foreach ($Tokens as $Token) {
    $GroupIDs[] = $Token['GroupID'];
}
$Artists = Artists::get_artists($GroupIDs);

foreach ($Tokens as $Token) {
    [$TorrentID, $GroupID, $Time, $Expired, $Downloaded, $Uses, $Name] = $Token;
    if ('' != $Name) {
        $Name = sprintf('<a href="torrents.php?torrentid=%s">%s</a>', $TorrentID, $Name);
    } else {
        $Name = sprintf('(<i>Deleted torrent <a href="log.php?search=Torrent+%s">%s</a></i>)', $TorrentID, $TorrentID);
    }
    $ArtistName = Artists::display_artists($Artists[$GroupID]);
    if ($ArtistName) {
        $Name = $ArtistName . $Name;
    } ?>
  <tr class="row">
    <td><?=$Name?></td>
    <td><?=time_diff($Time)?></td>
    <td><?=($Expired ? 'Yes' : 'No')?><?=(check_perms('users_mod') && !$Expired) ? sprintf(' <a href="userhistory.php?action=token_history&amp;expire=1&amp;userid=%s&amp;torrentid=%s">(expire)</a>', $UserID, $TorrentID) : ''; ?>
    </td>
<?php  if (check_perms('users_mod')) { ?>
    <td><?=Format::get_size($Downloaded)?></td>
    <td><?=$Uses?></td>
<?php  } ?>
  </tr>
<?php
}
?>
</table>
<div class="linkbox"><?=$Pages?></div>
<?php
View::show_footer();
?>
