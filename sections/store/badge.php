<?php declare(strict_types=1);
$UserID = $LoggedUser['ID'];
$BadgeID = $_GET['badge'];

$ShopBadgeIDs = [100, 101, 102, 103, 104, 105, 106, 107];
$Prices = [100 => 5000, 101 => 10000, 102 => 25000, 103 => 50000, 104 => 100000, 105 => 250000, 106 => 500000, 107 => 1_000_000];

if (!$BadgeID) {
    $Err = 'No badge specified.';
} elseif (!in_array($BadgeID, $ShopBadgeIDs, true)) {
    $Err = 'Invalid badge ID.';
} elseif (Badges::has_badge($UserID, $BadgeID)) {
    $Err = 'You already have this badge.';
} elseif ($BadgeID != $ShopBadgeIDs[0] && !Badges::has_badge($UserID, $ShopBadgeIDs[array_search($BadgeID, $ShopBadgeIDs, true)-1])) {
    $Err = "You haven't purchased the badges before this one!";
}

if (isset($_GET['confirm']) && 1 == $_GET['confirm']) {
    if (!isset($Err)) {
        $DB->query("
      SELECT BonusPoints
      FROM users_main
      WHERE ID = {$UserID}");
        if ($DB->has_results()) {
            [$BP] =  $DB->next_record();
            $BP = (int)$BP;

            if ($BP >= $Prices[$BadgeID]) {
                if (!Badges::award_badge($UserID, $BadgeID)) {
                    $Err = 'Could not award badge, unknown error occurred.';
                } else {
                    $DB->query("
            UPDATE users_main
            SET BonusPoints = BonusPoints - " . $Prices[$BadgeID] . "
            WHERE ID = {$UserID}");

                    $DB->query("
            UPDATE users_info
            SET AdminComment = CONCAT('" . sqltime() . " - Purchased badge {$BadgeID} from store\n\n', AdminComment)
            WHERE UserID = {$UserID}");

                    $Cache->delete_value(sprintf('user_info_heavy_%s', $UserID));
                }
            } else {
                $Err = 'Not enough ' . BONUS_POINTS . '.';
            }
        }
    }

    View::show_header('Store'); ?>
<div class='thin'>
  <h2 id='general'>Purchase <?=isset($Err)?'Failed':'Successful'?></h2>
  <div class='box pad' style='padding: 10px 10px 10px 20px;'>
    <p><?=isset($Err)?'Error: ' . $Err:'You have purchased a badge'?></p>
    <p><a href='/store.php'>Back to Store</a></p>
  </div>
</div>
<?php
} else {
        View::show_header('Store'); ?>
<div class='thin'>
  <h2 id='general'>Purchase Badge?</h2>
  <div class='box pad' style='padding: 10px 10px 10px 20px;'>
    <p>Badge cost: <?=number_format($Prices[$BadgeID])?> <?=BONUS_POINTS?></p>
    <?php if (isset($Err)) { ?>
    <p>Error: <?=$Err?></p>
    <?php } else { ?>
    <form action="store.php">
      <input type="hidden" name="item" value="badge">
      <input type="hidden" name="badge" value="<?=$BadgeID?>">
      <input type="hidden" name="confirm" value="1">
      <input type="submit" value="Purchase">
    <?php } ?>
    <p><a href='/store.php'>Back to Store</a></p>
  </div>
</div>
<?php
    }
View::show_footer(); ?>
