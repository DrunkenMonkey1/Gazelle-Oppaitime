<?php declare(strict_types=1);
if (!check_perms('site_torrents_notify')) {
    error(403);
}
View::show_header('Manage notifications', 'jquery.validate,form_validate');
?>
<div class="thin">
  <div class="header">
    <h2>Notify me of all new torrents with...</h2>
    <div class="linkbox">
      <a href="torrents.php?action=notify" class="brackets">View notifications</a>
    </div>
  </div>
<?php
$DB->query("
  SELECT
    ID,
    Label,
    Artists,
    ExcludeVA,
    NewGroupsOnly,
    Tags,
    NotTags,
    ReleaseTypes,
    Categories,
    Formats,
    Encodings,
    Media,
    FromYear,
    ToYear,
    Users
  FROM users_notify_filters
  WHERE UserID=$LoggedUser[ID]");

$NumFilters = $DB->record_count();

$Notifications = $DB->to_array();
$Notifications[] = [
    'ID' => false,
    'Label' => '',
    'Artists' => '',
    'ExcludeVA' => false,
    'NewGroupsOnly' => true,
    'Tags' => '',
    'NotTags' => '',
    'ReleaseTypes' => '',
    'Categories' => '',
    'Formats' => '',
    'Encodings' => '',
    'Media' => '',
    'FromYear' => '',
    'ToYear' => '',
    'Users' => ''
];

$i = 0;
foreach ($Notifications as $N) { // $N stands for Notifications
    ++$i;
    $NewFilter = false === $N['ID'];
    $N['Artists']      = implode(', ', explode('|', substr($N['Artists'], 1, -1)));
    $N['Tags']         = implode(', ', explode('|', substr($N['Tags'], 1, -1)));
    $N['NotTags']      = implode(', ', explode('|', substr($N['NotTags'], 1, -1)));
    $N['ReleaseTypes'] = explode('|', substr($N['ReleaseTypes'], 1, -1));
    $N['Categories']   = explode('|', substr($N['Categories'], 1, -1));
    $N['Formats']      = explode('|', substr($N['Formats'], 1, -1));
    $N['Encodings']    = explode('|', substr($N['Encodings'], 1, -1));
    $N['Media']        = explode('|', substr($N['Media'], 1, -1));
    $N['Users']        = explode('|', substr($N['Users'], 1, -1));

    $Usernames = '';
    foreach ($N['Users'] as $UserID) {
        $UserInfo = Users::user_info($UserID);
        $Usernames .= $UserInfo['Username'] . ', ';
    }
    $Usernames = rtrim($Usernames, ', ');

    if (0 == $N['FromYear']) {
        $N['FromYear'] = '';
    }
    if (0 == $N['ToYear']) {
        $N['ToYear'] = '';
    }
    if ($NewFilter && $NumFilters > 0) {
        ?>
  <br><br>
  <h3>Create a new notification filter</h3>
<?php
    } elseif ($NumFilters > 0) { ?>
  <h3>
    <a href="feeds.php?feed=torrents_notify_<?=$N['ID']?>_<?=$LoggedUser['torrent_pass']?>&amp;user=<?=$LoggedUser['ID']?>&amp;auth=<?=$LoggedUser['RSS_Auth']?>&amp;passkey=<?=$LoggedUser['torrent_pass']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;name=<?=urlencode($N['Label'])?>"><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed"></a>
    <?=display_str($N['Label'])?>
    <a href="user.php?action=notify_delete&amp;id=<?=$N['ID']?>&amp;auth=<?=$LoggedUser['AuthKey']?>" onclick="return confirm('Are you sure you want to delete this notification filter?')" class="brackets">Delete</a>
    <a data-toggle-target="#filter_<?=$N['ID']?>" class="brackets">Show</a>
  </h3>
<?php  } ?>
  <form class="box pad slight_margin <?=($NewFilter ? 'create_form' : 'edit_form')?>" id="<?=($NewFilter ? 'filter_form' : '')?>" name="notification" action="user.php" method="post">
    <input type="hidden" name="formid" value="<?=$i?>">
    <input type="hidden" name="action" value="notify_handle">
    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>">
<?php  if (!$NewFilter) { ?>
    <input type="hidden" name="id<?=$i?>" value="<?=$N['ID']?>">
<?php  } ?>
    <table <?=($NewFilter ? 'class="layout"' : 'id="filter_' . $N['ID'] . '" class="layout hidden"')?>>
<?php  if ($NewFilter) { ?>
      <tr>
        <td class="label"><strong>Notification filter name</strong></td>
        <td>
          <input type="text" class="required" name="label<?=$i?>" style="width: 100%;">
          <p class="min_padding">A name for the notification filter set to tell different filters apart.</p>
        </td>
      </tr>
      <tr>
        <td colspan="2" class="center">
          <strong>All fields below here are optional</strong>
        </td>
      </tr>
<?php  } ?>
      <tr>
        <td class="label"><strong>One of these artists</strong></td>
        <td>
          <textarea name="artists<?=$i?>" style="width: 100%;" rows="5"><?=display_str($N['Artists'])?></textarea>
          <p class="min_padding">Comma-separated list&#8202;&mdash;&#8202;e.g. <em>Yumeno Aika, Pink Pineapple, Sayama Ai</em></p>
          <input type="checkbox" name="excludeva<?=$i?>" id="excludeva_<?=$N['ID']?>"<?php if ('1' == $N['ExcludeVA']) {
        echo ' checked="checked"';
    } ?>>
          <label for="excludeva_<?=$N['ID']?>">Exclude Various Artists releases</label>
        </td>
      </tr>
      <tr>
        <td class="label"><strong>One of these users</strong></td>
        <td>
          <textarea name="users<?=$i?>" style="width: 100%;" rows="5"><?=display_str($Usernames)?></textarea>
          <p class="min_padding">Comma-separated list of usernames</p>
        </td>
      </tr>
      <tr>
        <td class="label"><strong>At least one of these tags</strong></td>
        <td>
          <textarea name="tags<?=$i?>" style="width: 100%;" rows="2"><?=display_str($N['Tags'])?></textarea>
          <p class="min_padding">Comma-separated list&#8202;&mdash;&#8202;e.g. <em>big.breasts, paizuri, nakadashi</em></p>
        </td>
      </tr>
      <tr>
        <td class="label"><strong>None of these tags</strong></td>
        <td>
          <textarea name="nottags<?=$i?>" style="width: 100%;" rows="2"><?=display_str($N['NotTags'])?></textarea>
          <p class="min_padding">Comma-separated list&#8202;&mdash;&#8202;e.g. <em>big.breasts, paizuri, nakadashi</em></p>
        </td>
      </tr>
      <tr>
        <td class="label"><strong>Only these categories</strong></td>
        <td>
<?php  foreach ($Categories as $Category) { ?>
          <input type="checkbox" name="categories<?=$i?>[]" id="<?=$Category?>_<?=$N['ID']?>" value="<?=$Category?>"<?php if (in_array($Category, $N['Categories'], true)) {
        echo ' checked="checked"';
    } ?>>
          <label for="<?=$Category?>_<?=$N['ID']?>"><?=$Category?></label>
<?php  } ?>
        </td>
      </tr>
      <tr>
        <td class="label"><strong>Only these media</strong></td>
        <td>
<?php  foreach ($Media as $Medium) { ?>
          <input type="checkbox" name="media<?=$i?>[]" id="<?=$Medium?>_<?=$N['ID']?>" value="<?=$Medium?>"<?php if (in_array($Medium, $N['Media'], true)) {
        echo ' checked="checked"';
    } ?>>
          <label for="<?=$Medium?>_<?=$N['ID']?>"><?=$Medium?></label>
<?php  } ?>
        </td>
      </tr>
      <tr>
        <td class="label"><strong>Only new releases</strong></td>
        <td>
          <input type="checkbox" name="newgroupsonly<?=$i?>" id="newgroupsonly_<?=$N['ID']?>"<?php if ('1' == $N['NewGroupsOnly']) {
        echo ' checked="checked"';
    } ?>>
<label for="newgroupsonly_<?=$N['ID']?>">Only notify for new releases, not new formats</label>
        </td>
      </tr>
      <tr>
        <td colspan="2" class="center">
          <input type="submit" value="<?=($NewFilter ? 'Create filter' : 'Update filter')?>">
        </td>
      </tr>
    </table>
  </form>
<?php
} ?>
</div>
<?php View::show_footer(); ?>
