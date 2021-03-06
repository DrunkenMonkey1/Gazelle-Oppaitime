<?php
declare(strict_types=1);
/*
 * This page creates previews of all supported stylesheets
 * SERVER_ROOT . '/' . STATIC_SERVER . 'stylespreview' must exist and be writable
 * Dependencies are PhantomJS (http://phantomjs.org/) and
 * ImageMagick (http://www.imagemagick.org/script/index.php)
 */
View::show_header('Rerender stylesheet gallery images');
$DB->query('
  SELECT
    ID,
    LOWER(REPLACE(Name," ","_")) AS Name,
    Name AS ProperName
  FROM stylesheets');
$Styles = $DB->to_array('ID', MYSQLI_BOTH);
$ImagePath = SERVER_ROOT . '/' . STATIC_SERVER . 'stylespreview';
?>
    <div class="thin">
        <h2>Rerender stylesheet gallery images</h2>
        <div class="sidebar">
            <div class="box box_info">
                <div class="head colhead_dark">Rendering parameters</div>
                <ul class="stats nobullet">
                    <li>Server root: <?= SERVER_ROOT ?></li>
                    <li>Static server: <?= STATIC_SERVER ?></li>
                    <li>Whoami: <?php echo(shell_exec('whoami')); ?></li>
                    <li>Path: <?php echo __DIR__; ?></li>
                    <li>Phantomjs ver: <?php echo(shell_exec('/usr/bin/phantomjs -v;')); ?></li>
                </ul>
            </div>
        </div>
        <div class="main_column">
            <div class="box">
                <div class="head">About rendering</div>
                <div class="pad">
                    <p>You are now rendering stylesheet gallery images.</p>
                    <p>The used parameters can be seen on the right, returned statuses are displayed below.</p>
                </div>
            </div>
            <div class="box">
                <div class="head">Rendering status</div>
                <div class="pad">
                    <?php
                    //set_time_limit(0);
                    foreach ($Styles as $Style) {
                        ?>
                        <div class="box">
                            <h6><?= $Style['Name'] ?></h6>
                            <p>Build preview:
                                <?php
                                $CmdLine = '/usr/bin/phantomjs "' . __DIR__ . '/render_build_preview.js" "' . SERVER_ROOT . '" "' . STATIC_SERVER . '" "' . $Style['Name'] . '" "' . dirname(__FILE__) . '"';
                                $run = shell_exec(escapeshellcmd($CmdLine));
                                if (!is_null($run)) {
                                    $BuildResult = json_decode($run, true);
                                    echo match ($BuildResult['status']) {
                                        0 => 'Success.',
                                        -1 => 'Err -1: Incorrect paths, are they passed correctly?',
                                        -2 => 'Err -2: Rendering base does not exist. Who broke things?',
                                        -3 => 'Err -3: No permission to write to preview folder.',
                                        -4 => 'Err -4: Failed to store specific preview file.',
                                        default => 'Err: Unknown error returned',
                                    };
                                }
                                ?>
                            </p>
                            <?php
                            //If build was successful, snap a preview.
                            if (0 === $BuildResult['status']) {
                                ?>
                                <p>Snap preview:
                                    <?php
                                    $CmdLine = '/usr/bin/phantomjs "' . dirname(__FILE__) . '/render_snap_preview.js" "' . SERVER_ROOT . '" "' . STATIC_SERVER . '" "' . $Style['Name'] . '" "' . dirname(__FILE__) . '"';
                                    $SnapResult = json_decode(shell_exec(escapeshellcmd($CmdLine)), true);
                                    switch ($SnapResult['status']) {
                                        case 0:
                                            echo 'Success.';
                                            $CmdLine = '/usr/bin/convert "' . $ImagePath . '/full_' . $Style['Name'] . '.png" -filter Box -resize 40% -quality 94 "' . $ImagePath . '/thumb_' . $Style['Name'] . '.png"';
                                            $ResizeResult = shell_exec(escapeshellcmd($CmdLine));
                                            if (null !== $ResizeResult) {
                                                echo ' But failed to resize image';
                                            }
                                            break;
                                        case -1:
                                            echo 'Err -1: Incorrect paths. Are they passed correctly? Do all folders exist?';
                                            break;
                                        case -2:
                                            echo 'Err -2: Preview file does not exist; running things in the wrong order perhaps?';
                                            break;
                                        case -3:
                                            echo 'Err -3: Preview is empty; did it get created properly?';
                                            break;
                                        case -4:
                                            echo 'Err -4: No permission to write to preview folder.';
                                            break;
                                        case -5:
                                            echo 'Err -5: Failed to store full image.';
                                            break;
                                        case -6:
                                            echo 'Err -6: Cannot find temp file to remove; are the paths correct?';
                                            break;
                                        default:
                                            echo 'Err: Unknown error returned.';
                                    } ?>
                                </p>
                                <?php
                            } ?>
                        </div>
                        <?php
                    } ?>
                </div>
            </div>
        </div>
    </div>
<?php
View::show_footer();
