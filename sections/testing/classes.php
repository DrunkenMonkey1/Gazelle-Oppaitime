<?php declare(strict_types=1);
if (!check_perms('users_mod')) {
    error(404);
}

View::show_header("Tests");

?>
<div class="header">
  <h2>Tests</h2>
  <?php TestingView::render_linkbox("classes"); ?>
</div>

<div class="thin">
  <?php TestingView::render_classes(Testing::get_classes());?>
</div>

<?php
View::show_footer();
