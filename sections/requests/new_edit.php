<?php declare(strict_types=1);

/*
 * Yeah, that's right, edit and new are the same place again.
 * It makes the page uglier to read but ultimately better as the alternative means
 * maintaining 2 copies of almost identical files.
 */


$NewRequest = 'new' === $_GET['action'];

if (!$NewRequest) {
    $RequestID = $_GET['id'];
    if (!is_number($RequestID)) {
        error(404);
    }
}

$Disabled = "";

if ($NewRequest && ($LoggedUser['BytesUploaded'] < 250 * 1024 * 1024 || !check_perms('site_submit_requests'))) {
    error('You do not have enough uploaded to make a request.');
}

if (!$NewRequest && empty($ReturnEdit)) {
    $Request = Requests::get_request($RequestID);
    if (false === $Request) {
        error(404);
    }
    // Define these variables to simplify _GET['groupid'] requests later on
    $CategoryID = $Request['CategoryID'];
    $Title = $Request['Title'];
    $CatalogueNumber = $Request['CatalogueNumber'];
    $DLsiteID = $Request['DLsiteID'];
    $Image = $Request['Image'];
    $GroupID = $Request['GroupID'];
    $VoteArray = Requests::get_votes_array($RequestID);
    $VoteCount = count($VoteArray['Voters']);
    $IsFilled = !empty($Request['TorrentID']);
    $CategoryName = $Categories[$CategoryID - 1];
    $ProjectCanEdit = (check_perms('project_team') && !$IsFilled && '0' === $CategoryID);
    $CanEdit = ((!$IsFilled && $LoggedUser['ID'] === $Request['UserID'] && $VoteCount < 2) || $ProjectCanEdit || check_perms('site_moderate_requests'));
    if (!$CanEdit) {
        error(403);
    }
    $ArtistForm = Requests::get_artists($RequestID);
    $Tags = implode(', ', $Request['Tags']);
}

if ($NewRequest && !empty($_GET['artistid']) && is_number($_GET['artistid'])) {
    $DB->query("
    SELECT Name
    FROM artists_group
    WHERE artistid = " . $_GET['artistid'] . "
    LIMIT 1");
    [$ArtistName] = $DB->next_record();
    $ArtistForm = [
        1 => [['name' => trim($ArtistName)]],
    ];
} elseif ($NewRequest && !empty($_GET['groupid']) && is_number($_GET['groupid'])) {
    $ArtistForm = Artists::get_artist($_GET['groupid']);
    $DB->query("
    SELECT
      tg.Name,
      tg.Year,
      tg.Studio,
      tg.Series,
      tg.CatalogueNumber,
      tg.DLsiteID,
      tg.WikiImage,
      GROUP_CONCAT(t.Name SEPARATOR ', '),
      tg.CategoryID
    FROM torrents_group AS tg
      JOIN torrents_tags AS tt ON tt.GroupID = tg.ID
      JOIN tags AS t ON t.ID = tt.TagID
    WHERE tg.ID = " . $_GET['groupid']);
    if ([$Title, $Year, $Studio, $Series, $CatalogueNumber, $DLsiteID, $Image, $Tags, $CategoryID] = $DB->next_record()) {
        $GroupID = trim($_REQUEST['groupid']);
        $CategoryName = $Categories[$CategoryID - 1];
        $Disabled = 'readonly="readonly"';
    }
}

View::show_header(($NewRequest ? 'Create a request' : 'Edit a request'), 'bbcode,requests,form_validate');
?>
<div class="thin">
  <div class="header">
    <h2><?=($NewRequest ? 'Create a request' : 'Edit a request')?></h2>
  </div>

  <div class="box pad">
    <form action="" method="post" id="request_form" onsubmit="Calculate();">
      <div>
<?php  if (!$NewRequest) { ?>
        <input type="hidden" name="requestid" value="<?=$RequestID?>" />
<?php  } ?>
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <input type="hidden" name="action" value="<?=($NewRequest ? 'takenew' : 'takeedit')?>" />
      </div>

      <table class="layout">
        <tr>
          <td colspan="2" class="center">Please make sure your request follows <a href="rules.php?p=requests">the request rules</a>!</td>
        </tr>
<?php  if ($NewRequest || $CanEdit) { ?>
        <tr>
          <td class="label">
            Type
          </td>
          <td>
<?php if (!empty($Disabled)) { ?>
            <input type="hidden" name="type" value="<?=$CategoryName?>" />
            <select id="categories" name="type" onchange="Categories();" disabled="disabled">
<?php } else { ?>
            <select id="categories" name="type" onchange="Categories();">
<?php } ?>
<?php    foreach (Misc::display_array($Categories) as $Cat) { ?>
              <option value="<?=$Cat?>"<?=(!empty($CategoryName) && ($CategoryName === $Cat) ? ' selected="selected"' : '')?>><?=$Cat?></option>
<?php    } ?>
            </select>
          </td>
        </tr>
        <tr id="cataloguenumber_tr">
          <td class="label">Catalogue Number</td>
          <td>
            <input type="text" id="catalogue" name="cataloguenumber" size="15" value="<?=(isset($CatalogueNumber)?$CatalogueNumber:'') ?>" <?=$Disabled?>/>
<?php if (empty($Disabled)) { ?>
            ( <input type="button" autofill="jav" value="Autofill" style="font-size:0.8em;"></input> )
<?php } ?>
          </td>
        </tr>
        <tr id="artist_tr">
          <td class="label">Artist(s)</td>
          <td id="artistfields">
            <p id="vawarning" class="hidden">Please use the multiple artists feature rather than adding "Various Artists" as an artist; read <a href="wiki.php?action=article&amp;id=369">this</a> for more information.</p>
<?php
    if (!empty($ArtistForm)) {
        $First = true;
        foreach ($ArtistForm as $Artist) {
            ?>
            <input type="text" id="artist_0" name="artists[]"<?php Users::has_autocomplete_enabled('other'); ?> size="45" value="<?=display_str($Artist['name']) ?>" <?=$Disabled?>/>
            <?php if (empty($Disabled)) {
                if ($First) { ?><a class="add_artist_button brackets">+</a> <a class="remove_artist_button brackets">&minus;</a><?php }
                $First = false;
            } ?>
            <br />
<?php
        }
    } else {
        ?>            <input type="text" id="artist_0" name="artists[]"<?php Users::has_autocomplete_enabled('other'); ?> size="45" <?=$Disabled?>/>
<?php if (empty($Disabled)) { ?>
            <a class="add_artist_button brackets">+</a> <a class="remove_artist_button brackets">&minus;</a>
<?php } ?>
<?php
    }
?>
          </td>
        </tr>
        <tr>
          <td class="label">Title</td>
          <td>
            <input type="text" id="title" name="title" size="45" value="<?=(empty($Title) ? '' : $Title)?>" <?=$Disabled?>/>
          </td>
        </tr>
        <tr id="dlsiteid_tr">
          <td class="label">DLSite ID</td>
          <td>
            <input type="text" id="dlsiteid" name="dlsiteid" size="15" value="<?=isset($DLsiteID)?$DLsiteID:''?>" <?=$Disabled?>/>
          </td>
        </tr>
<?php  } ?>
<?php  if ($NewRequest || $CanEdit) { ?>
        <tr id="image_tr">
          <td class="label">Image</td>
          <td>
            <input type="text" id="image" name="image" size="45" value="<?=(empty($Image) ? '' : $Image)?>" <?=$Disabled?>/>
          </td>
        </tr>
<?php  } ?>
        <tr>
          <td class="label">Tags</td>
          <td>
<?php
  $GenreTags = $Cache->get_value('genre_tags');
  if (!$GenreTags) {
      $DB->query('
      SELECT Name
      FROM tags
      WHERE TagType = \'genre\'
      ORDER BY Name');
      $GenreTags = $DB->collect('Name');
      $Cache->cache_value('genre_tags', $GenreTags, 3600 * 6);
  }

  if (!empty($Disabled)) {
      ?>
            <select id="genre_tags" name="genre_tags" onchange="add_tag(); return false;" disabled="disabled">
<?php
  } else { ?>
            <select id="genre_tags" name="genre_tags" onchange="add_tag(); return false;" >
<?php } ?>
              <option>---</option>
<?php  foreach (Misc::display_array($GenreTags) as $Genre) { ?>
              <option value="<?=$Genre?>"><?=$Genre?></option>
<?php  } ?>
            </select>
            <input type="text" id="tags" name="tags" size="45" value="<?=(empty($Tags) ? '' : display_str($Tags))?>"<?php Users::has_autocomplete_enabled('other'); ?> <?=$Disabled?>/>
            <br />
            Tags should be comma-separated, and you should use a period (".") to separate words inside a tag&#8202;&mdash;&#8202;e.g. "<strong class="important_text_alt">big.breasts</strong>".
            <br /><br />
            There is a list of official tags to the left of the text box. Please use these tags instead of "unofficial" tags (e.g. use the official "<strong class="important_text_alt">nakadashi</strong>" tag, instead of an unofficial "<strong class="important_text">creampie</strong>" tag.).
          </td>
        </tr>
<?php  if ($NewRequest || $CanEdit) { ?>
<?php  } ?>
        <tr>
          <td class="label">Description</td>
          <td>
<?php          new TEXTAREA_PREVIEW('description', 'req_desc', $Request['Description']??''); ?>
          </td>
        </tr>
<?php  if (check_perms('site_moderate_requests')) { ?>
        <tr>
          <td class="label">Torrent group</td>
          <td>
            <?=site_url()?>torrents.php?id=<input type="text" name="groupid" value="<?=isset($GroupID)?$GroupID:''?>" size="15"/><br />
            If this request matches a torrent group <span style="font-weight: bold;">already existing</span> on the site, please indicate that here.
          </td>
        </tr>
<?php  } elseif (!empty($GroupID) && (5 != $CategoryID) && (0 != $CategoryID)) { ?>
        <tr>
          <td class="label">Torrent group</td>
          <td>
            <a href="torrents.php?id=<?=$GroupID?>"><?=site_url()?>torrents.php?id=<?=$GroupID?></a><br />
            This request <?=($NewRequest ? 'will be' : 'is')?> associated with the above torrent group.
<?php    if (!$NewRequest) { ?>
            If this is incorrect, please <a href="reports.php?action=report&amp;type=request&amp;id=<?=$RequestID?>">report this request</a> so that staff can fix it.
<?php    } ?>
            <input type="hidden" name="groupid" value="<?=$GroupID?>" />
          </td>
        </tr>
<?php  }
  if ($NewRequest) { ?>
        <tr id="voting">
          <td class="label">Bounty (MB)</td>
          <td>
            <input type="text" id="amount_box" size="8" value="<?=(empty($Bounty) ? '100' : $Bounty)?>" onchange="Calculate();" />
            <select id="unit" name="unit" onchange="Calculate();">
              <option value="mb"<?=(!empty($_POST['unit']) && 'mb' === $_POST['unit'] ? ' selected="selected"' : '') ?>>MB</option>
              <option value="gb"<?=(!empty($_POST['unit']) && 'gb' === $_POST['unit'] ? ' selected="selected"' : '') ?>>GB</option>
            </select>
            <input type="button" value="Preview" onclick="Calculate();" />
            <strong><?=($RequestTax * 100)?>% of this is deducted as tax by the system.</strong>
          </td>
        </tr>
        <tr>
          <td class="label">Post request information</td>
          <td>
            <input type="hidden" id="amount" name="amount" value="<?=(empty($Bounty) ? '100' : $Bounty)?>" />
            <input type="hidden" id="current_uploaded" value="<?=$LoggedUser['BytesUploaded']?>" />
            <input type="hidden" id="current_downloaded" value="<?=$LoggedUser['BytesDownloaded']?>" />
            Bounty after tax: <strong><span id="bounty_after_tax">90.00 MB</span></strong><br />
            If you add the entered <strong><span id="new_bounty">100.00 MB</span></strong> of bounty, your new stats will be: <br />
            Uploaded: <span id="new_uploaded"><?=Format::get_size($LoggedUser['BytesUploaded'])?></span><br />
            Ratio: <span id="new_ratio"><?=Format::get_ratio_html($LoggedUser['BytesUploaded'], $LoggedUser['BytesDownloaded'])?></span>
          </td>
        </tr>
        <tr>
          <td colspan="2" class="center">
            <input type="submit" id="button" value="Create request" disabled="disabled" />
          </td>
        </tr>
<?php  } else { ?>
        <tr>
          <td colspan="2" class="center">
            <input type="submit" id="button" value="Edit request" />
          </td>
        </tr>
<?php  } ?>
      </table>
    </form>
  </div>
</div>
<?php
View::show_footer();
?>
