<?php

View::show_header('Staff Inbox');

$View = (isset($_GET['view'])) ? display_str($_GET['view']) : '';
$UserLevel = $LoggedUser['EffectiveClass'];

$LevelCap = 1000;

// Setup for current view mode
$SortStr = 'IF(AssignedToUser = ' . $LoggedUser['ID'] . ', 0, 1) ASC, ';
switch ($View) {
  case 'unanswered':
    $ViewString = 'Unanswered';
    $Status = "Unanswered";
    break;
  case 'open':
    $ViewString = 'Unresolved';
    $Status = "Open', 'Unanswered";
    $SortStr = '';
    break;
  case 'resolved':
    $ViewString = 'Resolved';
    $Status = "Resolved";
    $SortStr = '';
    break;
  case 'my':
    $ViewString = 'Your Unanswered';
    $Status = "Unanswered";
    break;
  default:
    $Status = "Unanswered";
    if ($UserLevel >= $Classes[MOD]['Level'] || $UserLevel == $Classes[FORUM_MOD]['Level']) {
        $ViewString = 'Your Unanswered';
    } else {
        // FLS
        $ViewString = 'Unanswered';
    }
    break;
}

$WhereCondition = "
  WHERE (
    LEAST($LevelCap, spc.Level) <= $UserLevel
    OR spc.AssignedToUser = '" . $LoggedUser['ID'] . "')
  AND spc.Status IN ('$Status')";

if ('Your Unanswered' == $ViewString) {
    if ($UserLevel >= $Classes[MOD]['Level']) {
        $WhereCondition .= " AND spc.Level >= " . $Classes[MOD]['Level'];
    } elseif ($UserLevel == $Classes[FORUM_MOD]['Level']) {
        $WhereCondition .= " AND spc.Level >= " . $Classes[FORUM_MOD]['Level'];
    }
}

[$Page, $Limit] = Format::page_limit(MESSAGES_PER_PAGE);
// Get messages
$StaffPMs = $DB->query("
  SELECT
    SQL_CALC_FOUND_ROWS
    spc.ID,
    spc.Subject,
    spc.UserID,
    spc.Status,
    spc.Level,
    spc.AssignedToUser,
    spc.Date,
    spc.Unread,
    COUNT(spm.ID) AS NumReplies,
    spc.ResolverID
  FROM staff_pm_conversations AS spc
  JOIN staff_pm_messages spm ON spm.ConvID = spc.ID
  $WhereCondition
  GROUP BY spc.ID
  ORDER BY $SortStr Level DESC, Date DESC
  LIMIT $Limit
");

$DB->query('SELECT FOUND_ROWS()');
[$NumResults] = $DB->next_record();
$DB->set_query_id($StaffPMs);

$CurURL = Format::get_url();
if (empty($CurURL)) {
    $CurURL = 'staffpm.php?';
} else {
    $CurURL = "staffpm.php?$CurURL&";
}
$Pages = Format::get_pages($Page, $NumResults, MESSAGES_PER_PAGE, 9);

// Start page
?>
<div class="thin">
  <div class="header">
    <h2><?=$ViewString?> Staff PMs</h2>
    <div class="linkbox">
<?php  if ($IsStaff) { ?>
      <a href="staffpm.php" class="brackets">View your unanswered</a>
<?php  } ?>
      <a href="staffpm.php?view=unanswered" class="brackets">View all unanswered</a>
      <a href="staffpm.php?view=open" class="brackets">View unresolved</a>
      <a href="staffpm.php?view=resolved" class="brackets">View resolved</a>
<?php  if ($IsStaff) { ?>
      <a href="staffpm.php?action=scoreboard" class="brackets">View scoreboard</a>
<?php  } ?>
    </div>
  </div>
  <br />
  <br />
  <div class="linkbox">
    <?=$Pages?>
  </div>
  <div class="box pad" id="inbox">
<?php

if (!$DB->has_results()) {
    // No messages
?>
    <h2>No messages</h2>
<?php
} else {
    // Messages, draw table
    if ('Resolved' != $ViewString && $IsStaff) {
        // Open multiresolve form
?>
    <form class="manage_form" name="staff_messages" method="post" action="staffpm.php" id="messageform">
      <input type="hidden" name="action" value="multiresolve" />
      <input type="hidden" name="view" value="<?=strtolower($View)?>" />
<?php
    }

    // Table head?>
      <table class="message_table<?=('Resolved' != $ViewString && $IsStaff) ? ' checkboxes' : '' ?>">
        <tr class="colhead">
<?php  if ('Resolved' != $ViewString && $IsStaff) { ?>
          <td width="10"><input type="checkbox" onclick="toggleChecks('messageform', this);" /></td>
<?php  } ?>
          <td>Subject</td>
          <td>Sender</td>
          <td>Date</td>
          <td>Assigned to</td>
          <td>Replies</td>
<?php  if ('Resolved' == $ViewString) { ?>
          <td>Resolved by</td>
<?php  } ?>
        </tr>
<?php

  // List messages
  while ([$ID, $Subject, $UserID, $Status, $Level, $AssignedToUser, $Date, $Unread, $NumReplies, $ResolverID] = $DB->next_record()) {

    //$UserInfo = Users::user_info($UserID);
      $UserStr = Users::format_username($UserID, true, true, true, true);

      // Get assigned
      if ('' == $AssignedToUser) {
          // Assigned to class
          $Assigned = (0 == $Level) ? 'First Line Support' : $ClassLevels[$Level]['Name'];
          // No + on Sysops
          if ('Sysop' != $Assigned) {
              $Assigned .= '+';
          }
      } else {
          // Assigned to user
          // $UserInfo = Users::user_info($AssignedToUser);
          $Assigned = Users::format_username($AssignedToUser, true, true, true, true);
      }

      // Get resolver
      if ('Resolved' == $ViewString) {
          //$UserInfo = Users::user_info($ResolverID);
          $ResolverStr = Users::format_username($ResolverID, true, true, true, true);
      }

      // Table row?>
        <tr class="row">
<?php    if ('Resolved' != $ViewString && $IsStaff) { ?>
          <td class="center"><input type="checkbox" name="id[]" value="<?=$ID?>" /></td>
<?php    } ?>
          <td><a href="staffpm.php?action=viewconv&amp;id=<?=$ID?>"><?=display_str($Subject)?></a></td>
          <td><?=$UserStr?></td>
          <td><?=time_diff($Date, 2, true)?></td>
          <td><?=$Assigned?></td>
          <td><?=$NumReplies - 1?></td>
<?php    if ('Resolved' == $ViewString) { ?>
          <td><?=$ResolverStr?></td>
<?php    } ?>
        </tr>
<?php

    $DB->set_query_id($StaffPMs);
  } //while

    // Close table and multiresolve form?>
      </table>
<?php  if ('Resolved' != $ViewString && $IsStaff) { ?>
      <div class="submit_div">
        <input type="submit" value="Resolve selected" />
      </div>
    </form>
<?php
  }
} //if (!$DB->has_results())
?>
  </div>
  <div class="linkbox">
    <?=$Pages?>
  </div>
</div>
<?php

View::show_footer();

?>
