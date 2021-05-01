<?php declare(strict_types=1);
$DB->query(sprintf('SELECT OwnerID FROM slaves WHERE UserID = %s', $UserID));
if ($DB->has_results()) {
    [$Owner] = $DB->next_record();
}

$UserLevel = Slaves::get_level($UserID);

$DB->query(sprintf('SELECT UserID FROM slaves WHERE OwnerID = %s', $UserID));
$Slaves = $DB->collect('UserID');

if (isset($_POST['release'])) {
    if (in_array($_POST['release'], $Slaves, true)) {
        $DB->query("
      DELETE FROM slaves
      WHERE UserID = " . db_string($_POST['release']) . "
      AND OwnerID = '{$UserID}'");
    }
    $Slaves = array_diff($Slaves, [$_POST['release']]);
}

foreach ($Slaves as $i => $Slave) {
    $Level = slaves::get_level($Slave);
    $Slaves[$i] = ['ID' => $Slave, 'Level' => $Level];
}

View::show_header('Slaves');
?>
<div class="thin">
  <h2>Slavery</h2>
  <div class="box pad">
<?php  if (isset($Owner)) { ?>
    <h3>You are owned by <?=Users::format_username($Owner, false, true, true)?></h3>
<?php  } else { ?>
    <h3>You are free</h3>
<?php  } ?>
  </div>
<?php  if (0 == count($Slaves)) { ?>
  <h3>You have no slaves</h3>
<?php  } else { ?>
  <h2>Your slaves</h2>
  <div class="box">
    <table>
      <tr class="colhead">
        <td>Slave</td>
        <td>Level</td>
        <td>Release</td>
      </tr>
<?php    foreach ($Slaves as $Slave) { ?>
      <tr>
        <td><?=Users::format_username($Slave['ID'], false, true, true)?></td>
        <td><?=number_format($Slave['Level'])?></td>
        <td><form method="post"><button type="submit" name="release" value=<?=$Slave['ID']?>>Release</button></form></td>
      </tr>
<?php    } ?>
    </table>
  </div>
<?php  } ?>
</div>
<?php View::show_footer(); ?>
