<?php

declare(strict_types=1);

if (empty($_GET['id']) || !is_number($_GET['id'])) {
    json_die("failure", 'Not Found');
}

[$NumComments, $Page, $Thread] = Comments::load('torrents', (int) $_GET['id'], false);

//---------- Begin printing
$JsonComments = [];
foreach ($Thread as $Key => $Post) {
    [$PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername] = array_values($Post);
    [
        $AuthorID,
        $Username,
        $PermissionID,
        $Paranoia,
        $Artist,
        $Donor,
        $Warned,
        $Avatar,
        $Enabled,
        $UserTitle
    ] = array_values(Users::user_info($AuthorID));
    $JsonComments[] = [
        'postId' => (int) $PostID,
        'addedTime' => $AddedTime,
        'bbBody' => $Body,
        'body' => Text::full_format($Body),
        'editedUserId' => (int) $EditedUserID,
        'editedTime' => $EditedTime,
        'editedUsername' => $EditedUsername,
        'userinfo' => [
            'authorId' => (int) $AuthorID,
            'authorName' => $Username,
            'artist' => 1 == $Artist,
            'donor' => 1 == $Donor,
            'warned' => (bool) $Warned,
            'avatar' => $Avatar,
            'enabled' => (2 != $Enabled),
            'userTitle' => $UserTitle
        ]
    ];
}

json_die("success", [
    'page' => (int) $Page,
    'pages' => ceil($NumComments / TORRENT_COMMENTS_PER_PAGE),
    'comments' => $JsonComments
]);
