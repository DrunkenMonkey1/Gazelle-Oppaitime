<?php declare(strict_types=1);
enforce_login();
if (!defined('LOG_ENTRIES_PER_PAGE')) {
    define('LOG_ENTRIES_PER_PAGE', 100);
}
View::show_header("Site log");

include SERVER_ROOT . '/sections/log/sphinx.php';
?>
<div class="thin">
  <div class="header">
    <h2>Site log</h2>
  </div>
  <div class="box pad">
    <form class="search_form" name="log" action="" method="get">
      <table cellpadding="6" cellspacing="1" border="0" class="layout" width="100%">
        <tr>
          <td class="label"><strong>Search for:</strong></td>
          <td>
            <input type="search" name="search" size="60"<?=(empty($_GET['search']) ? '' : ' value="' . display_str($_GET['search']) . '"')?> />
            &nbsp;
            <input type="submit" value="Search log" />
          </td>
        </tr>
      </table>
    </form>
  </div>

<?php  if ($TotalMatches > LOG_ENTRIES_PER_PAGE) { ?>
  <div class="linkbox">
<?php
  $Pages = Format::get_pages($Page, $TotalMatches, LOG_ENTRIES_PER_PAGE, 9);
  echo $Pages;?>
  </div>
<?php  } ?>
  <div class="box">
  <table cellpadding="6" cellspacing="1" border="0" class="log_table" id="log_table" width="100%">
    <tr class="colhead">
      <td style="width: 180px;"><strong>Time</strong></td>
      <td><strong>Message</strong></td>
    </tr>
<?php  if ($QueryStatus) { ?>
  <tr class="nobr"><td colspan="2">Search request failed (<?=$QueryError?>).</td></tr>
<?php  } elseif (!$DB->has_results()) { ?>
  <tr class="nobr"><td colspan="2">Nothing found!</td></tr>
<?php
  }
$Usernames = [];
while ([$ID, $Message, $LogTime] = $DB->next_record()) {
    $MessageParts = explode(' ', $Message);
    $Message = '';
    $Color = false;
    $Colon = false;
    for ($i = 0, $PartCount = count($MessageParts); $i < $PartCount; ++$i) {
        if (str_starts_with($MessageParts[$i], 'https://' . SITE_DOMAIN)) {
            $Offset = strlen('https://' . SITE_DOMAIN . '/');
            $MessageParts[$i] = '<a href="' . substr($MessageParts[$i], $Offset) . '">' . substr($MessageParts[$i], $Offset) . '</a>';
        }
        switch ($MessageParts[$i]) {
      case 'Torrent':
      case 'torrent':
        $TorrentID = $MessageParts[$i + 1];
        if (is_numeric($TorrentID)) {
            $Message = $Message . ' ' . $MessageParts[$i] . sprintf(' <a href="torrents.php?torrentid=%s">%s</a>', $TorrentID, $TorrentID);
            ++$i;
        } else {
            $Message = $Message . ' ' . $MessageParts[$i];
        }
        break;
      case 'Request':
        $RequestID = $MessageParts[$i + 1];
        if (is_numeric($RequestID)) {
            $Message = $Message . ' ' . $MessageParts[$i] . sprintf(' <a href="requests.php?action=view&amp;id=%s">%s</a>', $RequestID, $RequestID);
            ++$i;
        } else {
            $Message = $Message . ' ' . $MessageParts[$i];
        }
        break;
      case 'Artist':
      case 'artist':
        $ArtistID = $MessageParts[$i + 1];
        if (is_numeric($ArtistID)) {
            $Message = $Message . ' ' . $MessageParts[$i] . sprintf(' <a href="artist.php?id=%s">%s</a>', $ArtistID, $ArtistID);
            ++$i;
        } else {
            $Message = $Message . ' ' . $MessageParts[$i];
        }
        break;
      case 'group':
      case 'Group':
        $GroupID = $MessageParts[$i + 1];
        if (is_numeric($GroupID)) {
            $Message = $Message . ' ' . $MessageParts[$i] . sprintf(' <a href="torrents.php?id=%s">%s</a>', $GroupID, $GroupID);
        } else {
            $Message = $Message . ' ' . $MessageParts[$i];
        }
        ++$i;
        break;
      case 'by':
        $UserID = 0;
        $User = '';
        $URL = '';
        if ('user' == $MessageParts[$i + 1]) {
            ++$i;
            if (is_numeric($MessageParts[$i + 1])) {
                $UserID = $MessageParts[++$i];
            }
            $URL = sprintf('user %s (<a href="user.php?id=%s">', $UserID, $UserID) . substr($MessageParts[++$i], 1, -1) . '</a>)';
        } elseif (in_array($MessageParts[$i - 1], ['deleted', 'uploaded', 'edited', 'created', 'recovered'], true)) {
            $User = $MessageParts[++$i];
            if (':' == substr($User, -1)) {
                $User = substr($User, 0, -1);
                $Colon = true;
            }
            if (!isset($Usernames[$User])) {
                $DB->query("
              SELECT ID
              FROM users_main
              WHERE Username = ?", $User);
                [$UserID] = $DB->next_record();
                $Usernames[$User] = $UserID ? $UserID : '';
            } else {
                $UserID = $Usernames[$User];
            }
            $URL = $Usernames[$User] ? sprintf('<a href="user.php?id=%s">%s</a>', $UserID, $User) . ($Colon ? ':' : '') : $User;
            if (in_array($MessageParts[$i - 2], ['uploaded', 'edited'], true)) {
                $DB->query("SELECT UserID, Anonymous FROM torrents WHERE ID = ?", $MessageParts[1]);
                if ($DB->has_results()) {
                    [$UploaderID, $AnonTorrent] = $DB->next_record();
                    if ($AnonTorrent && $UploaderID == $UserID) {
                        $URL = '<em>Anonymous</em>';
                    }
                }
            }
            $DB->set_query_id($Log);
        }
        $Message = sprintf('%s by %s', $Message, $URL);
        break;
      case 'uploaded':
        if (false === $Color) {
            $Color = 'green';
        }
        $Message = $Message . ' ' . $MessageParts[$i];
        break;
      case 'deleted':
        if (false === $Color || 'green' === $Color) {
            $Color = 'red';
        }
        $Message = $Message . ' ' . $MessageParts[$i];
        break;
      case 'edited':
        if (false === $Color) {
            $Color = 'blue';
        }
        $Message = $Message . ' ' . $MessageParts[$i];
        break;
      case 'un-filled':
        if (false === $Color) {
            $Color = '';
        }
        $Message = $Message . ' ' . $MessageParts[$i];
        break;
      case 'marked':
        if (1 == $i) {
            $User = $MessageParts[$i - 1];
            if (!isset($Usernames[$User])) {
                $DB->query("
              SELECT ID
              FROM users_main
              WHERE Username = _utf8 '" . db_string($User) . "'
              COLLATE utf8_bin");
                [$UserID] = $DB->next_record();
                $Usernames[$User] = $UserID ? $UserID : '';
                $DB->set_query_id($Log);
            } else {
                $UserID = $Usernames[$User];
            }
            $URL = $Usernames[$User] ? sprintf('<a href="user.php?id=%s">%s</a>', $UserID, $User) : $User;
            $Message = $URL . " " . $MessageParts[$i];
        } else {
            $Message = $Message . ' ' . $MessageParts[$i];
        }
        break;
      case 'Collage':
        $CollageID = $MessageParts[$i + 1];
        if (is_numeric($CollageID)) {
            $Message = $Message . ' ' . $MessageParts[$i] . sprintf(' <a href="collages.php?id=%s">%s</a>', $CollageID, $CollageID);
            ++$i;
        } else {
            $Message = $Message . ' ' . $MessageParts[$i];
        }
        break;
      default:
        $Message = $Message . ' ' . $MessageParts[$i];
    }
    } ?>
    <tr class="row" id="log_<?=$ID?>">
      <td class="nobr">
        <?=time_diff($LogTime)?>
      </td>
      <td>
        <span<?php if ($Color) { ?> style="color: <?=$Color?>;"<?php } ?>><?=$Message?></span>
      </td>
    </tr>
<?php
}
?>
  </table>
  </div>
<?php if (isset($Pages)) { ?>
  <div class="linkbox">
    <?=$Pages?>
  </div>
<?php } ?>
</div>
<?php
View::show_footer(); ?>
