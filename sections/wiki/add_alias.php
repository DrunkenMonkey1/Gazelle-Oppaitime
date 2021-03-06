<?php

declare(strict_types=1);

authorize();

if (!isset($_POST['article']) || !is_number($_POST['article'])) {
    error(0);
}

$ArticleID = (int)$_POST['article'];

$DB->query(sprintf('SELECT MinClassEdit FROM wiki_articles WHERE ID = %s', $ArticleID));
[$MinClassEdit] = $DB->next_record();
if ($MinClassEdit > $LoggedUser['EffectiveClass']) {
    error(403);
}

$NewAlias = Wiki::normalize_alias($_POST['alias']);
$Dupe = Wiki::alias_to_id($_POST['alias']);

if ('' != $NewAlias && 'addalias' != $NewAlias && false === $Dupe) { //Not null, and not dupe
    $DB->query(sprintf('INSERT INTO wiki_aliases (Alias, UserID, ArticleID) VALUES (\'%s\', \'%s\', \'%s\')', $NewAlias, $LoggedUser[ID], $ArticleID));
} else {
    error('The alias you attempted to add was either null or already in the database.');
}

Wiki::flush_aliases();
Wiki::flush_article($ArticleID);
header('Location: wiki.php?action=article&id=' . $ArticleID);
