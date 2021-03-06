<?php declare(strict_types=1);
/*
 * $_REQUEST['action'] is artist, collages, requests or torrents (default torrents)
 * $_REQUEST['type'] depends on the page:
 *     collages:
 *        created = comments left on one's collages
 *        contributed = comments left on collages one contributed to
 *     requests:
 *        created = comments left on one's requests
 *        voted = comments left on requests one voted on
 *     torrents:
 *        uploaded = comments left on one's uploads
 *     If missing or invalid, this defaults to the comments one made
 */

// User ID
if (isset($_GET['id']) && is_number($_GET['id'])) {
    $UserID = (int)$_GET['id'];

    $UserInfo = Users::user_info($UserID);

    $Username = $UserInfo['Username'];
    $Self = $LoggedUser['ID'] == $UserID;
    $Perms = Permissions::get_permissions($UserInfo['PermissionID']);
    $UserClass = $Perms['Class'];
    if (!check_paranoia('torrentcomments', $UserInfo['Paranoia'], $UserClass, $UserID)) {
        error(403);
    }
} else {
    $UserID = $LoggedUser['ID'];
    $Username = $LoggedUser['Username'];
    $Self = true;
}

$PerPage = isset($LoggedUser['PostsPerPage']) ? $LoggedUser['PostsPerPage'] : POSTS_PER_PAGE;
[$Page, $Limit] = Format::page_limit($PerPage);

$Action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'torrents';
$Type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'default';

// Construct the SQL query
$Conditions = [];
$Join = [];
switch ($Action) {
  case 'artist':
    $Field1 = 'artists_group.ArtistID';
    $Field2 = 'artists_group.Name';
    $Table = 'artists_group';
    $Title = 'Artist comments left by ' . ($Self ? 'you' : $Username);
    $Header = 'Artist comments left by ' . ($Self ? 'you' : Users::format_username($UserID, false, false, false));
    $Conditions[] = sprintf('comments.AuthorID = %s', $UserID);
    break;
  case 'collages':
    $Field1 = 'collages.ID';
    $Field2 = 'collages.Name';
    $Table = 'collages';
    $Conditions[] = "collages.Deleted = '0'";
    if ('created' == $Type) {
        $Conditions[] = sprintf('collages.UserID = %s', $UserID);
        $Conditions[] = sprintf('comments.AuthorID != %s', $UserID);
        $Title = 'Comments left on collages ' . ($Self ? 'you' : $Username) . ' created';
        $Header = 'Comments left on collages ' . ($Self ? 'you' : Users::format_username($UserID, false, false, false)) . ' created';
    } elseif ('contributed' == $Type) {
        $Conditions[] = 'IF(collages.CategoryID = ' . array_search('Artists', $CollageCats, true) . ', collages_artists.ArtistID, collages_torrents.GroupID) IS NOT NULL';
        $Conditions[] = sprintf('comments.AuthorID != %s', $UserID);
        $Join[] = sprintf('LEFT JOIN collages_torrents ON collages_torrents.CollageID = collages.ID AND collages_torrents.UserID = %s', $UserID);
        $Join[] = sprintf('LEFT JOIN collages_artists ON collages_artists.CollageID = collages.ID AND collages_artists.UserID = %s', $UserID);
        $Title = 'Comments left on collages ' . ($Self ? "you've" : $Username . ' has') . ' contributed to';
        $Header = 'Comments left on collages ' . ($Self ? "you've" : Users::format_username($UserID, false, false, false) . ' has') . ' contributed to';
    } else {
        $Type = 'default';
        $Conditions[] = sprintf('comments.AuthorID = %s', $UserID);
        $Title = 'Collage comments left by ' . ($Self ? 'you' : $Username);
        $Header = 'Collage comments left by ' . ($Self ? 'you' : Users::format_username($UserID, false, false, false));
    }
    break;
  case 'requests':
    $Field1 = 'requests.ID';
    $Field2 = 'requests.Title';
    $Table = 'requests';
    if ('created' == $Type) {
        $Conditions[] = sprintf('requests.UserID = %s', $UserID);
        $Conditions[] = sprintf('comments.AuthorID != %s', $UserID);
        $Title = 'Comments left on requests ' . ($Self ? 'you' : $Username) . ' created';
        $Header = 'Comments left on requests ' . ($Self ? 'you' : Users::format_username($UserID, false, false, false)) . ' created';
    } elseif ('voted' == $Type) {
        $Conditions[] = sprintf('requests_votes.UserID = %s', $UserID);
        $Conditions[] = sprintf('comments.AuthorID != %s', $UserID);
        $Join[] = 'JOIN requests_votes ON requests_votes.RequestID = requests.ID';
        $Title = 'Comments left on requests ' . ($Self ? "you've" : $Username . ' has') . ' voted on';
        $Header = 'Comments left on requests ' . ($Self ? "you've" : Users::format_username($UserID, false, false, false) . ' has') . ' voted on';
    } else {
        $Type = 'default';
        $Conditions[] = sprintf('comments.AuthorID = %s', $UserID);
        $Title = 'Request comments left by ' . ($Self ? 'you' : $Username);
        $Header = 'Request comments left by ' . ($Self ? 'you' : Users::format_username($UserID, false, false, false));
    }
    break;
  case 'torrents':
  default:
    $Action = 'torrents';
    $Field1 = 'torrents.GroupID';
    $Field2 = "tg.Name AS Name";
    $Table = 'torrents';
    $Join[] = 'JOIN torrents_group AS tg ON torrents.GroupID = tg.ID';
    if ('uploaded' == $Type) {
        $Conditions[] = sprintf('torrents.UserID = %s', $UserID);
        $Conditions[] = 'comments.AddedTime > torrents.Time';
        $Conditions[] = sprintf('comments.AuthorID != %s', $UserID);
        $Title = 'Comments left on torrents ' . ($Self ? "you've" : $Username . ' has') . ' uploaded';
        $Header = 'Comments left on torrents ' . ($Self ? "you've" : Users::format_username($UserID, false, false, false) . ' has') . ' uploaded';
    } else {
        $Type = 'default';
        $Conditions[] = sprintf('comments.AuthorID = %s', $UserID);
        $Title = 'Torrent comments left by ' . ($Self ? 'you' : $Username);
        $Header = 'Torrent comments left by ' . ($Self ? 'you' : Users::format_username($UserID, false, false, false));
    }
    break;
}
$Join[] = sprintf('JOIN comments ON comments.Page = \'%s\' AND comments.PageID = %s', $Action, $Field1);
$Join = implode("\n\t\t", $Join);
$Conditions = implode(" AND ", $Conditions);
$Conditions = ('' !== $Conditions ? 'WHERE ' . $Conditions : '');

$SQL = "
  SELECT
    SQL_CALC_FOUND_ROWS
    comments.AuthorID,
    comments.Page,
    comments.PageID,
    {$Field2},
    comments.ID,
    comments.Body,
    comments.AddedTime,
    comments.EditedTime,
    comments.EditedUserID
  FROM {$Table}
    {$Join}
  {$Conditions}
  GROUP BY comments.ID
  ORDER BY comments.ID DESC
  LIMIT {$Limit}";

$Comments = $DB->query($SQL);
$Count = $DB->record_count();

$DB->query("SELECT FOUND_ROWS()");
[$Results] = $DB->next_record();
$Pages = Format::get_pages($Page, $Results, $PerPage, 11);

$DB->set_query_id($Comments);
if ('requests' == $Action) {
    $RequestIDs = array_flip(array_flip($DB->collect('PageID')));
    $Artists = [];
    foreach ($RequestIDs as $RequestID) {
        $Artists[$RequestID] = Requests::get_artists($RequestID);
    }
    $DB->set_query_id($Comments);
} elseif ('torrents' == $Action) {
    $GroupIDs = array_flip(array_flip($DB->collect('PageID')));
    $Artists = Artists::get_artists($GroupIDs);
    $DB->set_query_id($Comments);
}

$LinkID = ($Self ? '' : '&amp;id=' . $UserID);
$ActionLinks = [];
$TypeLinks = [];
if ('artist' != $Action) {
    $ActionLinks[] = '<a href="comments.php?action=artist' . $LinkID . '" class="brackets">Artist comments</a>';
}
if ('collages' != $Action) {
    $ActionLinks[] = '<a href="comments.php?action=collages' . $LinkID . '" class="brackets">Collections comments</a>';
}
if ('requests' != $Action) {
    $ActionLinks[] = '<a href="comments.php?action=requests' . $LinkID . '" class="brackets">Request comments</a>';
}
if ('torrents' != $Action) {
    $ActionLinks[] = '<a href="comments.php?action=torrents' . $LinkID . '" class="brackets">Torrent comments</a>';
}
switch ($Action) {
  case 'collages':
    $BaseLink = 'comments.php?action=collages' . $LinkID;
    if ('default' != $Type) {
        $TypeLinks[] = '<a href="' . $BaseLink . '" class="brackets">Display collage comments ' . ($Self ? "you've" : $Username . ' has') . ' made</a>';
    }
    if ('created' != $Type) {
        $TypeLinks[] = '<a href="' . $BaseLink . '&amp;type=created" class="brackets">Display comments left on ' . ($Self ? 'your collections' : 'collections created by ' . $Username) . '</a>';
    }
    if ('contributed' != $Type) {
        $TypeLinks[] = '<a href="' . $BaseLink . '&amp;type=contributed" class="brackets">Display comments left on collections ' . ($Self ? "you've" : $Username . ' has') . ' contributed to</a>';
    }
    break;
  case 'requests':
    $BaseLink = 'comments.php?action=requests' . $LinkID;
    if ('default' != $Type) {
        $TypeLinks[] = '<a href="' . $BaseLink . '" class="brackets">Display request comments you\'ve made</a>';
    }
    if ('created' != $Type) {
        $TypeLinks[] = '<a href="' . $BaseLink . '&amp;type=created" class="brackets">Display comments left on your requests</a>';
    }
    if ('voted' != $Type) {
        $TypeLinks[] = '<a href="' . $BaseLink . '&amp;type=voted" class="brackets">Display comments left on requests you\'ve voted on</a>';
    }
    break;
  case 'torrents':
    if ('default' != $Type) {
        $TypeLinks[] = '<a href="comments.php?action=torrents' . $LinkID . '" class="brackets">Display comments you have made</a>';
    }
    if ('uploaded' != $Type) {
        $TypeLinks[] = '<a href="comments.php?action=torrents' . $LinkID . '&amp;type=uploaded" class="brackets">Display comments left on your uploads</a>';
    }
    break;
}
$Links = implode(' ', $ActionLinks) . (count($TypeLinks) > 0 ? '<br />' . implode(' ', $TypeLinks) : '');

View::show_header($Title, 'bbcode,comments');
?><div class="thin">
  <div class="header">
    <h2><?=$Header?></h2>
<?php if ('' !== $Links) { ?>
    <div class="linkbox">
      <?=$Links?>
    </div>
<?php } ?>
  </div>
  <div class="linkbox">
    <?=$Pages?>
  </div>
<?php
if ($Count > 0) {
    $DB->set_query_id($Comments);
    while ([$AuthorID, $Page, $PageID, $Name, $PostID, $Body, $AddedTime, $EditedTime, $EditedUserID] = $DB->next_record()) {
        $Link = Comments::get_url($Page, $PageID, $PostID);
        $Header = match ($Page) {
            'artist' => sprintf(' on <a href="artist.php?id=%s">%s</a>', $PageID, $Name),
            'collages' => sprintf(' on <a href="collages.php?id=%s">%s</a>', $PageID, $Name),
            'requests' => ' on ' . Artists::display_artists($Artists[$PageID]) . sprintf(' <a href="requests.php?action=view&id=%s">%s</a>', $PageID, $Name),
            'torrents' => ' on ' . Artists::display_artists($Artists[$PageID]) . sprintf(' <a href="torrents.php?id=%s">%s</a>', $PageID, $Name),
        };
        CommentsView::render_comment($AuthorID, $PostID, $Body, $AddedTime, $EditedUserID, $EditedTime, $Link, false, $Header, false);
    }
} else { ?>
  <div class="center">No results.</div>
<?php } ?>
  <div class="linkbox">
    <?=$Pages?>
  </div>
</div>
<?php
View::show_footer();
