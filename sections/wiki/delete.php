<?php

declare(strict_types=1);

if (!check_perms('admin_manage_wiki')) {
    error(403);
}

if (!isset($_GET['id']) || !is_number($_GET['id'])) {
    error(404);
}
$ID = (int)$_GET['id'];

if (INDEX_ARTICLE == $ID) {
    error('You cannot delete the main wiki article.');
}

$DB->query("
  SELECT Title
  FROM wiki_articles
  WHERE ID = {$ID}");

if (!$DB->has_results()) {
    error(404);
}

[$Title] = $DB->next_record(MYSQLI_NUM, false);
//Log
Misc::write_log(sprintf('Wiki article %s (%s) was deleted by ', $ID, $Title) . $LoggedUser['Username']);
//Delete
$DB->query(sprintf('DELETE FROM wiki_articles WHERE ID = %s', $ID));
$DB->query(sprintf('DELETE FROM wiki_aliases WHERE ArticleID = %s', $ID));
$DB->query(sprintf('DELETE FROM wiki_revisions WHERE ID = %s', $ID));
Wiki::flush_aliases();
Wiki::flush_article($ID);

header("location: wiki.php");
