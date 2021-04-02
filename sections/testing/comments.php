<?php

if (!check_perms('users_mod')) {
    error(404);
}

View::show_header("Tests");

?>

<div class="header">
  <h2>Documentation</h2>
  <?php TestingView::render_linkbox("comments"); ?>
</div>

<div class="thin">
  <?php TestingView::render_missing_documentation(Testing::get_classes());?>
</div>

<?php
View::show_footer();
