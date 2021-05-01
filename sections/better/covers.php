<?php declare(strict_types=1);
if (!empty($_GET['filter']) && 'all' == $_GET['filter']) {
    $Join = '';
    $All = true;
} else {
    $Join = 'JOIN torrents AS t ON t.GroupID=tg.ID
           JOIN xbt_snatched AS x ON x.fid = t.ID AND x.uid = ' . $LoggedUser['ID'];
    $All = false;
}

View::show_header('Torrent groups with no covers');
$DB->query("
  SELECT
    SQL_CALC_FOUND_ROWS
    tg.ID
  FROM torrents_group AS tg
    {$Join}
  WHERE tg.WikiImage=''
  ORDER BY RAND()
  LIMIT 20");
$Groups = $DB->to_array('ID', MYSQLI_ASSOC);

$DB->query('SELECT FOUND_ROWS()');
[$NumResults] = $DB->next_record();

$Results = Torrents::get_groups(array_keys($Groups));

?>
<div class="header">
<?php if ($All) { ?>
  <h2>All torrent groups with no cover</h2>
<?php } else { ?>
  <h2>Torrent groups with no cover that you have snatched</h2>
<?php } ?>

  <div class="linkbox">
    <a href="better.php" class="brackets">Back to better.php list</a>
<?php if ($All) { ?>
    <a href="better.php?method=covers" class="brackets">Show only those you have snatched</a>
<?php } else { ?>
    <a href="better.php?method=covers&amp;filter=all" class="brackets">Show all</a>
<?php } ?>
  </div>
</div>
<div class="thin box pad">
  <h3>There are <?=number_format($NumResults)?> groups remaining</h3>
  <table class="torrent_table">
<?php
foreach ($Results as $Result) {
    extract($Result);
    $TorrentTags = new Tags($TagList);

    $DisplayName = sprintf('<a href="torrents.php?id=%s" class="tooltip" title="View torrent group" ', $ID);
    if (!isset($LoggedUser['CoverArt']) || $LoggedUser['CoverArt']) {
        $DisplayName .= 'data-cover="' . ImageTools::process($WikiImage, 'thumb') . '" ';
    }
    $DisplayName .= sprintf('dir="ltr">%s</a>', $Name);
    if ($Year > 0) {
        $DisplayName .= sprintf(' [%s]', $Year);
    } ?>
    <tr class="torrent">
      <td><div class="<?=Format::css_category($CategoryID)?>"></div></td>
      <td><?=$DisplayName?><div class="tags"><?=$TorrentTags->format()?></div></td>
    </tr>
<?php
} ?>
  </table>
</div>
<?php
View::show_footer();
?>
