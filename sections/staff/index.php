<?php declare(strict_types=1);
enforce_login();
View::show_header('Staff');

include SERVER_ROOT . '/sections/staff/functions.php';

$SupportStaff = get_support();

[$FrontLineSupport, $ForumStaff, $Staff] = $SupportStaff;

?>
<div class="thin">
  <div class="header">
    <h2><?=SITE_NAME?> Staff</h2>
  </div>
  <div class="box pad" style="padding: 0px 10px 10px 10px;">
    <br />
    <h3>Contact Staff</h3>
    <div id="below_box">
      <p>If you are looking for help with a general question, we appreciate it if you would only message through the staff inbox, where we can all help you.</p>
      <p>You can do that by <strong><a data-toggle-target="#compose">sending a message to the Staff Inbox</a></strong>.</p>
    </div>
    <?php View::parse('generic/reply/staffpm.php', ['Hidden' => true]); ?>
<?php if ($FrontLineSupport) { ?>
  </div><br />
  <div class="box pad">
    <h3 id="fls">First-Line Support</h3>
    <p><strong>These users are not official staff members.</strong> They are users who have volunteered their time to help people in need. Please treat them with respect, and read <a href="wiki.php?action=article&amp;id=260">this</a> before contacting them.</p>
    <table class="staff" width="100%">
      <tr class="colhead">
        <td style="width: 130px;">Username</td>
        <td style="width: 130px;">Last seen</td>
        <td><strong>Support for</strong></td>
      </tr>
<?php
  foreach ($FrontLineSupport as $Support) {
      [$ID, $Class, $Username, $Paranoia, $LastAccess, $SupportFor] = $Support;

      make_staff_row($ID, $Paranoia, $Class, $LastAccess, $SupportFor);
  } ?>
    </table>
<?php }
if ($ForumStaff) { ?>
  </div><br />
  <div class="box pad" style="padding: 0px 10px 10px 10px;">
    <br />
    <h3 id="forum_mods">Forum Moderators</h3>
    <p>Forum Moderators are users who have been promoted to help moderate the forums. They can only help with forum-oriented questions.</p>
    <table class="staff" width="100%">
      <tr class="colhead">
        <td style="width: 130px;">Username</td>
        <td style="width: 130px;">Last seen</td>
        <td><strong>Remark</strong></td>
      </tr>
<?php
  foreach ($ForumStaff as $Support) {
      [$ID, $Class, $Username, $Paranoia, $LastAccess, $SupportFor] = $Support;

      make_staff_row($ID, $Paranoia, $Class, $LastAccess, $SupportFor);
  } ?>
    </table>
<?php
}
  $CurClass = 0;
  $CloseTable = false;
  foreach ($Staff as $StaffMember) {
      [$ID, $Class, $ClassName, $Username, $Paranoia, $LastAccess, $Remark] = $StaffMember;
      if ($Class != $CurClass) { // Start new class of staff members
          if ($CloseTable) {
              $CloseTable = false;
              // the "\t" and "\n" are used here to make the HTML look pretty
              echo "\t\t</table>\n\t\t<br />\n";
          }
          $CurClass = $Class;
          $CloseTable = true;
          $DevDiv = false;
          $AdminDiv = false;

          $HTMLID = '';
          switch ($ClassName) {
        case 'Moderator':
          printSectionDiv("Moderators");
          $HTMLID = 'mods';
          break;
        case 'Developer':
          $HTMLID = 'devs';
          break;
        case 'Lead Developer':
          $HTMLID = 'lead_devs';
          break;
        case 'System Administrator':
          $HTMLID = 'sys_admins';
          break;
        case 'Administrator':
          $HTMLID = 'admins';
          break;
        case 'Sysop':
          $HTMLID = 'sysops';
          break;
        default:
          $HTMLID = '';
      }
          switch ($ClassName) {
        case 'Developer':
        case 'Lead Developer':
          if (!$DevDiv) {
              printSectionDiv("Development");
              $DevDiv = true;
          }
          break;
        case 'System Administrator':
        case 'Administrator':
        case 'Sysop':
          if (!$AdminDiv) {
              printSectionDiv("Administration");
              $AdminDiv = true;
          }
      }
          if ('mods' != $HTMLID) {
              echo sprintf('		<h3 style="font-size: 17px;" id="%s"><i>', $HTMLID) . $ClassName . "s</i></h3>\n";
          } else {
              echo "\t\t<h2 style='text-align: left'>" . $ClassName . "s</h2>\n";
          } ?>
    <table class="staff" width="100%">
      <tr class="colhead">
        <td style="width: 130px;">Username</td>
        <td style="width: 130px;">Last seen</td>
        <td><strong>Remark</strong></td>
      </tr>
<?php
      } // End new class header

      $HiddenBy = 'Hidden by staff member';

      // Display staff members for this class
      make_staff_row($ID, $Paranoia, $Class, $LastAccess, $Remark, $HiddenBy);
  } ?>
    </table>

  </div>
</div>
<?php
View::show_footer();
?>
