<?php
//Include the header
View::show_header('Tagging rules');
?>
<!-- General Rules -->
<div class="thin">
  <div class="header">
    <h3 id="general">Tagging rules</h3>
  </div>
  <div class="box pad rule_summary" style="padding: 10px 10px 10px 20px;">
<?php    Rules::display_site_tag_rules(false) ?>
  </div>
  <!-- END General Rules -->
<?php include 'jump.php'; ?>
</div>
<?php
View::show_footer();
?>
