<?php declare(strict_types=1);
/*
 * TODO: I'm not writing documentation for this page until I write this page >.>
 */
if (!check_perms('admin_reports')) {
    error(403);
}

View::show_header('Reports V2!', 'reportsv2');

?>
<div class="header">
  <h2>Search</h2>
<?php include __DIR__ . '/header.php'; ?>
</div>
<div class="thin box pad">
  On hold until someone fixes the main torrents search.
</div>
<?php
View::show_footer();
?>
