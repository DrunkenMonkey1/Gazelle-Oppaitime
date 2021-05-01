<?php declare(strict_types=1);
$Purchase = "100,000 bonus points";
$UserID = $LoggedUser['ID'];

$GiB = 1024 * 1024 * 1024;
$Cost = 130 * $GiB;

$DB->query("
  SELECT Uploaded
  FROM users_main
  WHERE ID = {$UserID}");
if ($DB->has_results()) {
    [$Upload] = $DB->next_record();

    if ($Upload >= $Cost) {
        $DB->query("
      UPDATE users_main
      SET BonusPoints = BonusPoints + 100000,
          Uploaded    = Uploaded - {$Cost}
      WHERE ID = {$UserID}");
        $DB->query("
      UPDATE users_info
      SET AdminComment = CONCAT('" . sqltime() . " - Purchased 100,000 " . BONUS_POINTS . " from the store\n\n', AdminComment)
      WHERE UserID = {$UserID}");
        $Cache->delete_value('user_info_heavy_' . $UserID);
        $Cache->delete_value('user_stats_' . $UserID);
        $Worked = true;
    } else {
        $Worked = false;
        $ErrMessage = "Not enough upload";
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
