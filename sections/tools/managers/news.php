<?php declare(strict_types=1);
enforce_login();
if (!check_perms('admin_manage_news')) {
    error(403);
}

View::show_header('Manage news', 'bbcode');

if ('takeeditnews' == $_GET['action']) {
    if (!check_perms('admin_manage_news')) {
        error(403);
    }
    if (is_number($_POST['newsid'])) {
        authorize();

        $DB->query("
        UPDATE news
        SET Title = '" . db_string($_POST['title']) . "', Body = '" . db_string($_POST['body']) . "'
        WHERE ID = '" . db_string($_POST['newsid']) . "'");
        $Cache->delete_value('news');
        $Cache->delete_value('feed_news');
    }
    header('Location: index.php');
} elseif ('editnews' == $_GET['action']) {
    if (is_number($_GET['id'])) {
        $NewsID = $_GET['id'];
        $DB->query("
        SELECT Title, Body
        FROM news
        WHERE ID = {$NewsID}");
        [$Title, $Body] = $DB->next_record();
    }
}
?>
<div class="thin">
  <div class="header">
    <h2><?= ('news' == $_GET['action']) ? 'Create a news post' : 'Edit news post';?></h2>
  </div>
  <form class="<?= ('news' == $_GET['action']) ? 'create_form' : 'edit_form';?>" name="news_post" action="tools.php" method="post">
    <div class="box pad">
      <input type="hidden" name="action" value="<?= ('news' == $_GET['action']) ? 'takenewnews' : 'takeeditnews';?>">
      <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>">
<?php if ('editnews' == $_GET['action']) { ?>
      <input type="hidden" name="newsid" value="<?=$NewsID; ?>">
<?php } ?>
      <h3>Title</h3>
      <input type="text" name="title" size="95"<?php if (!empty($Title)) {
    echo ' value="' . display_str($Title) . '"';
} ?>>
<!-- Why did someone add this?  <input type="datetime" name="datetime" value="<?=sqltime()?>" /> -->
      <br>
      <h3>Body</h3>
<?php    $Textarea = new TEXTAREA_PREVIEW('body', '', display_str($Body ?? ''), 95, 15, true, false); ?>
      <div class="center">
        <input type="button" value="Preview" class="hidden button_preview_<?=$Textarea->getID()?>">
        <input type="submit" value="<?= ('news' == $_GET['action']) ? 'Create news post' : 'Edit news post';?>">
      </div>
    </div>
  </form>

  <h2>News archive</h2>
<?php
$DB->query('
  SELECT
    ID,
    Title,
    Body,
    Time
  FROM news
  ORDER BY Time DESC');// LIMIT 20
while ([$NewsID, $Title, $Body, $NewsTime] = $DB->next_record()) {
    ?>
  <div class="box vertical_space news_post">
    <div class="head">
      <strong><?=display_str($Title) ?></strong> - posted <?=time_diff($NewsTime) ?>
      - <a href="tools.php?action=editnews&amp;id=<?=$NewsID?>" class="brackets">Edit</a>
      <a href="tools.php?action=deletenews&amp;id=<?=$NewsID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Delete</a>
    </div>
    <div class="pad"><?=Text::full_format($Body) ?></div>
  </div>
<?php
} ?>
</div>
<?php View::show_footer();?>
