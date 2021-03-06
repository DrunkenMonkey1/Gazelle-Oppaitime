<?php declare(strict_types=1);
if (!isset($_GET['groupid']) || !is_number($_GET['groupid'])) {
    error(0);
}
$GroupID = (int)$_GET['groupid'];

$DB->query("
  SELECT Name
  FROM torrents_group
  WHERE ID = {$GroupID}");
if (!$DB->has_results()) {
    error(404);
}
[$Name] = $DB->next_record();

View::show_header(sprintf('Revision history for %s', $Name));
?>
<div class="thin">
  <div class="header">
    <h2>Revision history for <a href="torrents.php?id=<?=$GroupID?>"><?=$Name?></a></h2>
  </div>
<?php
RevisionHistoryView::render_revision_history(RevisionHistory::get_revision_history('torrents', $GroupID), sprintf('torrents.php?id=%s', $GroupID));
?>
</div>
<?php
View::show_footer();
