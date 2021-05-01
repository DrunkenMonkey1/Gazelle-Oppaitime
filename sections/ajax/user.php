<?php

declare(strict_types=1);

if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
    json_die("failure", "bad id parameter");
}
$UserID = $_GET['id'];


$OwnProfile = $UserID == $LoggedUser['ID'];

// Always view as a normal user.
$DB->query("
  SELECT
    m.Username,
    m.Email,
    m.LastAccess,
    m.IP,
    p.Level AS Class,
    m.Uploaded,
    m.Downloaded,
    m.RequiredRatio,
    m.Enabled,
    m.Paranoia,
    m.Invites,
    m.Title,
    m.torrent_pass,
    m.can_leech,
    i.JoinDate,
    i.Info,
    i.Avatar,
    i.Donor,
    i.Warned,
    COUNT(posts.id) AS ForumPosts,
    i.Inviter,
    i.DisableInvites,
    inviter.username
  FROM users_main AS m
    JOIN users_info AS i ON i.UserID = m.ID
    LEFT JOIN permissions AS p ON p.ID = m.PermissionID
    LEFT JOIN users_main AS inviter ON i.Inviter = inviter.ID
    LEFT JOIN forums_posts AS posts ON posts.AuthorID = m.ID
  WHERE m.ID = {$UserID}
  GROUP BY AuthorID");

if (!$DB->has_results()) { // If user doesn't exist
    json_die("failure", "no such user");
}

[
    $Username,
    $Email,
    $LastAccess,
    $IP,
    $Class,
    $Uploaded,
    $Downloaded,
    $RequiredRatio,
    $Enabled,
    $Paranoia,
    $Invites,
    $CustomTitle,
    $torrent_pass,
    $DisableLeech,
    $JoinDate,
    $Info,
    $Avatar,
    $Donor,
    $Warned,
    $ForumPosts,
    $InviterID,
    $DisableInvites,
    $InviterName
] = $DB->next_record(MYSQLI_NUM, [9, 11]);

$Paranoia = unserialize($Paranoia);
if (!is_array($Paranoia)) {
    $Paranoia = [];
}
$ParanoiaLevel = 0;
foreach ($Paranoia as $P) {
    ++$ParanoiaLevel;
    if (str_contains($P, '+')) {
        ++$ParanoiaLevel;
    }
}

// Raw time is better for JSON.
//$JoinedDate = time_diff($JoinDate);
//$LastAccess = time_diff($LastAccess);

function check_paranoia_here($Setting)
{
    global $Paranoia, $Class, $UserID;
    
    return check_paranoia($Setting, $Paranoia, $Class, $UserID);
}

$Friend = false;
$DB->query("
  SELECT FriendID
  FROM friends
  WHERE UserID = '$LoggedUser[ID]'
    AND FriendID = '{$UserID}'");
if ($DB->has_results()) {
    $Friend = true;
}

if (check_paranoia_here('requestsfilled_count') || check_paranoia_here('requestsfilled_bounty')) {
    $DB->query("
    SELECT COUNT(DISTINCT r.ID), SUM(rv.Bounty)
    FROM requests AS r
      LEFT JOIN requests_votes AS rv ON r.ID = rv.RequestID
    WHERE r.FillerID = {$UserID}");
    [$RequestsFilled, $TotalBounty] = $DB->next_record();
    $DB->query("
    SELECT COUNT(RequestID), SUM(Bounty)
    FROM requests_votes
    WHERE UserID = {$UserID}");
    [$RequestsVoted, $TotalSpent] = $DB->next_record();
    
    $DB->query("
    SELECT COUNT(ID)
    FROM torrents
    WHERE UserID = '{$UserID}'");
    [$Uploads] = $DB->next_record();
} else {
    $RequestsFilled = null;
    $TotalBounty = null;
    $RequestsVoted = 0;
    $TotalSpent = 0;
}
if (check_paranoia_here('uploads+')) {
    $DB->query("
    SELECT COUNT(ID)
    FROM torrents
    WHERE UserID = '{$UserID}'");
    [$Uploads] = $DB->next_record();
} else {
    $Uploads = null;
}

if (check_paranoia_here('artistsadded')) {
    $DB->query("
    SELECT COUNT(ArtistID)
    FROM torrents_artists
    WHERE UserID = {$UserID}");
    [$ArtistsAdded] = $DB->next_record();
} else {
    $ArtistsAdded = null;
}

$UploadedRank = check_paranoia_here('uploaded') ? UserRank::get_rank('uploaded', $Uploaded) : null;
$DownloadedRank = check_paranoia_here('downloaded') ? UserRank::get_rank('downloaded', $Downloaded) : null;
$UploadsRank = check_paranoia_here('uploads+') ? UserRank::get_rank('uploads', $Uploads) : null;
$RequestRank = check_paranoia_here('requestsfilled_count') ? UserRank::get_rank('requests', $RequestsFilled) : null;
$PostRank = UserRank::get_rank('posts', $ForumPosts);
$BountyRank = check_paranoia_here('requestsvoted_bounty') ? UserRank::get_rank('bounty', $TotalSpent) : null;
$ArtistsRank = check_paranoia_here('artistsadded') ? UserRank::get_rank('artists', $ArtistsAdded) : null;

if (0 == $Downloaded) {
    $Ratio = 1;
} elseif (0 == $Uploaded) {
    $Ratio = 0.5;
} else {
    $Ratio = round($Uploaded / $Downloaded, 2);
}
if (check_paranoia_here([
    'uploaded',
    'downloaded',
    'uploads+',
    'requestsfilled_count',
    'requestsvoted_bounty',
    'artistsadded'
])) {
    $OverallRank = floor(UserRank::overall_score($UploadedRank, $DownloadedRank, $UploadsRank, $RequestRank, $PostRank,
        $BountyRank, $ArtistsRank, $Ratio));
} else {
    $OverallRank = null;
}

// Community section
if (check_paranoia_here('snatched+')) {
    $DB->query("
    SELECT COUNT(x.uid), COUNT(DISTINCT x.fid)
    FROM xbt_snatched AS x
      INNER JOIN torrents AS t ON t.ID = x.fid
    WHERE x.uid = '{$UserID}'");
    [$Snatched, $UniqueSnatched] = $DB->next_record();
}

if (check_paranoia_here('torrentcomments+')) {
    $DB->query("
    SELECT COUNT(ID)
    FROM comments
    WHERE Page = 'torrents'
      AND AuthorID = '{$UserID}'");
    [$NumComments] = $DB->next_record();
}

if (check_paranoia_here('torrentcomments+')) {
    $DB->query("
    SELECT COUNT(ID)
    FROM comments
    WHERE Page = 'artist'
      AND AuthorID = '{$UserID}'");
    [$NumArtistComments] = $DB->next_record();
}

if (check_paranoia_here('torrentcomments+')) {
    $DB->query("
    SELECT COUNT(ID)
    FROM comments
    WHERE Page = 'collages'
      AND AuthorID = '{$UserID}'");
    [$NumCollageComments] = $DB->next_record();
}

if (check_paranoia_here('torrentcomments+')) {
    $DB->query("
    SELECT COUNT(ID)
    FROM comments
    WHERE Page = 'requests'
      AND AuthorID = '{$UserID}'");
    [$NumRequestComments] = $DB->next_record();
}

if (check_paranoia_here('collages+')) {
    $DB->query("
    SELECT COUNT(ID)
    FROM collages
    WHERE Deleted = '0'
      AND UserID = '{$UserID}'");
    [$NumCollages] = $DB->next_record();
}

if (check_paranoia_here('collagecontribs+')) {
    $DB->query("
    SELECT COUNT(DISTINCT ct.CollageID)
    FROM collages_torrents AS ct
      JOIN collages AS c ON ct.CollageID = c.ID
    WHERE c.Deleted = '0'
      AND ct.UserID = '{$UserID}'");
    [$NumCollageContribs] = $DB->next_record();
}

if (check_paranoia_here('uniquegroups+')) {
    $DB->query("
    SELECT COUNT(DISTINCT GroupID)
    FROM torrents
    WHERE UserID = '{$UserID}'");
    [$UniqueGroups] = $DB->next_record();
}

if (check_paranoia_here('seeding+')) {
    $DB->query("
    SELECT COUNT(x.uid)
    FROM xbt_files_users AS x
      INNER JOIN torrents AS t ON t.ID = x.fid
    WHERE x.uid = '{$UserID}'
      AND x.remaining = 0");
    [$Seeding] = $DB->next_record();
}

if (check_paranoia_here('leeching+')) {
    $DB->query("
    SELECT COUNT(x.uid)
    FROM xbt_files_users AS x
      INNER JOIN torrents AS t ON t.ID = x.fid
    WHERE x.uid = '{$UserID}'
      AND x.remaining > 0");
    [$Leeching] = $DB->next_record();
}

if (check_paranoia_here('invitedcount')) {
    $DB->query("
    SELECT COUNT(UserID)
    FROM users_info
    WHERE Inviter = '{$UserID}'");
    [$Invited] = $DB->next_record();
}

if (!$OwnProfile) {
    $torrent_pass = '';
}

// Run through some paranoia stuff to decide what we can send out.
if (!check_paranoia_here('lastseen')) {
    $LastAccess = '';
}
$Ratio = check_paranoia_here('ratio') ? Format::get_ratio($Uploaded, $Downloaded, 5) : null;
if (!check_paranoia_here('uploaded')) {
    $Uploaded = null;
}
if (!check_paranoia_here('downloaded')) {
    $Downloaded = null;
}
if (isset($RequiredRatio) && !check_paranoia_here('requiredratio')) {
    $RequiredRatio = null;
}
if (0 == $ParanoiaLevel) {
    $ParanoiaLevelText = 'Off';
} elseif (1 == $ParanoiaLevel) {
    $ParanoiaLevelText = 'Very Low';
} elseif ($ParanoiaLevel <= 5) {
    $ParanoiaLevelText = 'Low';
} elseif ($ParanoiaLevel <= 20) {
    $ParanoiaLevelText = 'High';
} else {
    $ParanoiaLevelText = 'Very high';
}

//Bugfix for no access time available
if (!$LastAccess) {
    $LastAccess = '';
}

header('Content-Type: text/plain; charset=utf-8');

json_print("success", [
    'username' => $Username,
    'avatar' => $Avatar,
    'isFriend' => (bool) $Friend,
    'profileText' => Text::full_format($Info),
    'stats' => [
        'joinedDate' => $JoinDate,
        'lastAccess' => $LastAccess,
        'uploaded' => (int) $Uploaded,
        'downloaded' => (int) $Downloaded,
        'ratio' => (float) $Ratio,
        'requiredRatio' => (float) $RequiredRatio
    ],
    'ranks' => [
        'uploaded' => (int) $UploadedRank,
        'downloaded' => (int) $DownloadedRank,
        'uploads' => (int) $UploadsRank,
        'requests' => (int) $RequestRank,
        'bounty' => (int) $BountyRank,
        'posts' => (int) $PostRank,
        'artists' => (int) $ArtistsRank,
        'overall' => (int) $OverallRank
    ],
    'personal' => [
        'class' => $ClassLevels[$Class]['Name'],
        'paranoia' => (int) $ParanoiaLevel,
        'paranoiaText' => $ParanoiaLevelText,
        'donor' => (1 == $Donor),
        'warned' => (bool) $Warned,
        'enabled' => ('1' == $Enabled || '0' == $Enabled || !$Enabled),
        'passkey' => $torrent_pass
    ],
    'community' => [
        'posts' => (int) $ForumPosts,
        'torrentComments' => (int) $NumComments,
        'artistComments' => (int) $NumArtistComments,
        'collageComments' => (int) $NumCollageComments,
        'requestComments' => (int) $NumRequestComments,
        'collagesStarted' => (int) $NumCollages,
        'collagesContrib' => (int) $NumCollageContribs,
        'requestsFilled' => (int) $RequestsFilled,
        'bountyEarned' => (int) $TotalBounty,
        'requestsVoted' => (int) $RequestsVoted,
        'bountySpent' => (int) $TotalSpent,
        'uploaded' => (int) $Uploads,
        'groups' => (int) $UniqueGroups,
        'seeding' => (int) $Seeding,
        'leeching' => (int) $Leeching,
        'snatched' => (int) $Snatched,
        'invited' => (int) $Invited,
        'artistsAdded' => (int) $ArtistsAdded
    ]
]);
