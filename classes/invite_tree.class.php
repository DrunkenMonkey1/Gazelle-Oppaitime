<?php declare(strict_types=1);
/**************************************************************************/

/*-- Invite tree class -----------------------------------------------------



***************************************************************************/

class INVITE_TREE
{
    public int $UserID = 0;
    public bool $Visible = true;
    
    // Set things up
    public function __construct(int $UserID, $Options = [])
    {
        $this->UserID = $UserID;
        if (false === $Options['visible']) {
            $this->Visible = false;
        }
    }
    
    public function make_tree(): void
    {
        $QueryID = G::$DB->get_query_id();
        
        $UserID = $this->UserID; ?>
        <div class="invitetree pad">
            <?php
            G::$DB->query("
      SELECT TreePosition, TreeID, TreeLevel
      FROM invite_tree
      WHERE UserID = $UserID");
            [$TreePosition, $TreeID, $TreeLevel] = G::$DB->next_record(MYSQLI_NUM, false);
            
            if (!$TreeID) {
                return;
            }
            G::$DB->query("
      SELECT TreePosition
      FROM invite_tree
      WHERE TreeID = $TreeID
        AND TreeLevel = $TreeLevel
        AND TreePosition > $TreePosition
      ORDER BY TreePosition ASC
      LIMIT 1");
            if (G::$DB->has_results()) {
                [$MaxPosition] = G::$DB->next_record(MYSQLI_NUM, false);
            } else {
                $MaxPosition = false;
            }
            $TreeQuery = G::$DB->query("
      SELECT
        it.UserID,
        Enabled,
        PermissionID,
        Donor,
        Uploaded,
        Downloaded,
        Paranoia,
        TreePosition,
        TreeLevel
      FROM invite_tree AS it
        JOIN users_main AS um ON um.ID = it.UserID
        JOIN users_info AS ui ON ui.UserID = it.UserID
      WHERE TreeID = $TreeID
        AND TreePosition > $TreePosition" .
                ($MaxPosition ? " AND TreePosition < $MaxPosition" : '') . "
        AND TreeLevel > $TreeLevel
      ORDER BY TreePosition");
            
            $PreviousTreeLevel = $TreeLevel;
            
            // Stats for the summary
            $MaxTreeLevel = $TreeLevel; // The deepest level (this changes)
            $OriginalTreeLevel = $TreeLevel; // The level of the user we're viewing
            $BaseTreeLevel = $TreeLevel + 1; // The level of users invited by our user
            $Count = 0;
            $Branches = 0;
            $DisabledCount = 0;
            $DonorCount = 0;
            $ParanoidCount = 0;
            $TotalUpload = 0;
            $TotalDownload = 0;
            $TopLevelUpload = 0;
            $TopLevelDownload = 0;
            
            $ClassSummary = [];
            global $Classes;
            foreach (array_keys($Classes) as $ClassID) {
                $ClassSummary[$ClassID] = 0;
            }
            
            // We store this in an output buffer, so we can show the summary at the top without having to loop through twice
            ob_start();
            while ([
                $ID,
                $Enabled,
                $Class,
                $Donor,
                $Uploaded,
                $Downloaded,
                $Paranoia,
                $TreePosition,
                $TreeLevel
            ] = G::$DB->next_record(MYSQLI_NUM, false)) {
                // Do stats
                $Count++;
                
                if ($TreeLevel > $MaxTreeLevel) {
                    $MaxTreeLevel = $TreeLevel;
                }
                
                if ($TreeLevel == $BaseTreeLevel) {
                    $Branches++;
                    $TopLevelUpload += $Uploaded;
                    $TopLevelDownload += $Downloaded;
                }
                
                $ClassSummary[$Class]++;
                if (2 == $Enabled) {
                    $DisabledCount++;
                }
                if ($Donor) {
                    $DonorCount++;
                }
                
                // Manage tree depth
                if ($TreeLevel > $PreviousTreeLevel) {
                    for ($i = 0; $i < $TreeLevel - $PreviousTreeLevel; $i++) {
                        echo "\n\n<ul class=\"invitetree\">\n\t<li>\n";
                    }
                } elseif ($TreeLevel < $PreviousTreeLevel) {
                    for ($i = 0; $i < $PreviousTreeLevel - $TreeLevel; $i++) {
                        echo "\t</li>\n</ul>\n";
                    }
                    echo "\t</li>\n\t<li>\n";
                } else {
                    echo "\t</li>\n\t<li>\n";
                }
                $UserClass = $Classes[$Class]['Level']; ?>
                <strong><?= Users::format_username($ID, true, true, (2 == $Enabled), true) ?></strong>
                <?php
                if (check_paranoia(['uploaded', 'downloaded'], $Paranoia, $UserClass)) {
                    $TotalUpload += $Uploaded;
                    $TotalDownload += $Downloaded; ?>
                    &nbsp;Uploaded: <strong><?= Format::get_size($Uploaded) ?></strong>
                    &nbsp;Downloaded: <strong><?= Format::get_size($Downloaded) ?></strong>
                    &nbsp;Ratio: <strong><?= Format::get_ratio_html($Uploaded, $Downloaded) ?></strong>
                    <?php
                } else {
                    $ParanoidCount++; ?>
                    &nbsp;Hidden
                    <?php
                } ?>
                
                <?php
                $PreviousTreeLevel = $TreeLevel;
                G::$DB->set_query_id($TreeQuery);
            }
            
            $Tree = ob_get_clean();
            for ($i = 0; $i < $PreviousTreeLevel - $OriginalTreeLevel; $i++) {
                $Tree .= "\t</li>\n</ul>\n";
            }
            
            if (0 !== $Count) {
            ?>
            <p style="font-weight: bold;">
                This tree has <?= number_format($Count) ?> entries, <?= number_format($Branches) ?> branches, and a
                depth of <?= number_format($MaxTreeLevel - $OriginalTreeLevel) ?>.
                It has
                <?php
                $ClassStrings = [];
                foreach ($ClassSummary as $ClassID => $ClassCount) {
                    if (0 == $ClassCount) {
                        continue;
                    }
                    $LastClass = Users::make_class_string($ClassID);
                    if ($ClassCount > 1) {
                        if ('Torrent Celebrity' == $LastClass) {
                            $LastClass = 'Torrent Celebrities';
                        } elseif ('s' != substr($LastClass, -1)) {
                            $LastClass .= 's';
                        }
                    }
                    $LastClass = "$ClassCount $LastClass (" . number_format(($ClassCount / $Count) * 100) . '%)';
                    
                    $ClassStrings[] = $LastClass;
                }
                if (count($ClassStrings) > 1) {
                    array_pop($ClassStrings);
                    echo implode(', ', $ClassStrings);
                    echo ' and ' . $LastClass;
                } else {
                    echo $LastClass;
                }
                echo '. ';
                echo $DisabledCount;
                echo (1 == $DisabledCount) ? ' user is' : ' users are';
                echo ' disabled (';
                if (0 == $DisabledCount) {
                    echo '0%)';
                } else {
                    echo number_format(($DisabledCount / $Count) * 100) . '%)';
                }
                echo ', and ';
                echo $DonorCount;
                echo (1 == $DonorCount) ? ' user has' : ' users have';
                echo ' donated (';
                if (0 == $DonorCount) {
                    echo '0%)';
                } else {
                    echo number_format(($DonorCount / $Count) * 100) . '%)';
                }
                echo '. </p>';
                
                echo '<p style="font-weight: bold;">';
                echo 'The total amount uploaded by the entire tree was ' . Format::get_size($TotalUpload);
                echo '; the total amount downloaded was ' . Format::get_size($TotalDownload);
                echo '; and the total ratio is ' . Format::get_ratio_html($TotalUpload, $TotalDownload) . '. ';
                echo '</p>';
                
                echo '<p style="font-weight: bold;">';
                echo 'The total amount uploaded by direct invitees (the top level) was ' . Format::get_size($TopLevelUpload);
                echo '; the total amount downloaded was ' . Format::get_size($TopLevelDownload);
                echo '; and the total ratio is ' . Format::get_ratio_html($TopLevelUpload, $TopLevelDownload) . '. ';
                
                echo "These numbers include the stats of paranoid users and will be factored into the invitation giving script.\n\t\t</p>\n";
                
                if (0 !== $ParanoidCount) {
                    echo '<p style="font-weight: bold;">';
                    echo $ParanoidCount;
                    echo (1 == $ParanoidCount) ? ' user (' : ' users (';
                    echo number_format(($ParanoidCount / $Count) * 100);
                    echo '%) ';
                    echo (1 == $ParanoidCount) ? ' is' : ' are';
                    echo ' too paranoid to have their stats shown here, and ';
                    echo (1 == $ParanoidCount) ? ' was' : ' were';
                    echo ' not factored into the stats for the total tree.';
                    echo '</p>';
                }
                } ?>
                <br/>
                <?= $Tree ?>
        </div>
        <?php
        G::$DB->set_query_id($QueryID);
    }
}

?>
