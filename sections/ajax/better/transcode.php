<?php

if (!isset($_GET['type']) || !is_number($_GET['type']) || $_GET['type'] > 3) {
    error(0);
}

$Options = ['v0', 'v2', '320'];
$Encodings = ['V0 (VBR)', 'V2 (VBR)', '320'];
$EncodingKeys = array_fill_keys($Encodings, true);

if ('3' === $_GET['type']) {
    $List = "!(v0 | v2 | 320)";
} else {
    $List = '!' . $Options[$_GET['type']];
    if ('0' !== $_GET['type']) {
        $_GET['type'] = display_str($_GET['type']);
    }
}
$SphQL = new SphinxqlQuery();
$SphQL->select('id, groupid')
  ->from('better_transcode')
  ->where('logscore', 100)
  ->where_match('FLAC', 'format')
  ->where_match($List, 'encoding', false)
  ->order_by('RAND()')
  ->limit(0, TORRENTS_PER_PAGE, TORRENTS_PER_PAGE);
if (!empty($_GET['search'])) {
    $SphQL->where_match($_GET['search'], '(groupname,artistname,year,taglist)');
}

$SphQLResult = $SphQL->query();
$TorrentCount = $SphQLResult->get_meta('total');

if (0 == $TorrentCount) {
    error('No results found!');
}

$Results = $SphQLResult->to_array('groupid');
$Groups = Torrents::get_groups(array_keys($Results));

$TorrentGroups = [];
foreach ($Groups as $GroupID => $Group) {
    if (empty($Group['Torrents'])) {
        unset($Groups[$GroupID]);
        continue;
    }
    foreach ($Group['Torrents'] as $Torrent) {
        $TorRemIdent = "$Torrent[Media] $Torrent[RemasterYear] $Torrent[RemasterTitle] $Torrent[RemasterRecordLabel] $Torrent[RemasterCatalogueNumber]";
        if (!isset($TorrentGroups[$Group['ID']])) {
            $TorrentGroups[$Group['ID']] = [
                $TorRemIdent => [
                    'FlacID' => 0,
                    'Formats' => [],
                    'RemasterTitle' => $Torrent['RemasterTitle'],
                    'RemasterYear' => $Torrent['RemasterYear'],
                    'RemasterRecordLabel' => $Torrent['RemasterRecordLabel'],
                    'RemasterCatalogueNumber' => $Torrent['RemasterCatalogueNumber'],
                    'IsSnatched' => false
                ]
            ];
        } elseif (!isset($TorrentGroups[$Group['ID']][$TorRemIdent])) {
            $TorrentGroups[$Group['ID']][$TorRemIdent] = [
                'FlacID' => 0,
                'Formats' => [],
                'RemasterTitle' => $Torrent['RemasterTitle'],
                'RemasterYear' => $Torrent['RemasterYear'],
                'RemasterRecordLabel' => $Torrent['RemasterRecordLabel'],
                'RemasterCatalogueNumber' => $Torrent['RemasterCatalogueNumber'],
                'IsSnatched' => false
            ];
        }
        if (isset($EncodingKeys[$Torrent['Encoding']])) {
            $TorrentGroups[$Group['ID']][$TorRemIdent]['Formats'][$Torrent['Encoding']] = true;
        } elseif (0 == $TorrentGroups[$Group['ID']][$TorRemIdent]['FlacID'] && 'FLAC' == $Torrent['Format'] && 100 == $Torrent['LogScore']) {
            $TorrentGroups[$Group['ID']][$TorRemIdent]['FlacID'] = $Torrent['ID'];
            $TorrentGroups[$Group['ID']][$TorRemIdent]['IsSnatched'] = $Torrent['IsSnatched'];
        }
    }
}

$JsonResults = [];
foreach ($TorrentGroups as $GroupID => $Editions) {
    $GroupInfo = $Groups[$GroupID];
    $GroupYear = $GroupInfo['Year'];
    $ExtendedArtists = $GroupInfo['ExtendedArtists'];
    $GroupCatalogueNumber = $GroupInfo['CatalogueNumber'];
    $GroupName = $GroupInfo['Name'];
    $GroupRecordLabel = $GroupInfo['RecordLabel'];
    $ReleaseType = $GroupInfo['ReleaseType'];

    if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5]) || !empty($ExtendedArtists[6])) {
        unset($ExtendedArtists[2]);
        unset($ExtendedArtists[3]);
        $ArtistNames = Artists::display_artists($ExtendedArtists, false, false, false);
    } else {
        $ArtistNames = '';
    }

    $TagList = [];
    $TagList = explode(' ', str_replace('_', '.', $GroupInfo['TagList']));
    $TorrentTags = [];
    foreach ($TagList as $Tag) {
        $TorrentTags[] = "<a href=\"torrents.php?taglist=$Tag\">$Tag</a>";
    }
    $TorrentTags = implode(', ', $TorrentTags);
    foreach ($Editions as $RemIdent => $Edition) {
        if (!$Edition['FlacID']
        || !empty($Edition['Formats']) && '3' === $_GET['type']
        || true == $Edition['Formats'][$Encodings[$_GET['type']]]) {
            continue;
        }

        $JsonResults[] = [
            'torrentId' => (int)$Edition['FlacID'],
            'groupId' => (int)$GroupID,
            'artist' => $ArtistNames,
            'groupName' => $GroupName,
            'groupYear' => (int)$GroupYear,
            'missingV2' => !isset($Edition['Formats']['V2 (VBR)']),
            'missingV0' => !isset($Edition['Formats']['V0 (VBR)']),
            'missing320' => !isset($Encodings['Formats']['320']),
            'downloadUrl' => 'torrents.php?action=download&id=' . $Edition['FlacID'] . '&authkey=' . $LoggedUser['AuthKey'] . '&torrent_pass=' . $LoggedUser['torrent_pass']
        ];
    }
}

print json_encode(
    [
        'status' => 'success',
        'response' => $JsonResults
    ]
);
