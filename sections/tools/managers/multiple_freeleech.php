<?php
if (!check_perms('users_mod')) {
    error(403);
}

View::show_header('Multiple freeleech');

if (isset($_POST['torrents'])) {
    $GroupIDs = [];
    $Elements = explode("\r\n", $_POST['torrents']);
    foreach ($Elements as $Element) {
        // Get all of the torrent IDs
        if (false !== strpos($Element, "torrents.php")) {
            $Data = explode("id=", $Element);
            if (!empty($Data[1])) {
                $GroupIDs[] = (int) $Data[1];
            }
        } elseif (false !== strpos($Element, "collages.php")) {
            $Data = explode("id=", $Element);
            if (!empty($Data[1])) {
                $CollageID = (int) $Data[1];
                $DB->query("
                    SELECT GroupID
                    FROM collages_torrents
                    WHERE CollageID = '$CollageID'");
                while ([$GroupID] = $DB->next_record()) {
                    $GroupIDs[] = (int) $GroupID;
                }
            }
        }
    }

    if (0 == sizeof($GroupIDs)) {
        $Err = 'Please enter properly formatted URLs';
    } else {
        $FreeLeechType = (int) $_POST['freeleechtype'];
        $FreeLeechReason = (int) $_POST['freeleechreason'];

        if (!in_array($FreeLeechType, [0, 1, 2], true) || !in_array($FreeLeechReason, [0, 1, 2, 3], true)) {
            $Err = 'Invalid freeleech type or freeleech reason';
        } else {
            // Get the torrent IDs
            $DB->query("
                SELECT ID
                FROM torrents
                WHERE GroupID IN (" . implode(', ', $GroupIDs) . ")");
            $TorrentIDs = $DB->collect('ID');

            if (0 == sizeof($TorrentIDs)) {
                $Err = 'Invalid group IDs';
            } else {
                if (isset($_POST['NLOver']) && 1 == $FreeLeechType) {
                    // Only use this checkbox if freeleech is selected
                    $Size = (int) $_POST['size'];
                    $Units = db_string($_POST['scale']);

                    if (empty($Size) || !in_array($Units, ['k', 'm', 'g'], true)) {
                        $Err = 'Invalid size or units';
                    } else {
                        $Bytes = Format::get_bytes($Size . $Units);

                        $DB->query("
                            SELECT ID
                            FROM torrents
                            WHERE ID IN (" . implode(', ', $TorrentIDs) . ")
                              AND Size > '$Bytes'");
                        $LargeTorrents = $DB->collect('ID');
                        $TorrentIDs = array_diff($TorrentIDs, $LargeTorrents);
                    }
                }

                if (sizeof($TorrentIDs) > 0) {
                    Torrents::freeleech_torrents($TorrentIDs, $FreeLeechType, $FreeLeechReason);
                }

                if (isset($LargeTorrents) && sizeof($LargeTorrents) > 0) {
                    Torrents::freeleech_torrents($LargeTorrents, 2, $FreeLeechReason);
                }

                $Err = 'Done!';
            }
        }
    }
}
?>
<div class="thin">
    <div class="box pad box2">
<?php  if (isset($Err)) { ?>
        <strong class="important_text"><?=$Err?></strong><br />
<?php  } ?>
        Paste a list of collage or torrent group URLs
    </div>
    <div class="box pad">
        <form class="send_form center" action="" method="post">
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <textarea name="torrents" style="width: 95%; height: 200px;"><?=$_POST['torrents']?></textarea><br /><br />
            Mark torrents as:&nbsp;
            <select name="freeleechtype">
                <option value="1" <?='1' == $_POST['freeleechtype'] ? 'selected' : ''?>>FL</option>
                <option value="2" <?='2' == $_POST['freeleechtype'] ? 'selected' : ''?>>NL</option>
                <option value="0" <?='0' == $_POST['freeleechtype'] ? 'selected' : ''?>>Normal</option>
            </select>
            &nbsp;for reason&nbsp;<select name="freeleechreason">
<?php      $FL = ['N/A', 'Staff Pick', 'Perma-FL', 'Vanity House'];
        foreach ($FL as $Key => $FLType) { ?>
                            <option value="<?=$Key?>" <?=$_POST['freeleechreason'] == $Key ? 'selected' : ''?>><?=$FLType?></option>
<?php      } ?>
            </select><br /><br />
            <input type="checkbox" name="NLOver" checked />&nbsp;NL Torrents over <input type="text" name="size" value="<?=isset($_POST['size']) ? $_POST['size'] : '1'?>" size=1 />
            <select name="scale">
                <option value="k" <?='k' == $_POST['scale'] ? 'selected' : ''?>>KB</option>
                <option value="m" <?='m' == $_POST['scale'] ? 'selected' : ''?>>MB</option>
                <option value="g" <?=!isset($_POST['scale']) || 'g' == $_POST['scale'] ? 'selected' : ''?>>GB</option>
            </select><br /><br />
            <input type="submit" value="Submit" />
        </form>
    </div>
</div>
<?php
View::show_footer();
