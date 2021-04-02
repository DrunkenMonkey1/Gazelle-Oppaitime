<?php

include SERVER_ROOT . '/classes/feed.class.php'; // RSS feeds

authorize();

if (!Bookmarks::can_bookmark($_GET['type'])) {
    error(404);
}
$Feed = new FEED();

$Type = $_GET['type'];

[$Table, $Col] = Bookmarks::bookmark_schema($Type);

if (!is_number($_GET['id'])) {
    error(0);
}
$PageID = $_GET['id'];

$DB->query("
  SELECT UserID
  FROM $Table
  WHERE UserID = '$LoggedUser[ID]'
    AND $Col = $PageID");
if (!$DB->has_results()) {
    if ('torrent' === $Type) {
        $DB->query("
      SELECT MAX(Sort)
      FROM `bookmarks_torrents`
      WHERE UserID = $LoggedUser[ID]");
        [$Sort] = $DB->next_record();
        if (!$Sort) {
            $Sort = 0;
        }
        $Sort += 1;
        $DB->query("
      INSERT IGNORE INTO $Table (UserID, $Col, Time, Sort)
      VALUES ('$LoggedUser[ID]', $PageID, NOW(), $Sort)");
    } else {
        $DB->query("
      INSERT IGNORE INTO $Table (UserID, $Col, Time)
      VALUES ('$LoggedUser[ID]', $PageID, NOW())");
    }
    $Cache->delete_value('bookmarks_' . $Type . '_' . $LoggedUser['ID']);
    if ('torrent' == $Type) {
        $Cache->delete_value("bookmarks_group_ids_$UserID");

        $DB->query("
      SELECT Name, Year, WikiBody, TagList
      FROM torrents_group
      WHERE ID = $PageID");
        [$GroupTitle, $Year, $Body, $TagList] = $DB->next_record();
        $TagList = str_replace('_', '.', $TagList);

        /*
            $DB->query("
              SELECT ID, Format, Encoding, HasLog, HasCue, LogScore, Media, Scene, FreeTorrent, UserID
              FROM torrents
              WHERE GroupID = $PageID");
        */
        $DB->query("
      SELECT ID, Media, FreeTorrent, UserID
      FROM torrents
      WHERE GroupID = $PageID");
        // RSS feed stuff
        while ($Torrent = $DB->next_record()) {
            $Title = $GroupTitle;
            [$TorrentID, $Media, $Freeleech, $UploaderID] = $Torrent;
            $Title .= " [$Year] - ";
            $Title .= "$Format / $Bitrate";
            if ("'1'" == $HasLog) {
                $Title .= ' / Log';
            }
            if ($HasLog) {
                $Title .= " / $LogScore%";
            }
            if ("'1'" == $HasCue) {
                $Title .= ' / Cue';
            }
            $Title .= ' / ' . trim($Media);
            if ('1' == $Scene) {
                $Title .= ' / Scene';
            }
            if ('1' == $Freeleech) {
                $Title .= ' / Freeleech!';
            }
            if ('2' == $Freeleech) {
                $Title .= ' / Neutral leech!';
            }

            $UploaderInfo = Users::user_info($UploaderID);
            $Item = $Feed->item(
                $Title,
                Text::strip_bbcode($Body),
                'torrents.php?action=download&amp;authkey=[[AUTHKEY]]&amp;torrent_pass=[[PASSKEY]]&amp;id=' . $TorrentID,
                $UploaderInfo['Username'],
                "torrents.php?id=$PageID",
                trim($TagList)
            );
            $Feed->populate('torrents_bookmarks_t_' . $LoggedUser['torrent_pass'], $Item);
        }
    } elseif ('request' == $Type) {
        $DB->query("
      SELECT UserID
      FROM $Table
      WHERE $Col = '" . db_string($PageID) . "'");
        if ($DB->record_count() < 100) {
            // Sphinx doesn't like huge MVA updates. Update sphinx_requests_delta
            // and live with the <= 1 minute delay if we have more than 100 bookmarkers
            $Bookmarkers = implode(',', $DB->collect('UserID'));
            $SphQL = new SphinxqlQuery();
            $SphQL->raw_query("UPDATE requests, requests_delta SET bookmarker = ($Bookmarkers) WHERE id = $PageID");
        } else {
            Requests::update_sphinx_requests($PageID);
        }
    }
}
