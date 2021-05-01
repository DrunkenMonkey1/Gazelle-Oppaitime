<?php declare(strict_types=1);
if (!check_perms('site_admin_forums')) {
    error(403);
}

if (empty($_GET['postid']) || !is_number($_GET['postid'])) {
    die();
}

$PostID = $_GET['postid'];

if (!isset($_GET['depth']) || !is_number($_GET['depth'])) {
    die();
}

$Depth = $_GET['depth'];

if (empty($_GET['type']) || !in_array($_GET['type'], ['forums', 'collages', 'requests', 'torrents', 'artist'], true)) {
    die();
}
$Type = $_GET['type'];

$Edits = $Cache->get_value($Type . '_edits_' . $PostID);
if (!is_array($Edits)) {
    $DB->query("
    SELECT EditUser, EditTime, Body
    FROM comments_edits
    WHERE Page = '{$Type}' AND PostID = {$PostID}
    ORDER BY EditTime DESC");
    $Edits = $DB->to_array();
    $Cache->cache_value($Type . '_edits_' . $PostID, $Edits, 0);
}

[$UserID, $Time] = $Edits[$Depth];
if (0 != $Depth) {
    [, , $Body] = $Edits[$Depth - 1];
} else {
    //Not an edit, have to get from the original
    switch ($Type) {
    case 'forums':
      //Get from normal forum stuffs
      $DB->query("
        SELECT Body
        FROM forums_posts
        WHERE ID = {$PostID}");
      [$Body] = $DB->next_record();
      break;
    case 'collages':
    case 'requests':
    case 'artist':
    case 'torrents':
      $DB->query("
        SELECT Body
        FROM comments
        WHERE Page = '{$Type}' AND ID = {$PostID}");
      [$Body] = $DB->next_record();
      break;
  }
}
?>
        <?=Text::full_format($Body)?>
        <br />
        <br />

<?php if ($Depth < count($Edits)) { ?>
          <a href="#edit_info_<?=$PostID?>" onclick="LoadEdit('<?=$Type?>', <?=$PostID?>, <?=($Depth + 1)?>); return false;">&laquo;</a>
          <?=((0 == $Depth) ? 'Last edited by' : 'Edited by')?>
          <?=Users::format_username($UserID, false, false, false) ?> <?=time_diff($Time, 2, true, true)?>
<?php } else { ?>
          <em>Original Post</em>
<?php }

if ($Depth > 0) { ?>
          <a href="#edit_info_<?=$PostID?>" onclick="LoadEdit('<?=$Type?>', <?=$PostID?>, <?=($Depth - 1)?>); return false;">&raquo;</a>
<?php } ?>

