<?php declare(strict_types=1);
$Purchase = "Admin status";
$UserID = $LoggedUser['ID'];

$DB->query("
  SELECT BonusPoints
  FROM users_main
  WHERE ID = {$UserID}");
if ($DB->has_results()) {
    [$Points] = $DB->next_record();

    if ($Points >= 4_294_967_296) {
        $DB->query("
      UPDATE users_main
      SET BonusPoints  = BonusPoints - 4294967296,
          PermissionID = 15
      WHERE ID = {$UserID}");
        $Worked = true;
    } else {
        $Worked = false;
        $ErrMessage = "Not enough points";
    }
}

View::show_header('Store'); ?>
<div class="thin">
  <h2 id="general">Purchase <?print $Worked?"Successful":"Failed"?></h2>
  <div class="box pad" style="padding: 10px 10px 10px 20px;">
    <p><?print $Worked?("You purchased ".$Purchase):("Error: ".$ErrMessage)?></p>
    <p><a href="/store.php">Back to Store</a></p>
  </div>
</div>
<?php View::show_footer(); ?>
