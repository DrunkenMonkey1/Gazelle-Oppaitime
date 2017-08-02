<?
/*
 * This is the frontend of reporting a torrent, it's what users see when
 * they visit reportsv2.php?id=xxx
 */

include(SERVER_ROOT.'/sections/torrents/functions.php');

//If we're not coming from torrents.php, check we're being returned because of an error.
if (!isset($_GET['id']) || !is_number($_GET['id'])) {
  if (!isset($Err)) {
    error(404);
  }
} else {
  $TorrentID = $_GET['id'];
  $DB->query("
    SELECT tg.CategoryID, t.GroupID, u.Username
    FROM torrents_group AS tg
      LEFT JOIN torrents AS t ON t.GroupID = tg.ID
      LEFT JOIN users_main AS u ON t.UserID = u.ID
    WHERE t.ID = " . $_GET['id']);
  list($CategoryID, $GroupID, $Username) = $DB->next_record();
  $Artists = Artists::get_artist($GroupID);
  $TorrentCache = get_group_info($GroupID, true);
  $GroupDetails = $TorrentCache[0];
  $TorrentList = $TorrentCache[1];
  // Resolve the torrentlist to the one specific torrent being reported
  foreach ($TorrentList as &$Torrent) {
  // Remove unneeded entries
  if ($Torrent['ID'] != $TorrentID)
    unset($TorrentList[$Torrent['ID']]);
  }

  // Group details
  list($WikiBody, $WikiImage, $GroupID, $GroupName, $GroupNameJP, $GroupYear,
    $GroupStudio, $GroupSeries, $GroupCatalogueNumber, $GroupCategoryID,
    $GroupDLSite, $GroupTime, $TorrentTags, $TorrentTagIDs, $TorrentTagUserIDs,
    $Screenshots, $GroupFlags) = array_values($GroupDetails);

  $DisplayName = $GroupName;
  $AltName = $GroupName; // Goes in the alt text of the image
  $Title = $GroupName; // goes in <title>
  $WikiBody = Text::full_format($WikiBody);

  //Get the artist name, group name etc.
  $Artists = Artists::get_artist($GroupID);
  if ($Artists) {
    $DisplayName = '<span dir="ltr">' . Artists::display_artists($Artists, true) . "<a href=\"torrents.php?torrentid=$TorrentID\">$DisplayName</a></span>";
    $AltName = display_str(Artists::display_artists($Artists, false)) . $AltName;
    $Title = $AltName;
  }
  if ($GroupYear > 0) {
    $DisplayName .= " [$GroupYear]";
    $AltName .= " [$GroupYear]";
    $Title .= " [$GroupYear]";
  }
/*
  if ($GroupCategoryID == 1) {
    $DisplayName .= ' [' . $ReleaseTypes[$ReleaseType] . ']';
    $AltName .= ' [' . $ReleaseTypes[$ReleaseType] . ']';
  }
*/
}

View::show_header('Report', 'reportsv2,browse,torrent,bbcode,recommend');
?>

<div class="thin">
  <div class="header">
    <h2>Report a torrent</h2>
  </div>
  <div class="header">
    <h3><?=$DisplayName?></h3>
  </div>
  <div class="thin box">
    <table class="torrent_table details<?=((isset($GroupFlags['IsSnatched']) && $GroupFlags['IsSnatched']) ? ' snatched' : '')?>" id="torrent_details">
      <tr class="colhead_dark">
        <td width="80%"><strong>Reported torrent</strong></td>
        <td><strong>Size</strong></td>
        <td class="sign snatches">
          <a><svg width="15" height="15" fill="white" class="tooltip" alt="Snatches" title="Snatches" viewBox="3 0 88 98"><path d="M20 20 A43 43,0,1,0,77 23 L90 10 L55 10 L55 45 L68 32 A30.27 30.27,0,1,1,28 29"></path></svg></a>
        </td>
        <td class="sign seeders">
          <a><svg width="11" height="15" fill="white" class="tooltip" alt="Seeders" title="Seeders"><polygon points="0,7 5.5,0 11,7 8,7 8,15 3,15 3,7"></polygon></svg></a>
        </td>
        <td class="sign leechers">
          <a><svg width="11" height="15" fill="white" class="tooltip" alt="Leechers" title="Leechers"><polygon points="0,8 5.5,15 11,8 8,8 8,0 3,0 3,8"></polygon></svg></a>
        </td>
      </tr>
      <?
      $LangName = $GroupName ? $GroupName : ($GroupNameRJ ? $GroupNameRJ : $GroupNameJP);
      build_torrents_table($Cache, $DB, $LoggedUser, $GroupID, $LangName, $GroupCategoryID, $TorrentList, $Types, $Username);
      ?>
    </table>
  </div>

  <form class="create_form" name="report" action="reportsv2.php?action=takereport" enctype="multipart/form-data" method="post" id="reportform">
    <div>
      <input type="hidden" name="submit" value="true" />
      <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
      <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
      <input type="hidden" name="categoryid" value="<?=$CategoryID?>" />
    </div>

    <h3>Report Information</h3>
    <div class="box pad">
      <table class="layout">
        <tr>
          <td class="label">Reason:</td>
          <td>
            <select id="type" name="type" class="change_report_type">
<?
        if (!empty($Types[$CategoryID])) {
          $TypeList = $Types['master'] + $Types[$CategoryID];
          $Priorities = [];
          foreach ($TypeList as $Key => $Value) {
            $Priorities[$Key] = $Value['priority'];
          }
          array_multisort($Priorities, SORT_ASC, $TypeList);
        } else {
          $TypeList = $Types['master'];
        }
        foreach ($TypeList as $Type => $Data) {
          ?>
              <option value="<?=($Type)?>"><?=($Data['title'])?></option>
<?        } ?>
            </select>
          </td>
        </tr>
      </table>
      <p>Fields that contain lists of values (for example, listing more than one track number) should be separated by a space.</p>
      <br />
      <p><strong>Following the below report type specific guidelines will help the moderators deal with your report in a timely fashion. </strong></p>
      <br />

      <div id="dynamic_form">
<?
        /*
         * THIS IS WHERE SEXY AJAX COMES IN
         * The following malarky is needed so that if you get sent back here, the fields are filled in.
         */
        ?>
        <input id="sitelink" type="hidden" name="sitelink" size="50" value="<?=(!empty($_POST['sitelink']) ? display_str($_POST['sitelink']) : '')?>" />
        <input id="image" type="hidden" name="image" size="50" value="<?=(!empty($_POST['image']) ? display_str($_POST['image']) : '')?>" />
        <input id="track" type="hidden" name="track" size="8" value="<?=(!empty($_POST['track']) ? display_str($_POST['track']) : '')?>" />
        <input id="link" type="hidden" name="link" size="50" value="<?=(!empty($_POST['link']) ? display_str($_POST['link']) : '')?>" />
        <input id="extra" type="hidden" name="extra" value="<?=(!empty($_POST['extra']) ? display_str($_POST['extra']) : '')?>" />
      </div>
    </div>
  <input type="submit" value="Submit report" />
  </form>
</div>
<?
View::show_footer();
?>
