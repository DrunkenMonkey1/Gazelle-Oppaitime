<?php
$Servers = array_diff(scandir(SERVER_ROOT . '/misc/heartbeat', 1), ['.', '..']);
View::show_header('Network status');

?>
<h2>Network Status</h2>
<div style="font-size: 0; text-align: center;">
<div class="net_box">
  <div class="head">Webserver</div>
  <div class="box pad center">
    <span class="r10">Online</span>
  </div>
</div>
<?php
foreach ($Servers as $Server) {
    $Contents = file_get_contents(SERVER_ROOT . '/misc/heartbeat/' . $Server);
    if ('Tracker' == substr($Server, 0, 7) || 'IRC' == substr($Server, 0, 3)) {
        $Contents = explode("\n", $Contents);
        $Contents = '<span class="' . (((time() - (int)array_slice($Contents, -2)[0]) < 610) ? 'r10">Online' : 'r03">Offline') . '</span>' . (('Tracker'==substr($Server, 0, 7))?'<br><br>' . ('Backup From: ' . time_diff((int)$Contents[0], 2, false)):'');
    } elseif ('Backup' == substr($Server, 0, 6)) {
        $Contents = 'Backup From: ' . time_diff((int)$Contents, 2, false);
    } ?>
  <div class="net_box">
    <div class="head"><?=$Server?></div>
    <div class="box pad center">
      <span><?=$Contents?></span>
    </div>
  </div>
  <?php
  echo('IRC' == $Server ? '<br>' : '');
} ?>
</div>
<?php View::show_footer();
