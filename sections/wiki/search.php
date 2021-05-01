<?php declare(strict_types=1);
if (empty($_GET['nojump'])) {
    $ArticleID = Wiki::alias_to_id($_GET['search']);
    if ($ArticleID) {
        //Found the article!
        header('Location: wiki.php?action=article&id=' . $ArticleID);
        die();
    }
}

define('ARTICLES_PER_PAGE', 25);
[$Page, $Limit] = Format::page_limit(ARTICLES_PER_PAGE);

$OrderVals = ['Title', 'Created', 'Edited'];
$WayVals = ['Ascending', 'Descending'];
$TypeTable = ['Title'=>'Title', 'Body'=>'Body'];
$OrderTable = ['Title'=>'Title', 'Created'=>'ID', 'Edited'=>'Date'];
$WayTable = ['Ascending'=>'ASC', 'Descending'=>'DESC'];

// What are we looking for? Let's make sure it isn't dangerous.
$Search = db_string(trim($_GET['search']));

if (!in_array($Type, ['Title', 'Body'], true)) {
    $Type = 'Title';
}

// Break search string down into individual words
$Words = explode(' ', $Search);

$Type = $TypeTable[$_GET['type']];
if ('' === $Type) {
    $Type = 'Title';
}

$Order = $OrderTable[$_GET['order']];
if ('' === $Order) {
    $Order = 'ID';
}

$Way = $WayTable[$_GET['way']];
if ('' === $Way) {
    $Way = 'DESC';
}

$SQL = "
  SELECT
    SQL_CALC_FOUND_ROWS
    ID,
    Title,
    Date,
    Author
  FROM wiki_articles
  WHERE MinClassRead <= '" . $LoggedUser['EffectiveClass'] . "'";
if ('' != $Search) {
    $SQL .= sprintf(' AND %s LIKE \'%', $Type);
    $SQL .= implode(sprintf('%\' AND %s LIKE \'%', $Type), $Words);
    $SQL .= "%' ";
}

$SQL .= "
  ORDER BY {$Order} {$Way}
  LIMIT {$Limit} ";
$RS = $DB->query($SQL);
$DB->query("
  SELECT FOUND_ROWS()");
[$NumResults] = $DB->next_record();

View::show_header('Search articles');
$DB->set_query_id($RS);
?>
<div class="thin">
  <div class="header">
    <h2>Search articles</h2>
    <div class="linkbox">
      <a href="wiki.php?action=create&amp;alias=<?=display_str(Wiki::normalize_alias($_GET['search']))?>" class="brackets">Create an article</a>
    </div>
  </div>
  <div>
    <form action="" method="get">
      <div>
        <input type="hidden" name="action" value="search" />
        <input type="hidden" name="nojump" value="1" />
      </div>
      <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
        <tr>
          <td class="label"><label for="search"><strong>Search for:</strong></label></td>
          <td colspan="3">
            <input type="search" name="search" id="search" size="70" value="<?=display_str($_GET['search'])?>" />
          </td>
        </tr>
        <tr>
          <td class="label"><strong>Search in:</strong></td>
          <td>
            <label><input type="radio" name="type" value="Title" <?php if ('Title' == $Type) {
    echo 'checked="checked" ';
} ?>/> Title</label>
            <label><input type="radio" name="type" value="Body" <?php if ('Body' == $Type) {
    echo 'checked="checked" ';
} ?>/> Body</label>
          </td>
          <td class="label"><strong>Order by:</strong></td>
          <td>
            <select name="order">
<?php          foreach ($OrderVals as $Cur) { ?>
              <option value="<?=$Cur?>"<?php if ($_GET['order'] == $Cur || (!$_GET['order'] && 'Time' == $Cur)) {
    echo ' selected="selected"';
} ?>><?=$Cur?></option>
<?php          } ?>
            </select>
            <select name="way">
<?php          foreach ($WayVals as $Cur) { ?>
              <option value="<?=$Cur?>"<?php if ($_GET['way'] == $Cur || (!$_GET['way'] && 'Descending' == $Cur)) {
    echo ' selected="selected"';
} ?>><?=$Cur?></option>
<?php          } ?>
            </select>
          </td>
        </tr>
        <tr>
          <td colspan="4" class="center">
            <input type="submit" value="Search" />
          </td>
        </tr>
      </table>
    </form>
  </div>
  <br />
<?php
  $Pages = Format::get_pages($Page, $NumResults, ARTICLES_PER_PAGE);
  if ($Pages) { ?>
  <div class="linkbox pager"><?=($Pages)?></div>
<?php  } ?>
<table width="100%">
  <tr class="colhead">
    <td>Article</td>
    <td>Last updated on</td>
    <td>Last edited by</td>
  </tr>
<?php  while ([$ID, $Title, $Date, $UserID] = $DB->next_record()) { ?>
  <tr>
    <td><a href="wiki.php?action=article&amp;id=<?=$ID?>"><?=$Title?></a></td>
    <td><?=$Date?></td>
    <td><?=Users::format_username($UserID, false, false, false)?></td>
  </tr>
<?php  } ?>
</table>
  <div class="linkbox"><?=$Pages?></div>
</div>
<?php View::show_footer(); ?>
