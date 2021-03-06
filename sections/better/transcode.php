<?php declare(strict_types=1);
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
        $TorRemIdent = sprintf('%s %s %s %s %s', $Torrent[Media], $Torrent[RemasterYear], $Torrent[RemasterTitle], $Torrent[RemasterRecordLabel], $Torrent[RemasterCatalogueNumber]);
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
        if ('MP3' == $Torrent['Format'] && isset($EncodingKeys[$Torrent['Encoding']])) {
            $TorrentGroups[$Group['ID']][$TorRemIdent]['Formats'][$Torrent['Encoding']] = true;
        } elseif (0 == $TorrentGroups[$Group['ID']][$TorRemIdent]['FlacID'] && 'FLAC' == $Torrent['Format'] && (100 == $Torrent['LogScore'] || 'CD' != $Torrent['Media'])) {
            $TorrentGroups[$Group['ID']][$TorRemIdent]['FlacID'] = $Torrent['ID'];
            $TorrentGroups[$Group['ID']][$TorRemIdent]['IsSnatched'] = $Torrent['IsSnatched'];
        }
    }
}

View::show_header('Transcode Search');
?>
<br />
<div class="linkbox">
  <a href="better.php" class="brackets">Back to better.php list</a>
</div>
<div class="thin">
  <form class="search_form" name="transcodes" action="" method="get">
    <table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
      <tr>
        <td class="label"><strong>Search:</strong></td>
        <td>
          <input type="hidden" name="method" value="transcode" />
          <input type="hidden" name="type" value="<?=$_GET['type']?>" />
          <input type="search" name="search" size="60" value="<?=(empty($_GET['search']) ? '' : display_str($_GET['search']))?>" />
          &nbsp;
          <input type="submit" value="Search" />
        </td>
      </tr>
    </table>
  </form>
  <br />
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
        if (!$Edition['FlacID'] //no FLAC in this group
        || !empty($Edition['Formats']) && '3' === $_GET['type'] //at least one transcode present when we only wanted groups containing no transcodes at all (type 3)
        || $Edition['Formats'][$Encodings[$_GET['type']]] //the transcode we asked for is already there
        || 3 === count($Edition['Formats'])) { //all 3 transcodes are there already (this can happen due to the caching of Sphinx's better_transcode table)
      continue;
        }
        $DisplayName = $ArtistNames . '<a href="torrents.php?id=' . $GroupID . '&amp;torrentid=' . $Edition['FlacID'] . '#torrent' . $Edition['FlacID'] . '" class="tooltip" title="View torrent" dir="ltr">' . $GroupName . '</a>';
        if ($GroupYear > 0) {
            $DisplayName .= sprintf(' [%s]', $GroupYear);
        }
        if ($ReleaseType > 0) {
            $DisplayName .= ' [' . $ReleaseTypes[$ReleaseType] . ']';
        }
        if ($Edition['IsSnatched']) {
            $DisplayName .= ' ' . Format::torrent_label('Snatched!');
        }

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
    <tr<?=$Edition['IsSnatched'] ? ' class="snatched_torrent"' : ''?>>
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
