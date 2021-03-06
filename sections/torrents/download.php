<?php

declare(strict_types=1);

if (!isset($_REQUEST['authkey']) || !isset($_REQUEST['torrent_pass'])) {
    enforce_login();
    $TorrentPass = $LoggedUser['torrent_pass'];
    $UserID    = $LoggedUser['ID'];
    $AuthKey   = $LoggedUser['AuthKey'];
} else {
    if (str_contains($_REQUEST['torrent_pass'], '_')) {
        error(404);
    }

    $UserInfo = $Cache->get_value('user_' . $_REQUEST['torrent_pass']);
    if (!is_array($UserInfo)) {
        $DB->query("
      SELECT ID, la.UserID
      FROM users_main AS m
        INNER JOIN users_info AS i ON i.UserID = m.ID
        LEFT JOIN locked_accounts AS la ON la.UserID = m.ID
      WHERE m.torrent_pass = '" . db_string($_REQUEST['torrent_pass']) . "'
        AND m.Enabled = '1'");
        $UserInfo = $DB->next_record();
        $Cache->cache_value('user_' . $_REQUEST['torrent_pass'], $UserInfo, 3600);
    }
    $UserInfo = [$UserInfo];
    [$UserID, $Locked] = array_shift($UserInfo);
    if (!$UserID) {
        error(0);
    }
    $TorrentPass = $_REQUEST['torrent_pass'];
    $AuthKey = $_REQUEST['authkey'];

    if ($Locked == $UserID) {
        header('HTTP/1.1 403 Forbidden');
        die();
    }
}

$TorrentID = $_REQUEST['id'];



if (!is_number($TorrentID)) {
    error(0);
}

/* uTorrent Remote and various scripts redownload .torrent files periodically.
  To prevent this retardation from blowing bandwidth etc., let's block it
  if the .torrent file has been downloaded four times before */
$ScriptUAs = ['BTWebClient*', 'Python-urllib*', 'python-requests*'];
if (Misc::in_array_partial($_SERVER['HTTP_USER_AGENT'], $ScriptUAs)) {
    $DB->query("
    SELECT 1
    FROM users_downloads
    WHERE UserID = {$UserID}
      AND TorrentID = {$TorrentID}
    LIMIT 4");
    if (4 === $DB->record_count()) {
        error('You have already downloaded this torrent file four times. If you need to download it again, please do so from your browser.', true);
        die();
    }
}

$Info = $Cache->get_value('torrent_download_' . $TorrentID);
if (!is_array($Info) || !array_key_exists('PlainArtists', $Info) || empty($Info[10])) {
    $DB->query("
    SELECT
      t.Media,
      t.AudioFormat,
      t.Codec,
      tg.Year,
      tg.ID AS GroupID,
      tg.Name AS Name,
      tg.WikiImage,
      tg.CategoryID,
      t.Size,
      t.FreeTorrent,
      HEX(t.info_hash)
    FROM torrents AS t
      INNER JOIN torrents_group AS tg ON tg.ID = t.GroupID
    WHERE t.ID = '" . db_string($TorrentID) . "'");
    if (!$DB->has_results()) {
        error(404);
    }
    $Info = [$DB->next_record(MYSQLI_NUM, [4, 5, 6, 10])];
    $Artists = Artists::get_artist($Info[0][4], false);
    $Info['Artists'] = Artists::display_artists($Artists, false, true);
    $Info['PlainArtists'] = Artists::display_artists($Artists, false, true, false);
    $Cache->cache_value(sprintf('torrent_download_%s', $TorrentID), $Info, 0);
}
if (!is_array($Info[0])) {
    error(404);
}
[$Media, $Format, $Encoding, $Year, $GroupID, $Name, $Image, $CategoryID, $Size, $FreeTorrent, $InfoHash] = array_shift($Info); // used for generating the filename
$Artists = $Info['Artists'];

// If he's trying use a token on this, we need to make sure he has one,
// deduct it, add this to the FLs table, and update his cache key.
if ($_REQUEST['usetoken'] && '0' == $FreeTorrent) {
    if (isset($LoggedUser)) {
        $FLTokens = $LoggedUser['FLTokens'];
        if ('1' != $LoggedUser['CanLeech']) {
            error('You cannot use tokens while leech disabled.');
        }
    } else {
        $UInfo = Users::user_heavy_info($UserID);
        if ('1' != $UInfo['CanLeech']) {
            error('You may not use tokens while leech disabled.');
        }
        $FLTokens = $UInfo['FLTokens'];
    }

    // First make sure this isn't already FL, and if it is, do nothing

    if (!Torrents::has_token($TorrentID)) {
        if ($FLTokens <= 0) {
            error('You do not have any freeleech tokens left. Please use the regular DL link.');
        }
        if ($Size >= 10_737_418_240) {
            error('This torrent is too large. Please use the regular DL link.');
        }

        // Let the tracker know about this
        if (!Tracker::update_tracker('add_token', ['info_hash' => substr('%' . chunk_split($InfoHash, 2, '%'), 0, -1), 'userid' => $UserID])) {
            error('Sorry! An error occurred while trying to register your token. Most often, this is due to the tracker being down or under heavy load. Please try again later.');
        }

        if (!Torrents::has_token($TorrentID)) {
            $DB->query("
        INSERT INTO users_freeleeches (UserID, TorrentID, Time)
        VALUES ({$UserID}, {$TorrentID}, NOW())
        ON DUPLICATE KEY UPDATE
          Time = VALUES(Time),
          Expired = FALSE,
          Uses = Uses + 1");
            $DB->query("
        UPDATE users_main
        SET FLTokens = FLTokens - 1
        WHERE ID = {$UserID}");

            // Fix for downloadthemall messing with the cached token count
            $UInfo = Users::user_heavy_info($UserID);
            $FLTokens = $UInfo['FLTokens'];

            $Cache->begin_transaction(sprintf('user_info_heavy_%s', $UserID));
            $Cache->update_row(false, ['FLTokens' => ($FLTokens - 1)]);
            $Cache->commit_transaction(0);

            $Cache->delete_value(sprintf('users_tokens_%s', $UserID));
        }
    }
}

//Stupid Recent Snatches On User Page
if ('' != $Image) {
    $RecentSnatches = $Cache->get_value(sprintf('recent_snatches_%s', $UserID));
    if (!empty($RecentSnatches)) {
        $Snatch = [
            'ID' => $GroupID,
            'Name' => $Name,
            'Artist' => $Artists,
            'WikiImage' => $Image];
        if (!in_array($Snatch, $RecentSnatches, true)) {
            if (5 === count($RecentSnatches)) {
                array_pop($RecentSnatches);
            }
            array_unshift($RecentSnatches, $Snatch);
        } elseif (!is_array($RecentSnatches)) {
            $RecentSnatches = [$Snatch];
        }
        $Cache->cache_value(sprintf('recent_snatches_%s', $UserID), $RecentSnatches, 0);
    }
}

$DB->query("
  INSERT IGNORE INTO users_downloads (UserID, TorrentID, Time)
  VALUES ('{$UserID}', '{$TorrentID}', NOW())");

Torrents::set_snatch_update_time($UserID, Torrents::SNATCHED_UPDATE_AFTERDL);
$Contents = file_get_contents(TORRENT_STORE . $TorrentID . '.torrent');
$FileName = TorrentsDL::construct_file_name($Info['PlainArtists'], $Name, $Year, $Media, $Format, $Encoding, $TorrentID);

header('Content-Type: application/x-bittorrent; charset=utf-8');
header('Content-disposition: attachment; filename="' . $FileName . '"');

function add_passkey($ann)
{
    global $TorrentPass;
    return (is_array($ann)) ? array_map("add_passkey", $ann) : $ann . "/" . $TorrentPass . "/announce";
}
$UserAnnounceURL = ANNOUNCE_URLS[0][0] . "/" . $TorrentPass . "/announce";
$UserAnnounceList = (1 == count(ANNOUNCE_URLS) && 1 == count(ANNOUNCE_URLS[0])) ? [] : array_map("add_passkey", ANNOUNCE_URLS);

echo TorrentsDL::get_file($Contents, $UserAnnounceURL, $UserAnnounceList);

define('SKIP_NO_CACHE_HEADERS', 1);
