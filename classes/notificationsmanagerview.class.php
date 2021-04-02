<?php

class NotificationsManagerView
{
    private static $Settings;

    public static function load_js()
    {
        $JSIncludes = [
            'noty/noty.js',
            'noty/layouts/bottomRight.js',
            'noty/themes/default.js',
            'user_notifications.js'];
        foreach ($JSIncludes as $JSInclude) {
            $Path = STATIC_SERVER . "functions/$JSInclude"; ?>
  <script src="<?=$Path?>?v=<?=filemtime(SERVER_ROOT . "/$Path")?>" type="text/javascript"></script>
<?php
        }
    }

    public static function render_settings($Settings)
    {
        self::$Settings = $Settings; ?>
    <tr>
      <td class="label">
        <strong>News announcements</strong>
      </td>
      <td>
<?php        self::render_checkbox(NotificationsManager::NEWS); ?>
      </td>
    </tr>
    <tr>
      <td class="label">
        <strong>Blog announcements</strong>
      </td>
      <td>
<?php        self::render_checkbox(NotificationsManager::BLOG); ?>
      </td>
    </tr>
    <tr>
      <td class="label">
        <strong>Inbox messages</strong>
      </td>
      <td>
<?php        self::render_checkbox(NotificationsManager::INBOX, true); ?>
      </td>
    </tr>
    <tr>
      <td class="label tooltip" title="Enabling this will give you a notification when you receive a new private message from a member of the <?=SITE_NAME?> staff.">
        <strong>Staff messages</strong>
      </td>
      <td>
<?php        self::render_checkbox(NotificationsManager::STAFFPM, false, false); ?>
      </td>
    </tr>
    <tr>
      <td class="label">
        <strong>Thread subscriptions</strong>
      </td>
      <td>
<?php        self::render_checkbox(NotificationsManager::SUBSCRIPTIONS, false, false); ?>
      </td>
    </tr>
    <tr>
      <td class="label tooltip" title="Enabling this will give you a notification whenever someone quotes you in the forums.">
        <strong>Quote notifications</strong>
      </td>
      <td>
<?php        self::render_checkbox(NotificationsManager::QUOTES); ?>
      </td>
    </tr>
<?php    if (check_perms('site_torrents_notify')) { ?>
      <tr>
        <td class="label tooltip" title="Enabling this will give you a notification when the torrent notification filters you have established are triggered.">
          <strong>Torrent notifications</strong>
        </td>
        <td>
<?php          self::render_checkbox(NotificationsManager::TORRENTS, true, false); ?>
        </td>
      </tr>
<?php    } ?>

    <tr>
      <td class="label tooltip" title="Enabling this will give you a notification when a torrent is added to a collage you are subscribed to.">
        <strong>Collage subscriptions</strong>
      </td>
      <td>
<?php        self::render_checkbox(NotificationsManager::COLLAGES . false, false); ?>
      </td>
    </tr>
<?php
    }

    private static function render_checkbox($Name, $Traditional = false)
    {
        $Checked = self::$Settings[$Name];
        $PopupChecked = NotificationsManager::OPT_POPUP == $Checked || !isset($Checked) ? ' checked="checked"' : '';
        $TraditionalChecked = NotificationsManager::OPT_TRADITIONAL == $Checked ? ' checked="checked"' : ''; ?>
    <label>
      <input type="checkbox" name="notifications_<?=$Name?>_popup" id="notifications_<?=$Name?>_popup"<?=$PopupChecked?> />
      Pop-up
    </label>
<?php    if ($Traditional) { ?>
    <label>
      <input type="checkbox" name="notifications_<?=$Name?>_traditional" id="notifications_<?=$Name?>_traditional"<?=$TraditionalChecked?> />
      Traditional
    </label>
<?php    }
    }

    public static function format_traditional($Contents)
    {
        return "<a href=\"$Contents[url]\">$Contents[message]</a>";
    }
}
