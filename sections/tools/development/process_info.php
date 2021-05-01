<?php
declare(strict_types=1);
if (!check_perms('site_debug')) {
    error(403);
}
View::show_header('PHP Processes');
$cmd = 'ps -C php-fpm -o pid --no-header';
$PIDList = trim($cmd);
$PIDs = explode("\n", $PIDList);
$Debug->log_var($PIDList, 'PID list');
$Debug->log_var($PIDs, 'PIDs');
?>
    <div class="thin">
        <table class="process_info">
            <colgroup>
                <col class="process_info_pid"/>
                <col class="process_info_data"/>
            </colgroup>
            <tr class="colhead_dark">
                <td colspan="2">
                    <?= count($PIDs) . ' processes' ?>
                </td>
            </tr>
            <?php
            foreach ($PIDs as $PID) {
                $PID = trim($PID);
                if (!$ProcessInfo = $Cache->get_value(sprintf('php_%s', $PID))) {
                    continue;
                } ?>
                <tr>
                    <td>
                        <?= $PID ?>
                    </td>
                    <td>
                        <pre><?php print_r($ProcessInfo) ?></pre>
                    </td>
                </tr>
                <?php
            } ?>
        </table>
    </div>
<?php
View::show_footer();
