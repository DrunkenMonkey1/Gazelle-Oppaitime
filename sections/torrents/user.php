<?php declare(strict_types=1);


$Orders = ['Time', 'Name', 'Seeders', 'Leechers', 'Snatched', 'Size'];
$Ways = ['DESC' => 'Descending', 'ASC' => 'Ascending'];

// The "order by x" links on columns headers
function header_link($SortKey, $DefaultWay = 'DESC'): string
{
    global $Order, $Way;
    if ($SortKey == $Order) {
        $NewWay = 'DESC' == $Way ? 'ASC' : 'DESC';
    } else {
        $NewWay = $DefaultWay;
    }
    
    return sprintf('torrents.php?way=%s&amp;order=%s&amp;', $NewWay, $SortKey) . Format::get_url(['way', 'order']);
}

$UserID = $_GET['userid'];
if (!is_number($UserID)) {
    error(0);
}

if (!empty($_GET['page']) && is_number($_GET['page']) && $_GET['page'] > 0) {
    $Page = $_GET['page'];
    $Limit = ($Page - 1) * TORRENTS_PER_PAGE . ', ' . TORRENTS_PER_PAGE;
} else {
    $Page = 1;
    $Limit = TORRENTS_PER_PAGE;
}

$Order = !empty($_GET['order']) && in_array($_GET['order'], $Orders, true) ? $_GET['order'] : 'Time';

$Way = !empty($_GET['way']) && array_key_exists($_GET['way'], $Ways) ? $_GET['way'] : 'DESC';

$SearchWhere = [];
if (!empty($_GET['format'])) {
    if (in_array($_GET['format'], $Formats, true)) {
        $SearchWhere[] = "t.Format = '" . db_string($_GET['format']) . "'";
    } elseif ('perfectflac' == $_GET['format']) {
        $_GET['filter'] = 'perfectflac';
    }
}

if (isset($_GET['container']) && in_array($_GET['container'], array_unique(array_merge($Containers, $ContainersGames)),
        true)) {
    $SearchWhere[] = "t.Container = '" . db_string($_GET['container']) . "'";
}

if (isset($_GET['bitrate']) && in_array($_GET['bitrate'], $Bitrates, true)) {
    $SearchWhere[] = "t.Encoding = '" . db_string($_GET['bitrate']) . "'";
}

if (isset($_GET['media']) && in_array($_GET['media'], array_unique(array_merge($Media, $MediaManga)), true)) {
    $SearchWhere[] = "t.Media = '" . db_string($_GET['media']) . "'";
}

if (isset($_GET['codec']) && in_array($_GET['codec'], $Codecs, true)) {
    $SearchWhere[] = "t.Codec = '" . db_string($_GET['codec']) . "'";
}

if (isset($_GET['audioformat']) && in_array($_GET['audioformat'], $AudioFormats, true)) {
    $SearchWhere[] = "t.AudioFormat = '" . db_string($_GET['audioformat']) . "'";
}

if (isset($_GET['resolution']) && in_array($_GET['resolution'], $Resolutions, true)) {
    $SearchWhere[] = "t.Resolution = '" . db_string($_GET['resolution']) . "'";
}

if (isset($_GET['language']) && in_array($_GET['language'], $Languages, true)) {
    $SearchWhere[] = "t.Language = '" . db_string($_GET['language']) . "'";
}

if (isset($_GET['subbing']) && in_array($_GET['subbing'], $Subbing, true)) {
    $SearchWhere[] = "t.Subbing = '" . db_string($_GET['subbing']) . "'";
}

if (isset($_GET['censored']) && in_array($_GET['censored'], [1, 0], true)) {
    $SearchWhere[] = "t.Censored = '" . db_string($_GET['censored']) . "'";
}

if (!empty($_GET['categories'])) {
    $Cats = [];
    foreach (array_keys($_GET['categories']) as $Cat) {
        if (!is_number($Cat)) {
            error(0);
        }
        $Cats[] = "tg.CategoryID = '" . db_string($Cat) . "'";
    }
    $SearchWhere[] = '(' . implode(' OR ', $Cats) . ')';
}

if (!isset($_GET['tags_type'])) {
    $_GET['tags_type'] = '1';
}

if (!empty($_GET['tags'])) {
    $Tags = explode(',', $_GET['tags']);
    $TagList = [];
    foreach ($Tags as $Tag) {
        $Tag = trim(str_replace('.', '_', $Tag));
        if (empty($Tag)) {
            continue;
        }
        if ('!' == $Tag[0]) {
            $Tag = ltrim(substr($Tag, 1));
            if (empty($Tag)) {
                continue;
            }
            $TagList[] = "tg.TagList NOT RLIKE '[[:<:]]" . db_string($Tag) . "(:[^ ]+)?[[:>:]]'";
        } else {
            $TagList[] = "tg.TagList RLIKE '[[:<:]]" . db_string($Tag) . "(:[^ ]+)?[[:>:]]'";
        }
    }
    if (!empty($TagList)) {
        if (isset($_GET['tags_type']) && '1' !== $_GET['tags_type']) {
            $_GET['tags_type'] = '0';
            $SearchWhere[] = '(' . implode(' OR ', $TagList) . ')';
        } else {
            $_GET['tags_type'] = '1';
            $SearchWhere[] = '(' . implode(' AND ', $TagList) . ')';
        }
    }
}

$SearchWhere = implode(' AND ', $SearchWhere);
if (!empty($SearchWhere)) {
    $SearchWhere = sprintf(' AND %s', $SearchWhere);
}

$User = Users::user_info($UserID);
$Perms = Permissions::get_permissions($User['PermissionID']);
$UserClass = $Perms['Class'];

switch ($_GET['type']) {
    case 'snatched':
        if (!check_paranoia('snatched', $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $Time = 'xs.tstamp';
        $UserField = 'xs.uid';
        $ExtraWhere = '';
        $From = "
      xbt_snatched AS xs
        JOIN torrents AS t ON t.ID = xs.fid";
        break;
    case 'seeding':
        if (!check_paranoia('seeding', $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $Time = '(xfu.mtime - xfu.timespent)';
        $UserField = 'xfu.uid';
        $ExtraWhere = '
      AND xfu.active = 1
      AND xfu.Remaining = 0';
        $From = "
      xbt_files_users AS xfu
        JOIN torrents AS t ON t.ID = xfu.fid";
        break;
    case 'contest':
        $Time = 'unix_timestamp(t.Time)';
        $UserField = 't.UserID';
        $ExtraWhere = "
      AND t.ID IN (
          SELECT TorrentID
          FROM library_contest
          WHERE UserID = {$UserID}
          )";
        $From = 'torrents AS t';
        break;
    case 'leeching':
        if (!check_paranoia('leeching', $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $Time = '(xfu.mtime - xfu.timespent)';
        $UserField = 'xfu.uid';
        $ExtraWhere = '
      AND xfu.active = 1
      AND xfu.Remaining > 0';
        $From = "
      xbt_files_users AS xfu
        JOIN torrents AS t ON t.ID = xfu.fid";
        break;
    case 'uploaded':
        if ((empty($_GET['filter']) || 'perfectflac' !== $_GET['filter']) && !check_paranoia('uploads',
                $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $Time = 'unix_timestamp(t.Time)';
        $UserField = 't.UserID';
        $ExtraWhere = '';
        $From = "torrents AS t";
        break;
    case 'downloaded':
        if (!check_perms('site_view_torrent_snatchlist')) {
            error(403);
        }
        $Time = 'unix_timestamp(ud.Time)';
        $UserField = 'ud.UserID';
        $ExtraWhere = '';
        $From = "
      users_downloads AS ud
        JOIN torrents AS t ON t.ID = ud.TorrentID";
        break;
    default:
        error(404);
}

if (!empty($_GET['filter'])) {
    if ('perfectflac' === $_GET['filter']) {
        if (!check_paranoia('perfectflacs', $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $ExtraWhere .= " AND t.Format = 'FLAC'";
        if (empty($_GET['media'])) {
            $ExtraWhere .= "
        AND (
          t.LogScore = 100 OR
          t.Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT')
          )";
        } elseif ('CD' === strtoupper($_GET['media']) && empty($_GET['log'])) {
            $ExtraWhere .= "
        AND t.LogScore = 100";
        }
    } elseif ('uniquegroup' === $_GET['filter']) {
        if (!check_paranoia('uniquegroups', $User['Paranoia'], $UserClass, $UserID)) {
            error(403);
        }
        $GroupBy = 'tg.ID';
    }
}

if (empty($GroupBy)) {
    $GroupBy = 't.ID';
}

if ((empty($_GET['search']) || '' === trim($_GET['search']))) {//&& $Order != 'Name') {
    $SQL = "
    SELECT
      SQL_CALC_FOUND_ROWS
      t.GroupID,
      t.ID AS TorrentID,
      {$Time} AS Time,
      tg.Name AS Name,
      tg.CategoryID
    FROM {$From}
      JOIN torrents_group AS tg ON tg.ID = t.GroupID
    WHERE {$UserField} = '{$UserID}'
      {$ExtraWhere}
      {$SearchWhere}
    GROUP BY {$GroupBy}
    ORDER BY {$Order} {$Way}
    LIMIT {$Limit}";
} else {
    $DB->query("
    CREATE TEMPORARY TABLE temp_sections_torrents_user (
      GroupID int(10) unsigned not null,
      TorrentID int(10) unsigned not null,
      Time int(12) unsigned not null,
      CategoryID int(3) unsigned,
      Seeders int(6) unsigned,
      Leechers int(6) unsigned,
      Snatched int(10) unsigned,
      Name mediumtext,
      Size bigint(12) unsigned,
    PRIMARY KEY (TorrentID)) CHARSET=utf8");
    $DB->query("
    INSERT IGNORE INTO temp_sections_torrents_user
      SELECT
        t.GroupID,
        t.ID AS TorrentID,
        {$Time} AS Time,
        tg.CategoryID,
        t.Seeders,
        t.Leechers,
        t.Snatched,
        CONCAT_WS(' ', GROUP_CONCAT(ag.Name SEPARATOR ' '), ' ', tg.Name, ' ', tg.Year, ' ') AS Name,
        t.Size
      FROM {$From}
        JOIN torrents_group AS tg ON tg.ID = t.GroupID
        LEFT JOIN torrents_artists AS ta ON ta.GroupID = tg.ID
        LEFT JOIN artists_group AS ag ON ag.ArtistID = ta.ArtistID
      WHERE {$UserField} = '{$UserID}'
        {$ExtraWhere}
        {$SearchWhere}
      GROUP BY TorrentID, Time");
    
    if (!empty($_GET['search']) && '' !== trim($_GET['search'])) {
        $Words = array_unique(explode(' ', db_string($_GET['search'])));
    }
    
    $SQL = "
    SELECT
      SQL_CALC_FOUND_ROWS
      GroupID,
      TorrentID,
      Time,
      CategoryID
    FROM temp_sections_torrents_user";
    if (!empty($Words)) {
        $SQL .= "
    WHERE Name LIKE '%" . implode("%' AND Name LIKE '%", $Words) . "%'";
    }
    $SQL .= "
    ORDER BY {$Order} {$Way}
    LIMIT {$Limit}";
}

$DB->query($SQL);
$GroupIDs = $DB->collect('GroupID');
$TorrentsInfo = $DB->to_array('TorrentID', MYSQLI_ASSOC);

$DB->query('SELECT FOUND_ROWS()');
[$TorrentCount] = $DB->next_record();

$Results = Torrents::get_groups($GroupIDs);

$Action = display_str($_GET['type']);
$User = Users::user_info($UserID);

View::show_header($User['Username'] . sprintf('\'s %s torrents', $Action), 'browse');

$Pages = Format::get_pages($Page, $TorrentCount, TORRENTS_PER_PAGE);


?>
<div class="thin">
    <div class="header">
        <h2><a href="user.php?id=<?= $UserID ?>"><?= $User['Username'] ?></a><?= sprintf('\'s %s torrents', $Action) ?>
        </h2>
    </div>
    <div class="box pad">
        <form class="search_form" name="torrents" action="" method="get">
            <table class="layout">
                <tr>
                    <td class="label"><strong>Search for:</strong></td>
                    <td>
                        <input type="hidden" name="type" value="<?= $_GET['type'] ?>"/>
                        <input type="hidden" name="userid" value="<?= $UserID ?>"/>
                        <input type="search" name="search" size="60" value="<?php Format::form('search') ?>"/>
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Release specifics:</strong></td>
                    <td class="nobr" colspan="3">
                        <select id="container" name="container" class="ft_container">
                            <option value="">Container</option>
                            <?php foreach ($Containers as $ContainerName) { ?>
                                <option value="<?= display_str($ContainerName); ?>"<?php Format::selected('container',
                                    $ContainerName) ?>><?= display_str($ContainerName); ?></option>
                            <?php } ?>
                            <?php foreach ($ContainersGames as $ContainerName) { ?>
                                <option value="<?= display_str($ContainerName); ?>"<?php Format::selected('container',
                                    $ContainerName) ?>><?= display_str($ContainerName); ?></option>
                            <?php } ?>
                        </select>
                        <select id="codec" name="codec" class="ft_codec">
                            <option value="">Codec</option>
                            <?php foreach ($Codecs as $CodecName) { ?>
                                <option value="<?= display_str($CodecName); ?>"<?php Format::selected('codec',
                                    $CodecName) ?>><?= display_str($CodecName); ?></option>
                            <?php } ?>
                        </select>
                        <select id="audioformat" name="audioformat" class="ft_audioformat">
                            <option value="">AudioFormat</option>
                            <?php foreach ($AudioFormats as $AudioFormatName) { ?>
                                <option value="<?= display_str($AudioFormatName); ?>"<?php Format::selected('audioformat',
                                    $AudioFormatName) ?>><?= display_str($AudioFormatName); ?></option>
                            <?php } ?>
                        </select>
                        <select id="resolution" name="resolution" class="ft_resolution">
                            <option value="">Resolution</option>
                            <?php foreach ($Resolutions as $ResolutionName) { ?>
                                <option value="<?= display_str($ResolutionName); ?>"<?php Format::selected('resolution',
                                    $ResolutionName) ?>><?= display_str($ResolutionName); ?></option>
                            <?php } ?>
                        </select>
                        <select id="language" name="language" class="ft_language">
                            <option value="">Language</option>
                            <?php foreach ($Languages as $LanguageName) { ?>
                                <option value="<?= display_str($LanguageName); ?>"<?php Format::selected('language',
                                    $LanguageName) ?>><?= display_str($LanguageName); ?></option>
                            <?php } ?>
                        </select>
                        <select id="subbing" name="subbing" class="ft_subbing">
                            <option value="">Subs</option>
                            <?php foreach ($Subbing as $SubbingName) { ?>
                                <option value="<?= display_str($SubbingName); ?>"<?php Format::selected('subbing',
                                    $SubbingName) ?>><?= display_str($SubbingName); ?></option>
                            <?php } ?>
                        </select>
                        <select name="media" class="ft_media">
                            <option value="">Media</option>
                            <?php foreach ($Media as $MediaName) { ?>
                                <option value="<?= display_str($MediaName); ?>"<? Format::selected('media',
                                    $MediaName) ?>><?= display_str($MediaName); ?></option>
                            <?php } ?>
                            <option value="Scan"<?php Format::selected('media', 'Scan') ?>>Scan</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Misc:</strong></td>
                    <td class="nobr" colspan="3">
                        <select name="censored" class="ft_censored">
                            <option value="3">Censored?</option>
                            <option value="1"<?php Format::selected('censored', 1) ?>>Censored</option>
                            <option value="0"<?php Format::selected('censored', 0) ?>>Uncensored</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Tags:</strong></td>
                    <td>
                        <input type="search"
                               name="tags"
                               size="60"
                               class="tooltip"
                               title="Use !tag to exclude tag"
                               value="<?php Format::form('tags') ?>"/>&nbsp;
                        <input type="radio"
                               name="tags_type"
                               id="tags_type0"
                               value="0"<?php Format::selected('tags_type',
                            0, 'checked') ?> /><label for="tags_type0"> Any</label>&nbsp;&nbsp;
                        <input type="radio"
                               name="tags_type"
                               id="tags_type1"
                               value="1"<?php Format::selected('tags_type',
                            1, 'checked') ?> /><label for="tags_type1"> All</label>
                    </td>
                </tr>

                <tr>
                    <td class="label"><strong>Order by</strong></td>
                    <td>
                        <select name="order" class="ft_order_by">
                            <?php foreach ($Orders as $OrderText) { ?>
                                <option value="<?= $OrderText ?>"<?php Format::selected('order',
                                    $OrderText) ?>><?= $OrderText ?></option>
                            <?php } ?>
                        </select>&nbsp;
                        <select name="way" class="ft_order_way">
                            <?php foreach ($Ways as $WayKey => $WayText) { ?>
                                <option value="<?= $WayKey ?>"<?php Format::selected('way',
                                    $WayKey) ?>><?= $WayText ?></option>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
            </table>

            <table class="layout cat_list">
                <?php
                $x = 0;
                reset($Categories);
                foreach ($Categories
                
                as $CatKey => $CatName) {
                if (0 === $x % 7) {
                if ($x > 0) {
                    ?>
                    </tr>
                    <?php
                } ?>
                <tr>
                    <?php
                    }
                    ++$x; ?>
                    <td>
                        <input type="checkbox"
                               name="categories[<?= ($CatKey + 1) ?>]"
                               id="cat_<?= ($CatKey + 1) ?>"
                               value="1"<?php if (isset($_GET['categories'][$CatKey + 1])) { ?> checked="checked"<?php } ?> />
                        <label for="cat_<?= ($CatKey + 1) ?>"><?= $CatName ?></label>
                    </td>
                    <?php
                    }
                    ?>
                </tr>
            </table>
            <div class="submit">
                <span class="float_left"><?= number_format((int) $TorrentCount) ?> Results</span>
                <input type="submit" value="Search torrents"/>
            </div>
        </form>
    </div>
    <?php if (0 === count($GroupIDs)) { ?>
        <div class="center">
            Nothing found!
        </div>
    <?php } else { ?>
        <div class="linkbox"><?= $Pages ?></div>
        <div class="box">
            <table class="torrent_table cats" width="100%">
                <tr class="colhead">
                    <td class="cats_col"></td>
                    <td><a href="<?= header_link('Name', 'ASC') ?>">Torrent</a></td>
                    <td><a href="<?= header_link('Time') ?>">Time</a></td>
                    <td><a href="<?= header_link('Size') ?>">Size</a></td>
                    <td class="sign snatches">
                        <a href="<?= header_link('Snatched') ?>">
                            <svg width="15"
                                 height="15"
                                 fill="white"
                                 class="tooltip"
                                 alt="Snatches"
                                 title="Snatches"
                                 viewBox="3 0 88 98">
                                <path d="M20 20 A43 43,0,1,0,77 23 L90 10 L55 10 L55 45 L68 32 A30.27 30.27,0,1,1,28 29"></path>
                            </svg>
                        </a>
                    </td>
                    <td class="sign seeders">
                        <a href="<?= header_link('Seeders') ?>">
                            <svg width="11" height="15" fill="white" class="tooltip" alt="Seeders" title="Seeders">
                                <polygon points="0,7 5.5,0 11,7 8,7 8,15 3,15 3,7"></polygon>
                            </svg>
                        </a>
                    </td>
                    <td class="sign leechers">
                        <a href="<?= header_link('Leechers') ?>">
                            <svg width="11" height="15" fill="white" class="tooltip" alt="Leechers" title="Leechers">
                                <polygon points="0,8 5.5,15 11,8 8,8 8,0 3,0 3,8"></polygon>
                            </svg>
                        </a>
                    </td>
                </tr>
                <?php
                $PageSize = 0;
                foreach ($TorrentsInfo as $TorrentID => $Info) {
                    [$GroupID, , $Time] = array_values($Info);
                    
                    extract(Torrents::array_group($Results[$GroupID]));
                    $Torrent = $Torrents[$TorrentID];
                    
                    
                    $TorrentTags = new Tags($TagList);
                    
                    $DisplayName = Artists::display_artists($Artists);
                    $DisplayName .= '<a href="torrents.php?id=' . $GroupID . '&amp;torrentid=' . $TorrentID . '" ';
                    if (!isset($LoggedUser['CoverArt']) || $LoggedUser['CoverArt']) {
                        $DisplayName .= 'data-cover="' . ImageTools::process($WikiImage, 'thumb') . '" ';
                    }
                    $DisplayName .= 'dir="ltr">' . $GroupName . '</a>';
                    if ($GroupYear) {
                        $DisplayName .= sprintf(' [%s]', $GroupYear);
                    }
                    if ($GroupStudio) {
                        $DisplayName .= sprintf(' [%s]', $GroupStudio);
                    }
                    if ($GroupCatalogueNumber) {
                        $DisplayName .= sprintf(' [%s]', $GroupCatalogueNumber);
                    }
                    if ($GroupDLSiteID) {
                        $DisplayName .= sprintf(' [%s]', $GroupDLSiteID);
                    }
                    $ExtraInfo = Torrents::torrent_info($Torrent);
                    if ($ExtraInfo) {
                        $DisplayName .= sprintf(' - %s', $ExtraInfo);
                    } ?>
                    <tr class="torrent torrent_row<?= ($Torrent['IsSnatched'] ? ' snatched_torrent' : '') . ($GroupFlags['IsSnatched'] ? ' snatched_group' : '') ?>">
                        <td class="center cats_col">
                            <div title="<?= Format::pretty_category($GroupCategoryID) ?>"
                                 class="tooltip <?= Format::css_category($GroupCategoryID) ?>"></div>
                        </td>
                        <td class="big_info">
                            <div class="group_info clear">
          <span class="torrent_links_block">
            [ <a href="torrents.php?action=download&amp;id=<?= $TorrentID ?>&amp;authkey=<?= $LoggedUser['AuthKey'] ?>&amp;torrent_pass=<?= $LoggedUser['torrent_pass'] ?>"
                 class="tooltip"
                 title="Download">DL</a>
            | <a href="reportsv2.php?action=report&amp;id=<?= $TorrentID ?>" class="tooltip" title="Report">RP</a> ]
          </span>
                                <?php echo $DisplayName . PHP_EOL; ?>
                                <div class="tags"><?= $TorrentTags->format('torrents.php?type=' . $Action . '&amp;userid=' . $UserID . '&amp;tags=') ?></div>
                            </div>
                        </td>
                        <td class="nobr"><?= time_diff($Time, 1) ?></td>
                        <td class="number_column nobr"><?= Format::get_size($Torrent['Size']) ?></td>
                        <td class="number_column"><?= number_format((int) $Torrent['Snatched']) ?></td>
                        <td class="number_column<?= ((0 == $Torrent['Seeders']) ? ' r00' : '') ?>"><?= number_format((int) $Torrent['Seeders']) ?></td>
                        <td class="number_column"><?= number_format((int) $Torrent['Leechers']) ?></td>
                    </tr>
                    <?php
                } ?>
            </table>
        </div>
    <?php } ?>
    <div class="linkbox"><?= $Pages ?></div>
</div>
<?php View::show_footer(); ?>
