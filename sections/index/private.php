<?php
Text::$TOC = true;

$NewsCount = 5;
if (!$News = $Cache->get_value('news')) {
    $DB->query("
    SELECT
      ID,
      Title,
      Body,
      Time
    FROM news
    ORDER BY Time DESC
    LIMIT $NewsCount");
    $News = $DB->to_array(false, MYSQLI_NUM, false);
    $Cache->cache_value('news', $News, 3600 * 24 * 30);
    $Cache->cache_value('news_latest_id', $News[0][0], 0);
    $Cache->cache_value('news_latest_title', $News[0][1], 0);
}

if ($LoggedUser['LastReadNews'] != $News[0][0] && count($News) > 0) {
    $Cache->begin_transaction("user_info_heavy_$UserID");
    $Cache->update_row(false, ['LastReadNews' => $News[0][0]]);
    $Cache->commit_transaction(0);
    $DB->query("
    UPDATE users_info
    SET LastReadNews = '" . $News[0][0] . "'
    WHERE UserID = $UserID");
    $LoggedUser['LastReadNews'] = $News[0][0];
}

View::show_header('News', 'bbcode,news_ajax');
?>
<div class="thin">
  <div class="sidebar">
<?php
if (check_perms('users_mod')) {
    ?>
    <div class="box">
      <div class="head colhead_dark">
        <strong><a href="staffblog.php">Latest staff blog posts</a></strong>
      </div>
<?php
if (($Blog = $Cache->get_value('staff_blog')) === false) {
        $DB->query("
    SELECT
      b.ID,
      um.Username,
      b.Title,
      b.Body,
      b.Time
    FROM staff_blog AS b
      LEFT JOIN users_main AS um ON b.UserID = um.ID
    ORDER BY Time DESC");
        $Blog = $DB->to_array(false, MYSQLI_NUM);
        $Cache->cache_value('staff_blog', $Blog, 1_209_600);
    }
    if (($SBlogReadTime = $Cache->get_value('staff_blog_read_' . $LoggedUser['ID'])) === false) {
        $DB->query("
    SELECT Time
    FROM staff_blog_visits
    WHERE UserID = " . $LoggedUser['ID']);
        $SBlogReadTime = ([$SBlogReadTime] = $DB->next_record()) ? strtotime($SBlogReadTime) : 0;
        $Cache->cache_value('staff_blog_read_' . $LoggedUser['ID'], $SBlogReadTime, 1_209_600);
    } ?>
      <ul class="stats nobullet">
<?php
$End = min(count($Blog), 5);
    for ($i = 0; $i < $End; $i++) {
        [$BlogID, $Author, $Title, $Body, $BlogTime] = $Blog[$i];
        $BlogTime = strtotime($BlogTime); ?>
        <li>
          <?=$SBlogReadTime < $BlogTime ? '<strong>' : ''?><?=($i + 1)?>.
          <a href="staffblog.php#blog<?=$BlogID?>"><?=$Title?></a>
          <?=$SBlogReadTime < $BlogTime ? '</strong>' : ''?>
        </li>
<?php
    } ?>
      </ul>
    </div>
<?php
} ?>
    <div class="box">
      <div class="head colhead_dark"><strong><a href="blog.php">Latest blog posts</a></strong></div>
<?php
if (($Blog = $Cache->get_value('blog')) === false) {
        $DB->query("
    SELECT
      b.ID,
      um.Username,
      b.UserID,
      b.Title,
      b.Body,
      b.Time,
      b.ThreadID
    FROM blog AS b
      LEFT JOIN users_main AS um ON b.UserID = um.ID
    ORDER BY Time DESC
    LIMIT 20");
        $Blog = $DB->to_array();
        $Cache->cache_value('blog', $Blog, 1_209_600);
    }
?>
      <ul class="stats nobullet">
<?php
$Limit = count($Blog) < 5 ? count($Blog) : 5;
for ($i = 0; $i < $Limit; $i++) {
    [$BlogID, $Author, $AuthorID, $Title, $Body, $BlogTime, $ThreadID] = $Blog[$i]; ?>
        <li>
          <?=($i + 1)?>. <a href="blog.php#blog<?=$BlogID?>"><?=$Title?></a>
        </li>
<?php
}
?>
      </ul>
    </div>
<?php
if (($Freeleeches = $Cache->get_value('shop_freeleech_list')) === false) {
    $DB->query("
    SELECT
      TorrentID,
      UNIX_TIMESTAMP(ExpiryTime),
      Name AS Name,
      WikiImage
    FROM shop_freeleeches AS sf
    LEFT JOIN torrents AS t on sf.TorrentID=t.ID
    LEFT JOIN torrents_group AS tg ON tg.ID=t.GroupID
    ORDER BY ExpiryTime ASC
    LIMIT 10");
    $Freeleeches = $DB->to_array();
    $Cache->cache_value('shop_freeleech_list', $Freeleeches, 1_209_600);
}
if (count($Freeleeches) > 0) {
    ?>
    <div class="box">
      <div class="head colhead_dark"><strong><a href="torrents.php?freetorrent=1&order_by=seeders&order_way=asc">Freeleeches</a></strong></div>
      <ul class="stats nobullet">
<?php
  foreach ($Freeleeches as $i => $Freeleech) {
        [$ID, $ExpiryTime, $Name, $Image] = $Freeleech;
        if ($ExpiryTime < time()) {
            continue;
        }
        $DisplayTime = '(' . str_replace(['year', 'month', 'week', 'day', 'hour', 'min', 'Just now', 's', ' '], ['y', 'M', 'w', 'd', 'h', 'm', '0m'], time_diff($ExpiryTime, 1, false)) . ') ';
        $DisplayName = '<a href="torrents.php?torrentid=' . $ID . '"';
        if (!isset($LoggedUser['CoverArt']) || $LoggedUser['CoverArt']) {
            $DisplayName .= ' data-cover="' . ImageTools::process($Image, 'thumb') . '"';
        }
        $DisplayName .= '>' . $Name . '</a>';
        ?>
        <li>
          <strong class="fl_time">
        <?=$DisplayTime?>
        ?></strong>
        <?=$DisplayName?>

        ?>
        </li>
<?php 
    } ?>
      </ul>
    </div>
<?php
}
?>
    <div class="box">
      <div class="head colhead_dark"><strong>Stats</strong></div>
      <ul class="stats nobullet">
<?php if (USER_LIMIT > 0) { ?>
        <li>Maximum users: <?=number_format(USER_LIMIT) ?></li>
<?php
}

if (($UserCount = $Cache->get_value('stats_user_count')) === false) {
    $DB->query("
    SELECT COUNT(ID)
    FROM users_main
    WHERE Enabled = '1'");
    [$UserCount] = $DB->next_record();
    $Cache->cache_value('stats_user_count', $UserCount, 86400);
}
$UserCount = (int)$UserCount;
?>
        <li>Enabled users: <?=number_format($UserCount)?> <a href="stats.php?action=users" class="brackets">Details</a></li>
<?php

if (($UserStats = $Cache->get_value('stats_users')) === false) {
    $DB->query("
    SELECT COUNT(ID)
    FROM users_main
    WHERE Enabled = '1'
      AND LastAccess > '" . time_minus(3600 * 24) . "'");
    [$UserStats['Day']] = $DB->next_record();

    $DB->query("
    SELECT COUNT(ID)
    FROM users_main
    WHERE Enabled = '1'
      AND LastAccess > '" . time_minus(3600 * 24 * 7) . "'");
    [$UserStats['Week']] = $DB->next_record();

    $DB->query("
    SELECT COUNT(ID)
    FROM users_main
    WHERE Enabled = '1'
      AND LastAccess > '" . time_minus(3600 * 24 * 30) . "'");
    [$UserStats['Month']] = $DB->next_record();

    $Cache->cache_value('stats_users', $UserStats, 0);
}
?>
        <li>Users active today: <?=number_format($UserStats['Day'])?> (<?=number_format($UserStats['Day'] / $UserCount * 100, 2)?>%)</li>
        <li>Users active this week: <?=number_format($UserStats['Week'])?> (<?=number_format($UserStats['Week'] / $UserCount * 100, 2)?>%)</li>
        <li>Users active this month: <?=number_format($UserStats['Month'])?> (<?=number_format($UserStats['Month'] / $UserCount * 100, 2)?>%)</li>
<?php

if (($TorrentCount = $Cache->get_value('stats_torrent_count')) === false) {
    $DB->query("
    SELECT COUNT(ID)
    FROM torrents");
    [$TorrentCount] = $DB->next_record();
    $Cache->cache_value('stats_torrent_count', $TorrentCount, 86400); // 1 day cache
}

if (($GroupCount = $Cache->get_value('stats_group_count')) === false) {
    $DB->query("
    SELECT COUNT(ID)
    FROM torrents_group");
    [$GroupCount] = $DB->next_record();
    $Cache->cache_value('stats_group_count', $GroupCount, 86400); // 1 day cache
}

if (($TorrentSizeTotal = $Cache->get_value('stats_torrent_size_total')) === false) {
    $DB->query("
    SELECT SUM(Size)
    FROM torrents");
    [$TorrentSizeTotal] = $DB->next_record();
    $Cache->cache_value('stats_torrent_size_total', $TorrentSizeTotal, 86400); // 1 day cache
}
?>
        <li>Total Size of Torrents: <?=Format::get_size($TorrentSizeTotal)?> </li>
<?php

if (($ArtistCount = $Cache->get_value('stats_artist_count')) === false) {
    $DB->query("
    SELECT COUNT(ArtistID)
    FROM artists_group");
    [$ArtistCount] = $DB->next_record();
    $Cache->cache_value('stats_artist_count', $ArtistCount, 86400); // 1 day cache
}

?>
        <li>Torrents: <?=number_format((int)$TorrentCount)?></li>
        <li>Torrent Groups: <?=number_format((int)$GroupCount)?></li>
        <li>Artists: <?=number_format((int)$ArtistCount)?></li>
<?php
//End Torrent Stats

if (($RequestStats = $Cache->get_value('stats_requests')) === false) {
    $DB->query("
    SELECT COUNT(ID)
    FROM requests");
    [$RequestCount] = $DB->next_record();
    $DB->query("
    SELECT COUNT(ID)
    FROM requests
    WHERE FillerID > 0");
    [$FilledCount] = $DB->next_record();
    $Cache->cache_value('stats_requests', [$RequestCount, $FilledCount], 11280);
} else {
    [$RequestCount, $FilledCount] = $RequestStats;
}

$RequestsFilledPercent = $RequestCount > 0 ? $FilledCount / $RequestCount * 100 : 0;

?>
        <li>Requests: <?=number_format((int)$RequestCount)?> (<?=number_format((int)$RequestsFilledPercent, 2)?>% filled)</li>
<?php

if ($SnatchStats = $Cache->get_value('stats_snatches')) {
    ?>
        <li>Snatches: <?=number_format($SnatchStats)?></li>
<?php
}

if (($PeerStats = $Cache->get_value('stats_peers')) === false) {
    //Cache lock!
    $PeerStatsLocked = $Cache->get_value('stats_peers_lock');
    if (!$PeerStatsLocked) {
        $Cache->cache_value('stats_peers_lock', 1, 30);
        $DB->query("
      SELECT IF(remaining=0,'Seeding','Leeching') AS Type, COUNT(uid)
      FROM xbt_files_users
      WHERE active = 1
      GROUP BY Type");
        $PeerCount = $DB->to_array(0, MYSQLI_NUM, false);
        $SeederCount = $PeerCount['Seeding'][1] ?: 0;
        $LeecherCount = $PeerCount['Leeching'][1] ?: 0;
        $Cache->cache_value('stats_peers', [$LeecherCount, $SeederCount], 604800); // 1 week cache
        $Cache->delete_value('stats_peers_lock');
    }
} else {
    $PeerStatsLocked = false;
    [$LeecherCount, $SeederCount] = $PeerStats;
}

if (!$PeerStatsLocked) {
    $Ratio = Format::get_ratio_html($SeederCount, $LeecherCount);
    $PeerCount = number_format($SeederCount + $LeecherCount);
    $SeederCount = number_format($SeederCount);
    $LeecherCount = number_format($LeecherCount);
} else {
    $PeerCount = $SeederCount = $LeecherCount = $Ratio = 'Server busy';
}
?>
        <li>Peers: <?=$PeerCount?></li>
        <li>Seeders: <?=$SeederCount?></li>
        <li>Leechers: <?=$LeecherCount?></li>
        <li>Seeder/leecher ratio: <?=$Ratio?></li>
      </ul>
    </div>
<?php
if (($TopicID = $Cache->get_value('polls_featured')) === false) {
    $DB->query("
    SELECT TopicID
    FROM forums_polls
    ORDER BY Featured DESC
    LIMIT 1");
    [$TopicID] = $DB->next_record();
    $Cache->cache_value('polls_featured', $TopicID, 0);
}
if ($TopicID) {
    if (($Poll = $Cache->get_value("polls_$TopicID")) === false) {
        $DB->query("
      SELECT Question, Answers, Featured, Closed
      FROM forums_polls
      WHERE TopicID = '$TopicID'");
        [$Question, $Answers, $Featured, $Closed] = $DB->next_record(MYSQLI_NUM, [1]);
        $Answers = unserialize($Answers);
        $DB->query("
      SELECT Vote, COUNT(UserID)
      FROM forums_polls_votes
      WHERE TopicID = '$TopicID'
        AND Vote != '0'
      GROUP BY Vote");
        $VoteArray = $DB->to_array(false, MYSQLI_NUM);

        $Votes = [];
        foreach ($VoteArray as $VoteSet) {
            [$Key, $Value] = $VoteSet;
            $Votes[$Key] = $Value;
        }

        for ($i = 1, $il = count($Answers); $i <= $il; ++$i) {
            if (!isset($Votes[$i])) {
                $Votes[$i] = 0;
            }
        }
        $Cache->cache_value("polls_$TopicID", [$Question, $Answers, $Votes, $Featured, $Closed], 0);
    } else {
        [$Question, $Answers, $Votes, $Featured, $Closed] = $Poll;
    }

    if (!empty($Votes)) {
        $TotalVotes = array_sum($Votes);
        $MaxVotes = max($Votes);
    } else {
        $TotalVotes = 0;
        $MaxVotes = 0;
    }

    $DB->query("
    SELECT Vote
    FROM forums_polls_votes
    WHERE UserID = '" . $LoggedUser['ID'] . "'
      AND TopicID = '$TopicID'");
    [$UserResponse] = $DB->next_record(); ?>
    <div class="box">
      <div class="head colhead_dark"><strong>Poll<?php if ($Closed) {
        echo ' [Closed]';
    } ?></strong></div>
      <div class="pad">
        <p><strong><?=display_str($Question)?></strong></p>
<?php  if (null !== $UserResponse || $Closed) { ?>
        <ul class="poll nobullet">
<?php    foreach (array_keys($Answers) as $i) {
        if ($TotalVotes > 0) {
            $Ratio = $Votes[$i] / $MaxVotes;
            $Percent = $Votes[$i] / $TotalVotes;
        } else {
            $Ratio = 0;
            $Percent = 0;
        } ?>          <li<?=((!empty($UserResponse) && ($UserResponse == $i))?' class="poll_your_answer"':'')?>><?=display_str($Answers[$i])?> (<?=number_format($Percent * 100, 2)?>%)</li>
          <li class="graph">
            <span class="center_poll" style="width: <?=round($Ratio * 140)?>px;"></span>
            <br />
          </li>
<?php
    } ?>
        </ul>
        <strong>Votes:</strong> <?=number_format($TotalVotes)?><br />
<?php  } else { ?>
        <div id="poll_container">
        <form class="vote_form" name="poll" id="poll" action="">
          <input type="hidden" name="action" value="poll" />
          <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
          <input type="hidden" name="topicid" value="<?=$TopicID?>" />
<?php    foreach (array_keys($Answers) as $i) { ?>
          <input type="radio" name="vote" id="answer_<?=$i?>" value="<?=$i?>" />
          <label for="answer_<?=$i?>"><?=display_str($Answers[$i])?></label><br />
<?php    } ?>
          <br /><input type="radio" name="vote" id="answer_0" value="0" /> <label for="answer_0">Blank&#8202;&mdash;&#8202;Show the results!</label><br /><br />
          <input type="button" onclick="ajax.post('index.php', 'poll', function(response) { $('#poll_container').raw().innerHTML = response } );" value="Vote" />
        </form>
        </div>
<?php  } ?>
        <br /><strong>Topic:</strong> <a href="forums.php?action=viewthread&amp;threadid=<?=$TopicID?>">Visit</a>
      </div>
    </div>
<?php
}
//polls();
?>
  </div>
  <div class="main_column">
<?php

$Recommend = $Cache->get_value('recommend');
$Recommend_artists = $Cache->get_value('recommend_artists');

if (!is_array($Recommend) || !is_array($Recommend_artists)) {
    $DB->query("
    SELECT
      tr.GroupID,
      tr.UserID,
      u.Username,
      tg.Name,
      tg.TagList
    FROM torrents_recommended AS tr
      JOIN torrents_group AS tg ON tg.ID = tr.GroupID
      LEFT JOIN users_main AS u ON u.ID = tr.UserID
    ORDER BY tr.Time DESC
    LIMIT 10");
    $Recommend = $DB->to_array();
    $Cache->cache_value('recommend', $Recommend, 1_209_600);

    $Recommend_artists = Artists::get_artists($DB->collect('GroupID'));
    $Cache->cache_value('recommend_artists', $Recommend_artists, 1_209_600);
}

if (count($Recommend) >= 4) {
    $Cache->increment('usage_index'); ?>
  <div class="box" id="recommended">
    <div class="head colhead_dark">
      <strong>Latest Vanity House additions</strong>
      <a data-toggle-target="#vanityhouse", data-toggle-replace="Hide" class="brackets">Show</a>
    </div>

    <table class="torrent_table hidden" id="vanityhouse">
<?php
  foreach ($Recommend as $Recommendations) {
      [$GroupID, $UserID, $Username, $GroupName, $TagList] = $Recommendations;
      $TagsStr = '';
      if ($TagList) {
          // No vanity.house tag.
          $Tags = explode(' ', str_replace('_', '.', $TagList));
          $TagLinks = [];
          foreach ($Tags as $Tag) {
              if ('vanity.house' == $Tag) {
                  continue;
              }
              $TagLinks[] = "<a href=\"torrents.php?action=basic&amp;taglist=$Tag\">$Tag</a> ";
          }
          $TagStr = "<br />\n<div class=\"tags\">" . implode(', ', $TagLinks) . '</div>';
      } ?>
      <tr>
        <td>
          <?=Artists::display_artists($Recommend_artists[$GroupID]) ?>
          <a href="torrents.php?id=<?=$GroupID?>"><?=$GroupName?></a> (by <?=Users::format_username($UserID, false, false, false)?>)
          <?=$TagStr?>
        </td>
      </tr>
<?php
  } ?>
    </table>
  </div>
<!-- END recommendations section -->
<?php
}
$Count = 0;
foreach ($News as $NewsItem) {
    [$NewsID, $Title, $Body, $NewsTime] = $NewsItem;
    if (strtotime($NewsTime) > time()) {
        continue;
    } ?>
    <div id="news<?=$NewsID?>" class="box news_post">
      <div class="head">
        <strong><?=Text::full_format($Title)?></strong> <?=time_diff($NewsTime); ?>
<?php  if (check_perms('admin_manage_news')) { ?>
        - <a href="tools.php?action=editnews&amp;id=<?=$NewsID?>" class="brackets">Edit</a>
<?php  } ?>
      <span class="float_right"><a data-toggle-target="#newsbody<?=$NewsID?>" data-toggle-replace="Show" class="brackets">Hide</a></span>
      </div>
      <div id="newsbody<?=$NewsID?>" class="pad"><?=Text::full_format($Body)?></div>
    </div>
<?php
  if (++$Count > ($NewsCount - 1)) {
      break;
  }
}
?>
    <div id="more_news" class="box">
      <div class="head">
        <em><span><a href="#" onclick="news_ajax(event, 3, <?=$NewsCount?>, <?=check_perms('admin_manage_news') ? 1 : 0; ?>); return false;">Click to load more news</a>.</span> To browse old news posts, <a href="forums.php?action=viewforum&amp;forumid=10">click here</a>.</em>
      </div>
    </div>
  </div>
</div>
<?php
View::show_footer(['disclaimer'=>true]);

function contest()
{
    global $DB, $Cache, $LoggedUser;

    [$Contest, $TotalPoints] = $Cache->get_value('contest');
    if (!$Contest) {
        $DB->query("
      SELECT
        UserID,
        SUM(Points),
        Username
      FROM users_points AS up
        JOIN users_main AS um ON um.ID = up.UserID
      GROUP BY UserID
      ORDER BY SUM(Points) DESC
      LIMIT 20");
        $Contest = $DB->to_array();

        $DB->query("
      SELECT SUM(Points)
      FROM users_points");
        [$TotalPoints] = $DB->next_record();

        $Cache->cache_value('contest', [$Contest, $TotalPoints], 600);
    } ?>
<!-- Contest Section -->
    <div class="box box_contest">
      <div class="head colhead_dark"><strong>Quality time scoreboard</strong></div>
      <div class="pad">
        <ol style="padding-left: 5px;">
<?php
  foreach ($Contest as $User) {
      [$UserID, $Points, $Username] = $User; ?>
          <li><?=Users::format_username($UserID, false, false, false)?> (<?=number_format($Points)?>)</li>
<?php
  } ?>
        </ol>
        Total uploads: <?=$TotalPoints?><br />
        <a href="index.php?action=scoreboard">Full scoreboard</a>
      </div>
    </div>
  <!-- END contest Section -->
<?php
} // contest()
?>
