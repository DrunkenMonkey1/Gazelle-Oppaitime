<?php

declare(strict_types=1);

if (!empty($_GET['userid'])) {
    if (!check_perms('users_override_paranoia')) {
        print
            json_encode([
                'status' => 'failure'
            ], JSON_THROW_ON_ERROR);
        die();
    }
    $UserID = $_GET['userid'];
    $Sneaky = ($UserID != $LoggedUser['ID']);
    if (!is_number($UserID)) {
        print
            json_encode([
                'status' => 'failure'
            ], JSON_THROW_ON_ERROR);
        die();
    }
    $DB->query("
    SELECT Username
    FROM users_main
    WHERE ID = '{$UserID}'");
    [$Username] = $DB->next_record();
} else {
    $UserID = $LoggedUser['ID'];
}

$Sneaky = ($UserID != $LoggedUser['ID']);

//$ArtistList = Bookmarks::all_bookmarks('artist', $UserID);

$DB->query("
  SELECT ag.ArtistID, ag.Name
  FROM bookmarks_artists AS ba
    INNER JOIN artists_group AS ag ON ba.ArtistID = ag.ArtistID
  WHERE ba.UserID = {$UserID}");

$ArtistList = $DB->to_array();

$JsonArtists = [];
foreach ($ArtistList as $Artist) {
    [$ArtistID, $Name] = $Artist;
    $JsonArtists[] = [
        'artistId' => (int) $ArtistID,
        'artistName' => $Name
    ];
}

print
    json_encode([
        'status' => 'success',
        'response' => [
            'artists' => $JsonArtists
        ]
    ], JSON_THROW_ON_ERROR);
