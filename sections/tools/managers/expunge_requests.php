<?php declare(strict_types=1);

if (!check_perms('users_mod')) {
    error(403);
}

$QueryID = $DB->query("
  SELECT SQL_CALC_FOUND_ROWS *
  FROM deletion_requests");

$DB->query("SELECT FOUND_ROWS()");
[$NumResults] = $DB->next_record();
$DB->set_query_id($QueryID);

$Requests = $DB->to_array();

if (isset($_GET['deny']) && isset($_GET['type']) && isset($_GET['value'])) {
    authorize();

    $Deny = ('true' == $_GET['deny']);
    $Type = 'email' == $_GET['type'] ? 'Email' : ('ip' == $_GET['type'] ? 'IP' : '');
    $Value = db_string($_GET['value']);

    $DB->query("
    DELETE FROM deletion_requests
    WHERE Value = '{$Value}'");

    $DB->query("
    SELECT UserID
    FROM users_history_" . strtolower($Type) . "s
    WHERE {$Type} = '{$Value}'");
    if ($DB->has_results()) {
        [$UserID] = $DB->next_record();
        if ($UserID != $_GET['userid']) {
            $Err = "The specified UserID is incorrect.";
        }
    } else {
        $Err = sprintf('That %s doesn\'t exist.', $Type);
    }

    if (empty($Err)) {
        if (!$Deny) {
            $DB->query("
        SELECT {$Type}
        FROM users_history_" . strtolower($Type) . "s
        WHERE UserID = '{$UserID}'");
            $ToDelete = [];
            while ([$EncValue] = $DB->next_record()) {
                if (Crypto::decrypt($Value) == Crypto::decrypt($EncValue)) {
                    $ToDelete[] = $EncValue;
                }
            }
            foreach ($ToDelete as $DelValue) {
                $DB->query("
          DELETE FROM users_history_" . strtolower($Type) . "s
          WHERE UserID = {$UserID}
            AND {$Type} = '{$DelValue}'");
            }
            $Succ = sprintf('%s deleted.', $Type);
            Misc::send_pm($UserID, 0, sprintf('%s Deletion Request Accepted.', $Type), sprintf('Your deletion request has been accepted. What %s? I don\'t know! We don\'t have it anymore!', $Type));
        } else {
            $Succ = "Request denied.";
            Misc::send_pm($UserID, 0, sprintf('%s Deletion Request Denied.', $Type), "Your deletion request has been denied.\n\nIf you wish to discuss this matter further, please create a staff PM, or join " . BOT_HELP_CHAN . " on IRC to speak with a staff member.");
        }
    }

    $Cache->delete_value('num_deletion_requests');
}

View::show_header("Expunge Requests");

?>

<div class="header">
  <h2>Expunge Requests</h2>
</div>

<?php if (isset($Err)) { ?>
<span>Error: <?=$Err?></span>
<?php } elseif (isset($Succ)) { ?>
<span>Success: <?=$Succ?></span>
<?php } ?>

<div class="thin">
  <table width="100%">
    <tr class="colhead">
      <td>User</td>
      <td>Type</td>
      <td>Value</td>
      <td>Reason</td>
      <td>Accept</td>
      <td>Deny</td>
    </tr>
<?php foreach ($Requests as $Request) { ?>
    <tr>
      <td><?=Users::format_username($Request['UserID'])?></td>
      <td><?=$Request['Type']?></td>
      <td><?=Crypto::decrypt($Request['Value'])?></td>
      <td><?=display_str($Request['Reason'])?></td>
      <td><a href="tools.php?action=expunge_requests&auth=<?=$LoggedUser['AuthKey']?>&type=<?=strtolower($Request['Type'])?>&value=<?=urlencode($Request['Value'])?>&userid=<?=$Request['UserID']?>&deny=false" class="brackets">Accept</a></td>
      <td><a href="tools.php?action=expunge_requests&auth=<?=$LoggedUser['AuthKey']?>&type=<?=strtolower($Request['Type'])?>&value=<?=urlencode($Request['Value'])?>&userid=<?=$Request['UserID']?>&deny=true" class="brackets">Deny</a></td>
    </tr>
<?php } ?>
  </table>
</div>

<?php View::show_footer(); ?>
