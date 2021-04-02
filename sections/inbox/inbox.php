<?php



$UserID = $LoggedUser['ID'];


if (empty($_GET['action'])) {
    $Section = 'inbox';
} else {
    $Section = $_GET['action']; // either 'inbox' or 'sentbox'
}
if (!in_array($Section, ['inbox', 'sentbox'], true)) {
    error(404);
}

[$Page, $Limit] = Format::page_limit(MESSAGES_PER_PAGE);

View::show_header('Inbox');
?>
<div class="thin">
  <h2><?=('sentbox' === $Section ? 'Sentbox' : 'Inbox')?></h2>
  <div class="linkbox">
<?php
if ('inbox' === $Section) { ?>
    <a href="<?=Inbox::get_inbox_link('sentbox'); ?>" class="brackets">Sentbox</a>
<?php } elseif ('sentbox' === $Section) { ?>
    <a href="<?=Inbox::get_inbox_link(); ?>" class="brackets">Inbox</a>
<?php }

?>
    <br /><br />
<?php

$Sort = empty($_GET['sort']) || 'unread' !== $_GET['sort'] ? 'Date DESC' : "cu.Unread = '1' DESC, DATE DESC";

$sql = "
  SELECT
    SQL_CALC_FOUND_ROWS
    c.ID,
    c.Subject,
    cu.Unread,
    cu.Sticky,
    cu.ForwardedTo,
    cu2.UserID,";
$sql .= 'sentbox' === $Section ? ' cu.SentDate ' : ' cu.ReceivedDate ';
$sql .= "AS Date
  FROM pm_conversations AS c
    LEFT JOIN pm_conversations_users AS cu ON cu.ConvID = c.ID AND cu.UserID = '$UserID'
    LEFT JOIN pm_conversations_users AS cu2 ON cu2.ConvID = c.ID AND cu2.UserID != '$UserID' AND cu2.ForwardedTo = 0
    LEFT JOIN users_main AS um ON um.ID = cu2.UserID";

if (!empty($_GET['search']) && 'message' === $_GET['searchtype']) {
    $sql .= ' JOIN pm_messages AS m ON c.ID = m.ConvID';
}
$sql .= ' WHERE ';
if (!empty($_GET['search'])) {
    $Search = db_string($_GET['search']);
    if ('user' === $_GET['searchtype']) {
        $sql .= "um.Username LIKE '$Search' AND ";
    } elseif ('subject' === $_GET['searchtype']) {
        $Words = explode(' ', $Search);
        $sql .= "c.Subject LIKE '%" . implode("%' AND c.Subject LIKE '%", $Words) . "%' AND ";
    } elseif ('message' === $_GET['searchtype']) {
        $Words = explode(' ', $Search);
        $sql .= "m.Body LIKE '%" . implode("%' AND m.Body LIKE '%", $Words) . "%' AND ";
    }
}
$sql .= 'sentbox' === $Section ? ' cu.InSentbox' : ' cu.InInbox';
$sql .= " = '1'";

$sql .= "
  GROUP BY c.ID
  ORDER BY cu.Sticky, $Sort
  LIMIT $Limit";
$Results = $DB->query($sql);
$DB->query('SELECT FOUND_ROWS()');
[$NumResults] = $DB->next_record();
$DB->set_query_id($Results);
$Count = $DB->record_count();

$Pages = Format::get_pages($Page, $NumResults, MESSAGES_PER_PAGE, 9);
echo "\t\t$Pages\n";
?>
  </div>

  <div class="box pad">
<?php if (0 == $Count && empty($_GET['search'])) { ?>
  <h2>Your <?=('sentbox' === $Section ? 'sentbox' : 'inbox')?> is empty.</h2>
<?php } else { ?>
    <form class="search_form" name="<?=('sentbox' === $Section ? 'sentbox' : 'inbox')?>" action="inbox.php" method="get" id="searchbox">
      <div>
        <input type="hidden" name="action" value="<?=$Section?>" />
        <input type="radio" name="searchtype" value="user"<?=(empty($_GET['searchtype']) || 'user' === $_GET['searchtype'] ? ' checked="checked"' : '')?> /> User
        <input type="radio" name="searchtype" value="subject"<?=(!empty($_GET['searchtype']) && 'subject' === $_GET['searchtype'] ? ' checked="checked"' : '')?> /> Subject
        <input type="radio" name="searchtype" value="message"<?=(!empty($_GET['searchtype']) && 'message' === $_GET['searchtype'] ? ' checked="checked"' : '')?> /> Message
        <span class="float_right">
<?php      // provide a temporary toggle for sorting PMs
    $ToggleTitle = 'Temporary toggle switch for sorting PMs. To permanently change the sorting behavior, edit the setting in your profile.';
    $BaseURL = 'inbox.php';

    if (isset($_GET['sort']) && 'unread' === $_GET['sort']) { ?>
          <a href="<?=$BaseURL?>" class="brackets tooltip" title="<?=$ToggleTitle?>">List latest first</a>
<?php    } else { ?>
          <a href="<?=$BaseURL?>?sort=unread" class="brackets tooltip" title="<?=$ToggleTitle?>">List unread first</a>
<?php    } ?>
        </span>
        <br />
        <input type="search" name="search" placeholder="<?=(!empty($_GET['search']) ? display_str($_GET['search']) : 'Search ' . ('sentbox' === $Section ? 'sentbox' : 'inbox'))?>" />
      </div>
    </form>
    <form class="manage_form" name="messages" action="inbox.php" method="post" id="messageform">
      <input type="hidden" name="action" value="masschange" />
      <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
      <input type="submit" name="read" value="Mark as read" />
      <input type="submit" name="unread" value="Mark as unread" />
      <input type="submit" name="delete" value="Delete message(s)" />

      <table class="message_table checkboxes">
        <tr class="colhead">
          <td width="10"><input type="checkbox" onclick="toggleChecks('messageform', this);" /></td>
          <td width="50%">Subject</td>
          <td><?=('sentbox' === $Section ? 'Receiver' : 'Sender')?></td>
          <td>Date</td>
<?php    if (check_perms('users_mod')) { ?>
          <td>Forwarded to</td>
<?php    } ?>
        </tr>
<?php
  if (0 == $Count) { ?>
        <tr class="a">
          <td colspan="5">No results.</td>
        </tr>
<?php  } else {
      while ([$ConvID, $Subject, $Unread, $Sticky, $ForwardedID, $SenderID, $Date] = $DB->next_record()) {
          if ('1' === $Unread) {
              $RowClass = 'unreadpm';
          } else {
              $RowClass = "row";
          } ?>
        <tr class="<?=$RowClass?>">
          <td class="center"><input type="checkbox" name="messages[]=" value="<?=$ConvID?>" /></td>
          <td>
<?php
      echo "\t\t\t\t\t\t"; // for proper indentation of HTML
          if ($Unread) {
              echo '<strong>';
          }
          if ($Sticky) {
              echo 'Sticky: ';
          }
          echo "\n"; ?>
            <a href="inbox.php?action=viewconv&amp;id=<?=$ConvID?>"><?=$Subject?></a>
<?php
      echo "\t\t\t\t\t\t"; // for proper indentation of HTML
          if ($Unread) {
              echo "</strong>\n";
          } ?>
          </td>
          <td><?=Users::format_username($SenderID, true, true, true, true)?></td>
          <td><?=time_diff($Date)?></td>
<?php      if (check_perms('users_mod')) { ?>
          <td><?=(($ForwardedID && $ForwardedID != $LoggedUser['ID']) ? Users::format_username($ForwardedID, false, false, false) : '')?></td>
<?php      } ?>
        </tr>
<?php
    $DB->set_query_id($Results);
      }
  } ?>
      </table>
      <input type="submit" name="read" value="Mark as read" />
      <input type="submit" name="unread" value="Mark as unread" />
      <input type="submit" name="delete" value="Delete message(s)" />
    </form>
<?php } ?>
  </div>
  <div class="linkbox">
<?php echo "\t\t$Pages\n"; ?>
  </div>
</div>
<?php
View::show_footer();
?>
