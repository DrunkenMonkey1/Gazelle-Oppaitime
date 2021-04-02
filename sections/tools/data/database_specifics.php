<?php
if (!check_perms('site_debug')) {
    error(403);
}

//View schemas
if (!empty($_GET['table'])) {
    $DB->query('SHOW TABLES');
    $Tables =$DB->collect('Tables_in_' . SQLDB);
    if (!in_array($_GET['table'], $Tables, true)) {
        error(0);
    }
    $DB->query('SHOW CREATE TABLE ' . db_string($_GET['table']));
    [, $Schema] = $DB->next_record(MYSQLI_NUM, false);
    header('Content-type: text/plain');
    die($Schema);
}

//Cache the tables for 4 hours, makes sorting faster
if (!$Tables = $Cache->get_value('database_table_stats')) {
    $DB->query('SHOW TABLE STATUS');
    $Tables =$DB->to_array();
    $Cache->cache_value('database_table_stats', $Tables, 3600 * 4);
}

require SERVER_ROOT . '/classes/charts.class.php';
$Pie = new PIE_CHART(750, 400, ['Other'=>1, 'Percentage'=>1, 'Sort'=>1]);

//Begin sorting
$Sort = [];
switch (empty($_GET['order_by']) ? '' : $_GET['order_by']) {
  case 'name':
    foreach ($Tables as $Key => $Value) {
        $Pie->add($Value[0], $Value[6] + $Value[8]);
        $Sort[$Key] = $Value[0];
    }
    break;
  case 'engine':
    foreach ($Tables as $Key => $Value) {
        $Pie->add($Value[0], $Value[6] + $Value[8]);
        $Sort[$Key] = $Value[1];
    }
    break;
  case 'rows':
    foreach ($Tables as $Key => $Value) {
        $Pie->add($Value[0], $Value[4]);
        $Sort[$Key] = $Value[4];
    }
    break;
  case 'rowsize':
    foreach ($Tables as $Key => $Value) {
        $Pie->add($Value[0], $Value[5]);
        $Sort[$Key] = $Value[5];
    }
    break;
  case 'datasize':
    foreach ($Tables as $Key => $Value) {
        $Pie->add($Value[0], $Value[6]);
        $Sort[$Key] = $Value[6];
    }
    break;
  case 'indexsize':
    foreach ($Tables as $Key => $Value) {
        $Pie->add($Value[0], $Value[8]);
        $Sort[$Key] = $Value[8];
    }
    break;
  case 'totalsize':
  default:
    foreach ($Tables as $Key => $Value) {
        $Pie->add($Value[0], $Value[6] + $Value[8]);
        $Sort[$Key] = $Value[6] + $Value[8];
    }
}
$Pie->generate();

if (!empty($_GET['order_way']) && 'asc' == $_GET['order_way']) {
    $SortWay = SORT_ASC;
} else {
    $SortWay = SORT_DESC;
}

array_multisort($Sort, $SortWay, $Tables);
//End sorting

View::show_header('Database Specifics');
?>
<h3>Breakdown</h3>
<div class="box pad center">
  <img src="<?=$Pie->url()?>" />
</div>
<br />
<table>
  <tr class="colhead">
    <td><a href="tools.php?action=database_specifics&amp;order_by=name&amp;order_way=<?=(!empty($_GET['order_by']) && 'name' == $_GET['order_by'] && !empty($_GET['order_way']) && 'desc' == $_GET['order_way']) ? 'asc' : 'desc'?>">Name</a></td>
    <td><a href="tools.php?action=database_specifics&amp;order_by=engine&amp;order_way=<?=(!empty($_GET['order_by']) && 'engine' == $_GET['order_by'] && !empty($_GET['order_way']) && 'desc' == $_GET['order_way']) ? 'asc' : 'desc'?>">Engine</a></td>
    <td><a href="tools.php?action=database_specifics&amp;order_by=rows&amp;order_way=<?=(!empty($_GET['order_by']) && 'rows' == $_GET['order_by'] && !empty($_GET['order_way']) && 'desc' == $_GET['order_way']) ? 'asc' : 'desc'?>">Rows</td>
    <td><a href="tools.php?action=database_specifics&amp;order_by=rowsize&amp;order_way=<?=(!empty($_GET['order_by']) && 'rowsize' == $_GET['order_by'] && !empty($_GET['order_way']) && 'desc' == $_GET['order_way']) ? 'asc' : 'desc'?>">Row Size</a></td>
    <td><a href="tools.php?action=database_specifics&amp;order_by=datasize&amp;order_way=<?=(!empty($_GET['order_by']) && 'datasize' == $_GET['order_by'] && !empty($_GET['order_way']) && 'desc' == $_GET['order_way']) ? 'asc' : 'desc'?>">Data Size</a></td>
    <td><a href="tools.php?action=database_specifics&amp;order_by=indexsize&amp;order_way=<?=(!empty($_GET['order_by']) && 'indexsize' == $_GET['order_by'] && !empty($_GET['order_way']) && 'desc' == $_GET['order_way']) ? 'asc' : 'desc'?>">Index Size</a></td>
    <td><a href="tools.php?action=database_specifics&amp;order_by=totalsize&amp;order_way=<?=(!empty($_GET['order_by']) && 'totalsize' == $_GET['order_by'] && !empty($_GET['order_way']) && 'desc' == $_GET['order_way']) ? 'asc' : 'desc'?>">Total Size</td>
    <td>Tools</td>
  </tr>
<?php
$TotalRows = 0;
$TotalDataSize = 0;
$TotalIndexSize = 0;
foreach ($Tables as $Table) {
    [$Name, $Engine, , , $Rows, $RowSize, $DataSize, , $IndexSize] = $Table;

    $TotalRows += $Rows;
    $TotalDataSize += $DataSize;
    $TotalIndexSize += $IndexSize; ?>
  <tr class="row">
    <td><?=display_str($Name)?></td>
    <td><?=display_str($Engine)?></td>
    <td><?=number_format($Rows)?></td>
    <td><?=Format::get_size($RowSize)?></td>
    <td><?=Format::get_size($DataSize)?></td>
    <td><?=Format::get_size($IndexSize)?></td>
    <td><?=Format::get_size($DataSize + $IndexSize)?></td>
    <td><a href="tools.php?action=database_specifics&amp;table=<?=display_str($Name)?>" class="brackets">Schema</a></td>
  </tr>
<?php
}
?>
  <tr>
    <td></td>
    <td></td>
    <td><?=number_format($TotalRows)?></td>
    <td></td>
    <td><?=Format::get_size($TotalDataSize)?></td>
    <td><?=Format::get_size($TotalIndexSize)?></td>
    <td><?=Format::get_size($TotalDataSize + $TotalIndexSize)?></td>
    <td></td>
  </tr>
</table>
<?php
View::show_footer();
