<?php declare(strict_types=1);
//Include the header
View::show_header('Rule Index');
?>
<!-- General Rules -->
<div class="thin">
  <div class="header">
    <h3 id="general">Golden Rules</h3>
  </div>
  <div class="box pad rule_summary" style="padding: 10px 10px 10px 20px;">
<?php Rules::display_golden_rules(); ?>
  </div>
  <!-- END General Rules -->
<?php include __DIR__ . '/jump.php'; ?>
</div>
<?php
View::show_footer();
?>
