<?php
$Where = [];

if (!empty($_GET['advanced']) && check_perms('site_advanced_top10')) {
    $Details = 'all';
    $Limit = 10;

    if ($_GET['tags']) {
        $TagWhere = [];
        $Tags = explode(',', str_replace('.', '_', trim($_GET['tags'])));
        foreach ($Tags as $Tag) {
            $Tag = preg_replace('/[^a-z0-9_]/', '', $Tag);
            if ('' != $Tag) {
                $TagWhere[] = "g.TagList REGEXP '[[:<:]]" . db_string($Tag) . "[[:>:]]'";
            }
        }
        if (!empty($TagWhere)) {
            if ('any' == $_GET['anyall']) {
                $Where[] = '(' . implode(' OR ', $TagWhere) . ')';
            } else {
                $Where[] = '(' . implode(' AND ', $TagWhere) . ')';
            }
        }
    }

    if ($_GET['category']) {
        if (in_array($_GET['category'], $Categories, true)) {
            $Where[] = "g.CategoryID = '" . (array_search($_GET['category'], $Categories, true)+1) . "'";
        }
    }
} else {
    // error out on invalid requests (before caching)
    if (isset($_GET['details'])) {
        if (in_array($_GET['details'], ['day', 'week', 'overall', 'snatched', 'data', 'seeded', 'month', 'year'], true)) {
            $Details = $_GET['details'];
        } else {
            error(404);
        }
    } else {
        $Details = 'all';
    }

    // defaults to 10 (duh)
    $Limit = (isset($_GET['limit']) ? intval($_GET['limit']) : 10);
    $Limit = (in_array($Limit, [10, 100, 250], true) ? $Limit : 10);
}
$Filtered = !empty($Where);
View::show_header("Top $Limit Torrents", 'browse');
?>
<div class="thin">
  <div class="header">
    <h2>Top <?=$Limit?> Torrents</h2>
    <?php Top10View::render_linkbox("torrents"); ?>
  </div>
<?php

if (check_perms('site_advanced_top10')) {
    ?>
  <div class="box pad">
  <form class="search_form" name="torrents" action="" method="get">
    <input type="hidden" name="advanced" value="1" />
    <table cellpadding="6" cellspacing="1" border="0" class="layout" width="100%">
      <tr id="tagfilter">
        <td class="label">Tags (comma-separated):</td>
        <td class="ft_taglist">
          <input type="text" name="tags" id="tags" size="75" value="<?php if (!empty($_GET['tags'])) {
        echo display_str($_GET['tags']);
    } ?>"<?php Users::has_autocomplete_enabled('other'); ?> />&nbsp;
          <input type="radio" id="rdoAll" name="anyall" value="all"<?=((!isset($_GET['anyall'])||'any'!=$_GET['anyall'])?' checked="checked"':'')?> /><label for="rdoAll"> All</label>&nbsp;&nbsp;
          <input type="radio" id="rdoAny" name="anyall" value="any"<?=((!isset($_GET['anyall'])||'any'==$_GET['anyall'])?' checked="checked"':'')?> /><label for="rdoAny"> Any</label>
        </td>
      </tr>
      <tr>
        <td class="label">Category:</td>
        <td>
          <select name="category" style="width: auto;" class="ft_format">
            <option value="">Any</option>
<?php  foreach ($Categories as $CategoryName) { ?>
            <option value="<?=display_str($CategoryName)?>"<?=(($CategoryName==($_GET['category']??false))?'selected="selected"':'')?>><?=display_str($CategoryName)?></option>
<?php  } ?>
          </select>
        </td>
      </tr>
      <tr>
        <td colspan="2" class="center">
          <input type="submit" value="Filter torrents" />
        </td>
      </tr>
    </table>
  </form>
  </div>
<?php
}

// default setting to have them shown
$DisableFreeTorrentTop10 = (isset($LoggedUser['DisableFreeTorrentTop10']) ? $LoggedUser['DisableFreeTorrentTop10'] : 0);
// did they just toggle it?
if (isset($_GET['freeleech'])) {
    // what did they choose?
    $NewPref = (('hide' == $_GET['freeleech']) ? 1 : 0);

    // Pref id different
    if ($NewPref != $DisableFreeTorrentTop10) {
        $DisableFreeTorrentTop10 = $NewPref;
        Users::update_site_options($LoggedUser['ID'], ['DisableFreeTorrentTop10' => $DisableFreeTorrentTop10]);
    }
}

// Modify the Where query
if ($DisableFreeTorrentTop10) {
    $Where[] = "t.FreeTorrent='0'";
}

// The link should say the opposite of the current setting
$FreeleechToggleName = ($DisableFreeTorrentTop10 ? 'show' : 'hide');
$FreeleechToggleQuery = Format::get_url(['freeleech', 'groups']);

if (!empty($FreeleechToggleQuery)) {
    $FreeleechToggleQuery .= '&amp;';
}

$FreeleechToggleQuery .= 'freeleech=' . $FreeleechToggleName;

$GroupByToggleName = ((isset($_GET['groups']) && 'show' == $_GET['groups']) ? 'hide' : 'show');
$GroupByToggleQuery = Format::get_url(['freeleech', 'groups']);

if (!empty($GroupByToggleQuery)) {
    $GroupByToggleQuery .= '&amp;';
}

$GroupByToggleQuery .= 'groups=' . $GroupByToggleName;

$GroupBySum = '';
$GroupBy = '';
if (isset($_GET['groups']) && 'show' == $_GET['groups']) {
    $GroupBy = ' GROUP BY g.ID ';
    $GroupBySum = md5($GroupBy);
}

?>
  <div style="text-align: right;" class="linkbox">
    <a href="top10.php?<?=$FreeleechToggleQuery?>" class="brackets"><?=ucfirst($FreeleechToggleName)?> freeleech in Top 10</a>
<?php    if (check_perms('users_mod')) { ?>
      <a href="top10.php?<?=$GroupByToggleQuery?>" class="brackets"><?=ucfirst($GroupByToggleName)?> top groups</a>
<?php    } ?>
  </div>
<?php

if (!empty($Where)) {
    $Where = '(' . implode(' AND ', $Where) . ')';
    $WhereSum = md5($Where);
} else {
    $WhereSum = '';
}
$BaseQuery = '
  SELECT
    t.ID,
    g.ID,
    g.Name,
    g.NameRJ,
    g.NameJP,
    g.CategoryID,
    g.WikiImage,
    g.TagList,
    t.Media,
    g.Year,
    t.Snatched,
    t.Seeders,
    t.Leechers,
    ((t.Size * t.Snatched) + (t.Size * 0.5 * t.Leechers)) AS Data,
    t.Size
  FROM torrents AS t
    LEFT JOIN torrents_group AS g ON g.ID = t.GroupID';

if ('all' == $Details || 'day' == $Details) {
    $TopTorrentsActiveLastDay = $Cache->get_value('top10tor_day_' . $Limit . $WhereSum . $GroupBySum);
    if (false === $TopTorrentsActiveLastDay) {
        if ($Cache->get_query_lock('top10')) {
            $DayAgo = time_minus(86400);
            $Query = $BaseQuery . ' WHERE t.Seeders>0 AND ';
            if (!empty($Where)) {
                $Query .= $Where . ' AND ';
            }
            $Query .= "
        t.Time>'$DayAgo'
        $GroupBy
        ORDER BY (t.Seeders + t.Leechers) DESC
        LIMIT $Limit;";
            $DB->query($Query);
            $TopTorrentsActiveLastDay = $DB->to_array(false, MYSQLI_NUM);
            $Cache->cache_value('top10tor_day_' . $Limit . $WhereSum . $GroupBySum, $TopTorrentsActiveLastDay, 3600 * 2);
            $Cache->clear_query_lock('top10');
        } else {
            $TopTorrentsActiveLastDay = false;
        }
    }
    generate_torrent_table('Most Active Torrents Uploaded in the Past Day', 'day', $TopTorrentsActiveLastDay, $Limit);
}
if ('all' == $Details || 'week' == $Details) {
    $TopTorrentsActiveLastWeek = $Cache->get_value('top10tor_week_' . $Limit . $WhereSum . $GroupBySum);
    if (false === $TopTorrentsActiveLastWeek) {
        if ($Cache->get_query_lock('top10')) {
            $WeekAgo = time_minus(604800);
            $Query = $BaseQuery . ' WHERE ';
            if (!empty($Where)) {
                $Query .= $Where . ' AND ';
            }
            $Query .= "
        t.Time>'$WeekAgo'
        $GroupBy
        ORDER BY (t.Seeders + t.Leechers) DESC
        LIMIT $Limit;";
            $DB->query($Query);
            $TopTorrentsActiveLastWeek = $DB->to_array(false, MYSQLI_NUM);
            $Cache->cache_value('top10tor_week_' . $Limit . $WhereSum . $GroupBySum, $TopTorrentsActiveLastWeek, 3600 * 6);
            $Cache->clear_query_lock('top10');
        } else {
            $TopTorrentsActiveLastWeek = false;
        }
    }
    generate_torrent_table('Most Active Torrents Uploaded in the Past Week', 'week', $TopTorrentsActiveLastWeek, $Limit);
}

if ('all' == $Details || 'month' == $Details) {
    $TopTorrentsActiveLastMonth = $Cache->get_value('top10tor_month_' . $Limit . $WhereSum . $GroupBySum);
    if (false === $TopTorrentsActiveLastMonth) {
        if ($Cache->get_query_lock('top10')) {
            $Query = $BaseQuery . ' WHERE ';
            if (!empty($Where)) {
                $Query .= $Where . ' AND ';
            }
            $Query .= "
        t.Time > NOW() - INTERVAL 1 MONTH
        $GroupBy
        ORDER BY (t.Seeders + t.Leechers) DESC
        LIMIT $Limit;";
            $DB->query($Query);
            $TopTorrentsActiveLastMonth = $DB->to_array(false, MYSQLI_NUM);
            $Cache->cache_value('top10tor_month_' . $Limit . $WhereSum . $GroupBySum, $TopTorrentsActiveLastMonth, 3600 * 6);
            $Cache->clear_query_lock('top10');
        } else {
            $TopTorrentsActiveLastMonth = false;
        }
    }
    generate_torrent_table('Most Active Torrents Uploaded in the Past Month', 'month', $TopTorrentsActiveLastMonth, $Limit);
}

if ('all' == $Details || 'year' == $Details) {
    $TopTorrentsActiveLastYear = $Cache->get_value('top10tor_year_' . $Limit . $WhereSum . $GroupBySum);
    if (false === $TopTorrentsActiveLastYear) {
        if ($Cache->get_query_lock('top10')) {
            // IMPORTANT NOTE - we use WHERE t.Seeders>200 in order to speed up this query. You should remove it!
            $Query = $BaseQuery . ' WHERE ';
            if ('all' == $Details && !$Filtered) {
//        $Query .= 't.Seeders>=200 AND ';
                if (!empty($Where)) {
                    $Query .= $Where . ' AND ';
                }
            } elseif (!empty($Where)) {
                $Query .= $Where . ' AND ';
            }
            $Query .= "
        t.Time > NOW() - INTERVAL 1 YEAR
        $GroupBy
        ORDER BY (t.Seeders + t.Leechers) DESC
        LIMIT $Limit;";
            $DB->query($Query);
            $TopTorrentsActiveLastYear = $DB->to_array(false, MYSQLI_NUM);
            $Cache->cache_value('top10tor_year_' . $Limit . $WhereSum . $GroupBySum, $TopTorrentsActiveLastYear, 3600 * 6);
            $Cache->clear_query_lock('top10');
        } else {
            $TopTorrentsActiveLastYear = false;
        }
    }
    generate_torrent_table('Most Active Torrents Uploaded in the Past Year', 'year', $TopTorrentsActiveLastYear, $Limit);
}

if ('all' == $Details || 'overall' == $Details) {
    $TopTorrentsActiveAllTime = $Cache->get_value('top10tor_overall_' . $Limit . $WhereSum . $GroupBySum);
    if (false === $TopTorrentsActiveAllTime) {
        if ($Cache->get_query_lock('top10')) {
            // IMPORTANT NOTE - we use WHERE t.Seeders>500 in order to speed up this query. You should remove it!
            $Query = $BaseQuery;
            if ('all'==$Details && !$Filtered) {
//        $Query .= "t.Seeders>=500 ";
                if (!empty($Where)) {
                    $Query .= ' WHERE ' . $Where;
                }
            } elseif (!empty($Where)) {
                $Query .= ' WHERE ' . $Where;
            }
            $Query .= "
        $GroupBy
        ORDER BY (t.Seeders + t.Leechers) DESC
        LIMIT $Limit;";
            $DB->query($Query);
            $TopTorrentsActiveAllTime = $DB->to_array(false, MYSQLI_NUM);
            $Cache->cache_value('top10tor_overall_' . $Limit . $WhereSum . $GroupBySum, $TopTorrentsActiveAllTime, 3600 * 6);
            $Cache->clear_query_lock('top10');
        } else {
            $TopTorrentsActiveAllTime = false;
        }
    }
    generate_torrent_table('Most Active Torrents of All Time', 'overall', $TopTorrentsActiveAllTime, $Limit);
}

if (('all' == $Details || 'snatched' == $Details) && !$Filtered) {
    $TopTorrentsSnatched = $Cache->get_value('top10tor_snatched_' . $Limit . $WhereSum . $GroupBySum);
    if (false === $TopTorrentsSnatched) {
        if ($Cache->get_query_lock('top10')) {
            $Query = $BaseQuery;
            if (!empty($Where)) {
                $Query .= ' WHERE ' . $Where;
            }
            $Query .= "
        $GroupBy
        ORDER BY t.Snatched DESC
        LIMIT $Limit;";
            $DB->query($Query);
            $TopTorrentsSnatched = $DB->to_array(false, MYSQLI_NUM);
            $Cache->cache_value('top10tor_snatched_' . $Limit . $WhereSum . $GroupBySum, $TopTorrentsSnatched, 3600 * 6);
            $Cache->clear_query_lock('top10');
        } else {
            $TopTorrentsSnatched = false;
        }
    }
    generate_torrent_table('Most Snatched Torrents', 'snatched', $TopTorrentsSnatched, $Limit);
}

if (('all' == $Details || 'data' == $Details) && !$Filtered) {
    $TopTorrentsTransferred = $Cache->get_value('top10tor_data_' . $Limit . $WhereSum . $GroupBySum);
    if (false === $TopTorrentsTransferred) {
        if ($Cache->get_query_lock('top10')) {
            // IMPORTANT NOTE - we use WHERE t.Snatched>100 in order to speed up this query. You should remove it!
            $Query = $BaseQuery;
            if ('all'==$Details) {
//        $Query .= " WHERE t.Snatched>=100 ";
                if (!empty($Where)) {
                    $Query .= ' WHERE ' . $Where;
                }
            }
            $Query .= "
        $GroupBy
        ORDER BY Data DESC
        LIMIT $Limit;";
            $DB->query($Query);
            $TopTorrentsTransferred = $DB->to_array(false, MYSQLI_NUM);
            $Cache->cache_value('top10tor_data_' . $Limit . $WhereSum . $GroupBySum, $TopTorrentsTransferred, 3600 * 6);
            $Cache->clear_query_lock('top10');
        } else {
            $TopTorrentsTransferred = false;
        }
    }
    generate_torrent_table('Most Data Transferred Torrents', 'data', $TopTorrentsTransferred, $Limit);
}

if (('all' == $Details || 'seeded' == $Details) && !$Filtered) {
    $TopTorrentsSeeded = $Cache->get_value('top10tor_seeded_' . $Limit . $WhereSum . $GroupBySum);
    if (false === $TopTorrentsSeeded) {
        if ($Cache->get_query_lock('top10')) {
            $Query = $BaseQuery;
            if (!empty($Where)) {
                $Query .= ' WHERE ' . $Where;
            }
            $Query .= "
        $GroupBy
        ORDER BY t.Seeders DESC
        LIMIT $Limit;";
            $DB->query($Query);
            $TopTorrentsSeeded = $DB->to_array(false, MYSQLI_NUM);
            $Cache->cache_value('top10tor_seeded_' . $Limit . $WhereSum . $GroupBySum, $TopTorrentsSeeded, 3600 * 6);
            $Cache->clear_query_lock('top10');
        } else {
            $TopTorrentsSeeded = false;
        }
    }
    generate_torrent_table('Best Seeded Torrents', 'seeded', $TopTorrentsSeeded, $Limit);
}

?>
</div>
<?php
View::show_footer();

// generate a table based on data from most recent query to $DB
function generate_torrent_table($Caption, $Tag, $Details, $Limit)
{
    global $LoggedUser, $Categories, $ReleaseTypes, $GroupBy; ?>
    <h3>Top <?="$Limit $Caption"?>
<?php  if (empty($_GET['advanced'])) { ?>
    <small class="top10_quantity_links">
<?php
    switch ($Limit) {
      case 100: ?>
        - <a href="top10.php?details=<?=$Tag?>" class="brackets">Top 10</a>
        - <span class="brackets">Top 100</span>
        - <a href="top10.php?type=torrents&amp;limit=250&amp;details=<?=$Tag?>" class="brackets">Top 250</a>
<?php        break;
      case 250: ?>
        - <a href="top10.php?details=<?=$Tag?>" class="brackets">Top 10</a>
        - <a href="top10.php?type=torrents&amp;limit=100&amp;details=<?=$Tag?>" class="brackets">Top 100</a>
        - <span class="brackets">Top 250</span>
<?php        break;
      default: ?>
        - <span class="brackets">Top 10</span>
        - <a href="top10.php?type=torrents&amp;limit=100&amp;details=<?=$Tag?>" class="brackets">Top 100</a>
        - <a href="top10.php?type=torrents&amp;limit=250&amp;details=<?=$Tag?>" class="brackets">Top 250</a>
<?php    } ?>
    </small>
<?php  } ?>
    </h3>
  <table class="torrent_table cats numbering border">
  <tr class="colhead">
    <td class="center" style="width: 15px;"></td>
    <td class="cats_col"></td>
    <td>Name</td>
    <td style="text-align: right;">Size</td>
    <td style="text-align: right;">Data</td>
    <td style="text-align: right;" class="sign snatches"><svg width="15" height="15" fill="white" class="tooltip" alt="Snatches" title="Snatches" viewBox="3 0 88 98"><path d="M20 20 A43 43,0,1,0,77 23 L90 10 L55 10 L55 45 L68 32 A30.27 30.27,0,1,1,28 29"></path></svg></td>
    <td style="text-align: right;" class="sign seeders"><svg width="11" height="15" fill="white" class="tooltip" alt="Seeders" title="Seeders"><polygon points="0,7 5.5,0 11,7 8,7 8,15 3,15 3,7"></polygon></svg></td>
    <td style="text-align: right;" class="sign leechers"><svg width="11" height="15" fill="white" class="tooltip" alt="Leechers" title="Leechers"><polygon points="0,8 5.5,15 11,8 8,8 8,0 3,0 3,8"></polygon></svg></td>
    <td style="text-align: right;">Peers</td>
  </tr>
<?php
  // Server is already processing a top10 query. Starting another one will make things slow
  if (false === $Details) {
      ?>
    <tr class="row">
      <td colspan="9" class="center">
        Server is busy processing another top list request. Please try again in a minute.
      </td>
    </tr>
    </table><br />
<?php
    return;
  }
    // in the unlikely event that query finds 0 rows...
    if (empty($Details)) {
        ?>
    <tr class="row">
      <td colspan="9" class="center">
        Found no torrents matching the criteria.
      </td>
    </tr>
    </table><br />
<?php
    return;
    }
    $Rank = 0;
    foreach ($Details as $Detail) {
        $GroupIDs[] = $Detail[1];
    }
    $Artists = Artists::get_artists($GroupIDs);

    foreach ($Details as $Detail) {
        [$TorrentID, $GroupID, $GroupName, $GroupNameRJ, $GroupNameJP, $GroupCategoryID, $WikiImage, $TagsList,
      $Media, $Year, $Snatched, $Seeders, $Leechers, $Data, $Size] = $Detail;

        $IsBookmarked = Bookmarks::has_bookmarked('torrent', $GroupID);
        $IsSnatched = Torrents::has_snatched($TorrentID);

        $Rank++;

        // generate torrent's title
        $DisplayName = '';

        if (!empty($Artists[$GroupID])) {
            $DisplayName = Artists::display_artists($Artists[$GroupID], true, true);
        }

        $DisplayName .= "<a href=\"torrents.php?id=$GroupID&amp;torrentid=$TorrentID\" ";
        if (!isset($LoggedUser['CoverArt']) || $LoggedUser['CoverArt']) {
            $DisplayName .= 'data-cover="' . ImageTools::process($WikiImage, 'thumb') . '" ';
        }


        $Name = empty($GroupName) ? (empty($GroupNameRJ) ? $GroupNameJP : $GroupNameRJ) : $GroupName;
        $DisplayName .= "dir=\"ltr\">$Name</a>";

        // append extra info to torrent title
        $ExtraInfo = '';
        $AddExtra = '';
        if (empty($GroupBy)) {
            if ($Media) {
                $ExtraInfo .= $AddExtra . $Media;
                $AddExtra = ' / ';
            }
            if ($Year > 0) {
                $ExtraInfo .= $AddExtra . $Year;
                $AddExtra = ' ';
            }
            if ($IsSnatched) {
                if (5 != $GroupCategoryID) {
                    $ExtraInfo .= ' / ';
                }
                $ExtraInfo .= Format::torrent_label('Snatched!');
            }
            if ('' != $ExtraInfo) {
                $ExtraInfo = "- [$ExtraInfo]";
            }
        }

        $TorrentTags = new Tags($TagsList);

        //Get report info, use the cache if available, if not, add to it.
        $Reported = false;
        $Reports = Torrents::get_reports($TorrentID);
        if (count($Reports) > 0) {
            $Reported = true;
        }

        // print row?>
  <tr class="torrent row<?=($IsBookmarked ? ' bookmarked' : '') . ($IsSnatched ? ' snatched_torrent' : '')?>">
    <td style="padding: 8px; text-align: center;"><strong><?=$Rank?></strong></td>
    <td class="center cats_col"><div title="<?=Format::pretty_category($GroupCategoryID)?>" class="tooltip <?=Format::css_category($GroupCategoryID)?>"></div></td>
    <td class="big_info">
      <div class="group_info clear">

        <span><a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download" class="brackets tooltip">DL</a></span>

        <?=$DisplayName?> <?=$ExtraInfo?><?php if ($Reported) { ?> - <strong class="torrent_label tl_reported">Reported</strong><?php } ?>
<?php
    if ($IsBookmarked) {
        ?>
        <span class="remove_bookmark float_right">
          <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="bookmarklink_torrent_<?=$GroupID?> brackets" onclick="Unbookmark('torrent', <?=$GroupID?>, 'Bookmark'); return false;">Remove bookmark</a>
        </span>
<?php
    } else { ?>
        <span class="add_bookmark float_right">
          <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="bookmarklink_torrent_<?=$GroupID?> brackets" onclick="Bookmark('torrent', <?=$GroupID?>, 'Remove bookmark'); return false;">Bookmark</a>
        </span>
<?php    } ?>
        <div class="tags"><?=$TorrentTags->format()?></div>
      </div>
    </td>
    <td class="number_column nobr"><?=Format::get_size($Size)?></td>
    <td class="number_column nobr"><?=Format::get_size($Data)?></td>
    <td class="number_column"><?=number_format((double)$Snatched)?></td>
    <td class="number_column"><?=number_format((double)$Seeders)?></td>
    <td class="number_column"><?=number_format((double)$Leechers)?></td>
    <td class="number_column"><?=number_format($Seeders + $Leechers)?></td>
  </tr>
<?php
    } //foreach ($Details as $Detail)
?>
  </table><br />
<?php
}
?>
