<?php

declare(strict_types=1);

if (!check_perms('site_proxy_images')) {
    img_error('forbidden');
}

$URL = isset($_GET['i']) ? htmlspecialchars_decode($_GET['i']) : null;

if (!extension_loaded('openssl') && 'S' == strtoupper($URL[4])) {
    img_error('badprotocol');
}

/*
if (!(preg_match('/^'.IMAGE_REGEX.'/is', $URL, $Matches) || preg_match('/^'.VIDEO_REGEX.'/is', $URL, $Matches))) {
  img_error('invalid');
}
*/

if (isset($_GET['c'])) {
    [$Data, $FileType] = $Cache->get_value('image_cache_' . md5($URL));
    $Cached = true;
}
if (!isset($Data) || !$Data) {
    $Cached = false;
    $Data = @file_get_contents($URL, 0, stream_context_create(['http' => ['timeout' => 15], 'ssl' => ['verify_peer' => false]]));
    if (!$Data || empty($Data)) {
        img_error('timeout');
    }
    $FileType = image_type($Data);
    if ($FileType && function_exists(sprintf('imagecreatefrom%s', $FileType))) {
        $Image = imagecreatefromstring($Data);
        if (invisible($Image)) {
            img_error('invisible');
        }
        if (verysmall($Image)) {
            img_error('small');
        }
    }

    if (isset($_GET['c']) && strlen($Data) < 524288 && '<' != substr($Data, 0, 1)) {
        $Cache->cache_value('image_cache_' . md5($URL), [$Data, $FileType], 3600 * 24 * 7);
    }
}
// Reset avatar, add mod note
function reset_image($UserID, $Type, $AdminComment, $PrivMessage): void
{
    if ('avatar' === $Type) {
        $CacheKey = sprintf('user_info_%s', $UserID);
        $DBTable = 'users_info';
        $DBColumn = 'Avatar';
        $PMSubject = 'Your avatar has been automatically reset';
    } elseif ('avatar2' === $Type) {
        $CacheKey = sprintf('donor_info_%s', $UserID);
        $DBTable = 'donor_rewards';
        $DBColumn = 'SecondAvatar';
        $PMSubject = 'Your second avatar has been automatically reset';
    } elseif ('donoricon' === $Type) {
        $CacheKey = sprintf('donor_info_%s', $UserID);
        $DBTable = 'donor_rewards';
        $DBColumn = 'CustomIcon';
        $PMSubject = 'Your donor icon has been automatically reset';
    }

    $UserInfo = G::$Cache->get_value($CacheKey, true);
    if (false !== $UserInfo) {
        if ('' === $UserInfo[$DBColumn]) {
            // This image has already been reset
            return;
        }
        $UserInfo[$DBColumn] = '';
        G::$Cache->cache_value($CacheKey, $UserInfo, 2_592_000); // cache for 30 days
    }

    // reset the avatar or donor icon URL
    G::$DB->query("
    UPDATE {$DBTable}
    SET {$DBColumn} = ''
    WHERE UserID = '{$UserID}'");

    // write comment to staff notes
    G::$DB->query("
    UPDATE users_info
    SET AdminComment = CONCAT('" . sqltime() . ' - ' . db_string($AdminComment) . "\n\n', AdminComment)
    WHERE UserID = '{$UserID}'");

    // clear cache keys
    G::$Cache->delete_value($CacheKey);

    Misc::send_pm($UserID, 0, $PMSubject, $PrivMessage);
}

// Enforce avatar rules
if (isset($_GET['type']) && isset($_GET['userid'])) {
    $ValidTypes = ['avatar', 'avatar2', 'donoricon'];
    if (!is_number($_GET['userid']) || !in_array($_GET['type'], $ValidTypes, true)) {
        die();
    }
    $UserID = $_GET['userid'];
    $Type = $_GET['type'];

    if ('avatar' === $Type || 'avatar2' === $Type) {
        $MaxFileSize = 512 * 1024; // 512 kiB
    $MaxImageHeight = 600; // pixels
    $TypeName = 'avatar' === $Type ? 'avatar' : 'second avatar';
    } elseif ('donoricon' === $Type) {
        $MaxFileSize = 128 * 1024; // 128 kiB
    $MaxImageHeight = 100; // pixels
    $TypeName = 'donor icon';
    }

    $Height = image_height($FileType, $Data);
    if (strlen($Data) > $MaxFileSize || $Height > $MaxImageHeight) {
        // Sometimes the cached image we have isn't the actual image
        if ($Cached) {
            $Data2 = file_get_contents($URL, 0, stream_context_create(['http' => ['timeout' => 60], 'ssl' => ['verify_peer' => false]]));
        } else {
            $Data2 = $Data;
        }

        if ((strlen($Data2) > $MaxFileSize || image_height($FileType, $Data2) > $MaxImageHeight) && 1 != $UserID && 2 != $UserID) {
            require_once SERVER_ROOT . '/classes/mysql.class.php';
            require_once SERVER_ROOT . '/classes/time.class.php';
            $DBURL = db_string($URL);
            $AdminComment = ucfirst($TypeName) . " reset automatically (Size: " . number_format((strlen($Data)) / 1024) . " kB, Height: " . $Height . sprintf('px). Used to be %s', $DBURL);
            $PrivMessage = SITE_NAME . " has the following requirements for {$TypeName}s:\n\n" .
        "[b]" . ucfirst($TypeName) . "s must not exceed " . ($MaxFileSize / 1024) . " kB or be vertically longer than {$MaxImageHeight}px.[/b]\n\n" .
        sprintf('Your %s at %s has been found to exceed these rules. As such, it has been automatically reset. You are welcome to reinstate your %s once it has been resized down to an acceptable size.', $TypeName, $DBURL, $TypeName);
            reset_image($UserID, $Type, $AdminComment, $PrivMessage);
        }
    }
}

if (!isset($FileType)) {
    img_error('timeout');
}

if ('webm' == $FileType) {
    header(sprintf('Content-type: video/%s', $FileType));
} else {
    header(sprintf('Content-type: image/%s', $FileType));
}
echo $Data;
