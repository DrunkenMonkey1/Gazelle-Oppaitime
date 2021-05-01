<?php declare(strict_types=1);
if (!check_perms('torrents_edit')) {
    error(403);
}

$GroupID = $_POST['groupid'];
$OldGroupID = $GroupID;
$NewGroupID = db_string($_POST['targetgroupid']);

if (!$GroupID || !is_number($GroupID)) {
    error(404);
}
if (!$NewGroupID || !is_number($NewGroupID)) {
    error(404);
}
if ($NewGroupID == $GroupID) {
    error('Old group ID is the same as new group ID!');
}
$DB->query("
  SELECT CategoryID, Name
  FROM torrents_group
  WHERE ID = '{$NewGroupID}'");
if (!$DB->has_results()) {
    error('Target group does not exist.');
}
[$CategoryID, $NewName] = $DB->next_record();
/*
if ($Categories[$CategoryID - 1] != 'Music') {
  error('Only music groups can be merged.');
}
*/

$DB->query("
  SELECT Name
  FROM torrents_group
  WHERE ID = {$GroupID}");
[$Name] = $DB->next_record();

// Everything is legit, let's just confim they're not retarded
if (empty($_POST['confirm'])) {
    $Artists = Artists::get_artists([$GroupID, $NewGroupID]);

    View::show_header(); ?>
  <div class="center thin">
  <div class="header">
    <h2>Merge Confirm!</h2>
  </div>
  <div class="box pad">
    <form class="confirm_form" name="torrent_group" action="torrents.php" method="post">
      <input type="hidden" name="action" value="merge" />
      <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
      <input type="hidden" name="confirm" value="true" />
      <input type="hidden" name="groupid" value="<?=$GroupID?>" />
      <input type="hidden" name="targetgroupid" value="<?=$NewGroupID?>" />
      <h3>You are attempting to merge the group:</h3>
      <ul>
        <li><?= Artists::display_artists($Artists[$GroupID], true, false)?> - <a href="torrents.php?id=<?=$GroupID?>"><?=$Name?></a></li>
      </ul>
      <h3>Into the group:</h3>
      <ul>
        <li><?= Artists::display_artists($Artists[$NewGroupID], true, false)?> - <a href="torrents.php?id=<?=$NewGroupID?>"><?=$NewName?></a></li>
      </ul>
      <input type="submit" value="Confirm" />
    </form>
  </div>
  </div>
<?php
  View::show_footer();
} else {
    authorize();

    $DB->query("
    UPDATE torrents
    SET GroupID = '{$NewGroupID}'
    WHERE GroupID = '{$GroupID}'");
    $DB->query("
    UPDATE wiki_torrents
    SET PageID = '{$NewGroupID}'
    WHERE PageID = '{$GroupID}'");

    //Comments
    Comments::merge('torrents', $OldGroupID, $NewGroupID);

    //Collages
  $DB->query("
    SELECT CollageID
    FROM collages_torrents
    WHERE GroupID = '{$OldGroupID}'"); // Select all collages that contain edited group
  while ([$CollageID] = $DB->next_record()) {
      $DB->query("
      UPDATE IGNORE collages_torrents
      SET GroupID = '{$NewGroupID}'
      WHERE GroupID = '{$OldGroupID}'
        AND CollageID = '{$CollageID}'"); // Change collage group ID to new ID
      $DB->query("
      DELETE FROM collages_torrents
      WHERE GroupID = '{$OldGroupID}'
        AND CollageID = '{$CollageID}'");
      $Cache->delete_value(sprintf('collage_%s', $CollageID));
  }
    $Cache->delete_value(sprintf('torrent_collages_%s', $NewGroupID));
    $Cache->delete_value(sprintf('torrent_collages_personal_%s', $NewGroupID));

    // Requests
    $DB->query("
    SELECT ID
    FROM requests
    WHERE GroupID = '{$OldGroupID}'");
    $Requests = $DB->collect('ID');
    $DB->query("
    UPDATE requests
    SET GroupID = '{$NewGroupID}'
    WHERE GroupID = '{$OldGroupID}'");
    foreach ($Requests as $RequestID) {
        $Cache->delete_value(sprintf('request_%s', $RequestID));
    }
    $Cache->delete_value('requests_group_' . $NewGroupID);

    Torrents::delete_group($GroupID);

    Torrents::write_group_log($NewGroupID, 0, $LoggedUser['ID'], sprintf('Merged Group %s (%s) to %s (%s)', $GroupID, $Name, $NewGroupID, $NewName), 0);
    $DB->query("
    UPDATE group_log
    SET GroupID = {$NewGroupID}
    WHERE GroupID = {$GroupID}");

    $GroupID = $NewGroupID;

    $DB->query("
    SELECT ID
    FROM torrents
    WHERE GroupID = '{$OldGroupID}'");
    while ([$TorrentID] = $DB->next_record()) {
        $Cache->delete_value(sprintf('torrent_download_%s', $TorrentID));
    }
    $Cache->delete_value(sprintf('torrents_details_%s', $GroupID));
    $Cache->delete_value(sprintf('groups_artists_%s', $GroupID));
    Torrents::update_hash($GroupID);

    header("Location: torrents.php?id=" . $GroupID);
}
?>
