<?php declare(strict_types=1);
if (!empty($_GET['userid']) && is_number($_GET['userid'])) {
    if (check_perms('users_override_paranoia')) {
        $UserID = $_GET['userid'];
    } else {
        error(403);
    }
} else {
    $UserID = $LoggedUser['ID'];
}

$Encodings = ['V0 (VBR)', 'V2 (VBR)', '320'];
$EncodingKeys = array_fill_keys($Encodings, true);

$SeedingOnly = !empty($_GET['filter']) && 'seeding' === $_GET['filter'];

// Get list of FLAC snatches
$DB->query("
  SELECT t.GroupID, x.fid
  FROM " . ($SeedingOnly ? 'xbt_files_users' : 'xbt_snatched') . " AS x
    JOIN torrents AS t ON t.ID = x.fid
    JOIN torrents_group AS tg ON tg.ID = t.GroupID
  WHERE t.Format = 'FLAC'
    AND ((t.LogScore = '100' AND t.Media = 'CD')
      OR t.Media != 'CD')
    AND tg.CategoryID = 1
    AND x.uid = '{$UserID}'" .
    ($SeedingOnly ? ' AND x.active = 1 AND x.remaining = 0' : ''));

$SnatchedTorrentIDs = array_fill_keys($DB->collect('fid'), true);
$SnatchedGroupIDs = array_unique($DB->collect('GroupID'));
if (count($SnatchedGroupIDs) > 1000) {
    shuffle($SnatchedGroupIDs);
    $SnatchedGroupIDs = array_slice($SnatchedGroupIDs, 0, 1000);
}

if ([] === $SnatchedGroupIDs) {
    error(($SeedingOnly ? "You aren't seeding any perfect FLACs!" : "You haven't snatched any perfect FLACs!"));
}
// Create hash table

$DB->query("
  CREATE TEMPORARY TABLE temp_sections_better_snatch
  SELECT
    GroupID,
    GROUP_CONCAT(Encoding SEPARATOR ' ') AS EncodingList,
    CRC32(CONCAT_WS(
      ' ', Media, Remasteryear, Remastertitle,
      Remasterrecordlabel, Remastercataloguenumber)
    ) AS RemIdent
  FROM torrents
  WHERE GroupID IN (" . implode(',', $SnatchedGroupIDs) . ")
    AND Format IN ('FLAC', 'MP3')
  GROUP BY GroupID, RemIdent");

$DB->query("
  SELECT GroupID
  FROM temp_sections_better_snatch
  WHERE EncodingList NOT LIKE '%V0 (VBR)%'
    OR EncodingList NOT LIKE '%V2 (VBR)%'
    OR EncodingList NOT LIKE '%320%'");

$GroupIDs = array_fill_keys($DB->collect('GroupID'), true);

if ([] === $GroupIDs) {
    error('No results found');
}

$Groups = Torrents::get_groups(array_keys($GroupIDs));
$TorrentGroups = [];
foreach ($Groups as $GroupID => $Group) {
    if (empty($Group['Torrents'])) {
        unset($Groups[$GroupID]);
        continue;
    }
    foreach ($Group['Torrents'] as $Torrent) {
        $TorRemIdent = sprintf('%s %s %s %s %s', $Torrent[Media], $Torrent[RemasterYear], $Torrent[RemasterTitle], $Torrent[RemasterRecordLabel], $Torrent[RemasterCatalogueNumber]);
        if (!isset($TorrentGroups[$Group['ID']])) {
            $TorrentGroups[$Group['ID']] = [
                $TorRemIdent => [
                    'FlacID' => 0,
                    'Formats' => [],
                    'IsSnatched' => $Torrent['IsSnatched'],
                    'Medium' => $Torrent['Media'],
                    'RemasterTitle' => $Torrent['RemasterTitle'],
                    'RemasterYear' => $Torrent['RemasterYear'],
                    'RemasterRecordLabel' => $Torrent['RemasterRecordLabel'],
                    'RemasterCatalogueNumber' => $Torrent['RemasterCatalogueNumber']
                ]
            ];
        } elseif (!isset($TorrentGroups[$Group['ID']][$TorRemIdent])) {
            $TorrentGroups[$Group['ID']][$TorRemIdent] = [
                'FlacID' => 0,
                'Formats' => [],
                'IsSnatched' => $Torrent['IsSnatched'],
                'Medium' => $Torrent['Media'],
                'RemasterTitle' => $Torrent['RemasterTitle'],
                'RemasterYear' => $Torrent['RemasterYear'],
                'RemasterRecordLabel' => $Torrent['RemasterRecordLabel'],
                'RemasterCatalogueNumber' => $Torrent['RemasterCatalogueNumber']
            ];
        }
        if ('MP3' == $Torrent['Format'] && isset($EncodingKeys[$Torrent['Encoding']])) {
            $TorrentGroups[$Group['ID']][$TorRemIdent]['Formats'][$Torrent['Encoding']] = true;
        } elseif (isset($SnatchedTorrentIDs[$Torrent['ID']])) {
            $TorrentGroups[$Group['ID']][$TorRemIdent]['FlacID'] = $Torrent['ID'];
        }
    }
}
$Counter = [
    'total' => 0, //how many FLAC torrents can be transcoded?
    'miss_total' => 0, //how many transcodes are missing?
    'miss_V0 (VBR)' => 0, //how many V0 transcodes are missing?
    'miss_V2 (VBR)' => 0, //how many V2 transcodes are missing?
    'miss_320' => 0, //how many 320 transcodes are missing?
];
foreach ($TorrentGroups as $Editions) {
    foreach ($Editions as $Edition) {
        if (0 == $Edition['FlacID']) { // no FLAC in this edition
            continue;
        }
        $edition_miss = 0; //number of transcodes missing in this edition
        foreach ($Encodings as $Encoding) {
            if (!isset($Edition['Formats'][$Encoding])) {
                ++$edition_miss;
                ++$Counter[sprintf('miss_%s', $Encoding)];
            }
        }
        $Counter['miss_total'] += $edition_miss;
        $Counter['total'] += (bool)$edition_miss;
    }
}

View::show_header('Transcode Snatches');
?>
<div class="linkbox">
  <a href="better.php" class="brackets">Back to better.php list</a>
<?php if ($SeedingOnly) { ?>
  <a href="better.php?method=snatch" class="brackets">Show all</a>
<?php } else { ?>
  <a href="better.php?method=snatch&amp;filter=seeding" class="brackets">Show only those currently seeding</a>
<?php } ?>
</div>
<div class="thin">
  <h2>Transcode <?=($SeedingOnly ? 'seeding' : 'snatched')?> torrents</h2>
  <h3>Stats</h3>
  <div class="box pad">
    <p>
      Number of perfect FLACs you can transcode: <?=number_format($Counter['total'])?><br />
      Number of missing transcodes: <?=number_format($Counter['miss_total'])?><br />
      Number of missing V2 / V0 / 320 transcodes: <?=number_format($Counter['miss_V2 (VBR)'])?> / <?=number_format($Counter['miss_V0 (VBR)'])?> / <?=number_format($Counter['miss_320'])?>
    </p>
  </div>
  <h3>List</h3>
  <table width="100%" class="torrent_table">
    <tr class="colhead">
      <td>Torrent</td>
      <td>V2</td>
      <td>V0</td>
      <td>320</td>
    </tr>
<?php
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
        $ArtistNames = Artists::display_artists($ExtendedArtists);
    } else {
        $ArtistNames = '';
    }
    $TorrentTags = new Tags($GroupInfo['TagList']);
    foreach ($Editions as $RemIdent => $Edition) {
        if (!$Edition['FlacID'] || 3 === count($Edition['Formats'])) {
            continue;
        }
        $DisplayName = $ArtistNames . '<a href="torrents.php?id=' . $GroupID . '&amp;torrentid=' . $Edition['FlacID'] . '#torrent' . $Edition['FlacID'] . '" class="tooltip" title="View torrent" dir="ltr">' . $GroupName . '</a>';
        if ($GroupYear > 0) {
            $DisplayName .= sprintf(' [%s]', $GroupYear);
        }
        if ($ReleaseType > 0) {
            $DisplayName .= ' [' . $ReleaseTypes[$ReleaseType] . ']';
        }
        $DisplayName .= ' [' . $Edition['Medium'] . ']';

        $EditionInfo = [];
        $ExtraInfo = empty($Edition['RemasterYear']) ? '' : $Edition['RemasterYear'];
        if (!empty($Edition['RemasterRecordLabel'])) {
            $EditionInfo[] = $Edition['RemasterRecordLabel'];
        }
        if (!empty($Edition['RemasterTitle'])) {
            $EditionInfo[] = $Edition['RemasterTitle'];
        }
        if (!empty($Edition['RemasterCatalogueNumber'])) {
            $EditionInfo[] = $Edition['RemasterCatalogueNumber'];
        }
        if (!empty($Edition['RemasterYear'])) {
            $ExtraInfo .= ' - ';
        }
        $ExtraInfo .= implode(' / ', $EditionInfo); ?>
    <tr class="torrent torrent_row<?=$Edition['IsSnatched'] ? ' snatched_torrent' : ''?>">
      <td>
        <span class="torrent_links_block">
          <a href="torrents.php?action=download&amp;id=<?=$Edition['FlacID']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download" class="brackets tooltip">DL</a>
        </span>
        <?=$DisplayName?>
        <div class="torrent_info"><?=$ExtraInfo?></div>
        <div class="tags"><?=$TorrentTags->format()?></div>
      </td>
      <td><?=isset($Edition['Formats']['V2 (VBR)']) ? '<strong class="important_text_alt">YES</strong>' : '<strong class="important_text">NO</strong>'?></td>
      <td><?=isset($Edition['Formats']['V0 (VBR)']) ? '<strong class="important_text_alt">YES</strong>' : '<strong class="important_text">NO</strong>'?></td>
      <td><?=isset($Edition['Formats']['320']) ? '<strong class="important_text_alt">YES</strong>' : '<strong class="important_text">NO</strong>'?></td>
    </tr>
<?php
    }
}
?>
  </table>
</div>
<?php
View::show_footer();
?>
