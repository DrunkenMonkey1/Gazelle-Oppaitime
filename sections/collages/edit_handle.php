<?php

declare(strict_types=1);

authorize();

$CollageID = $_POST['collageid'];
if (!is_number($CollageID)) {
    error(0);
}

$DB->query("
  SELECT UserID, CategoryID, Locked, MaxGroups, MaxGroupsPerUser
  FROM collages
  WHERE ID = '{$CollageID}'");
[$UserID, $CategoryID, $Locked, $MaxGroups, $MaxGroupsPerUser] = $DB->next_record();
if (0 == $CategoryID && $UserID != $LoggedUser['ID'] && !check_perms('site_collages_delete')) {
    error(403);
}
if (isset($_POST['name'])) {
    $DB->query("
    SELECT ID, Deleted
    FROM collages
    WHERE Name = '" . db_string($_POST['name']) . "'
      AND ID != '{$CollageID}'
    LIMIT 1");
    if ($DB->has_results()) {
        [$ID, $Deleted] = $DB->next_record();
        if ($Deleted) {
            $Err = 'A collage with that name already exists but needs to be recovered, please <a href="staffpm.php">contact</a> the staff team!';
        } else {
            $Err = sprintf('A collage with that name already exists: <a href="/collages.php?id=%s">%s</a>.', $ID, $_POST[name]);
        }
        $ErrNoEscape = true;
        include SERVER_ROOT . '/sections/collages/edit.php';
        die();
    }
}

$TagList = explode(',', $_POST['tags']);
foreach ($TagList as $ID => $Tag) {
    $TagList[$ID] = Misc::sanitize_tag($Tag);
}
$TagList = implode(' ', $TagList);

$Updates = ["Description='" . db_string($_POST['description']) . "', TagList='" . db_string($TagList) . "'"];

if (!check_perms('site_collages_delete') && (0 == $CategoryID && $UserID == $LoggedUser['ID'] && check_perms('site_collages_renamepersonal')) && !stristr($_POST['name'], $LoggedUser['Username'])) {
    error("Your personal collage's title must include your username.");
}

if (isset($_POST['featured']) && 0 == $CategoryID && (($LoggedUser['ID'] == $UserID && check_perms('site_collages_personal')) || check_perms('site_collages_delete'))) {
    $DB->query("
    UPDATE collages
    SET Featured = 0
    WHERE CategoryID = 0
      AND UserID = {$UserID}");
    $Updates[] = 'Featured = 1';
}

if (check_perms('site_collages_delete') || (0 == $CategoryID && $UserID == $LoggedUser['ID'] && check_perms('site_collages_renamepersonal'))) {
    $Updates[] = "Name = '" . db_string($_POST['name']) . "'";
}

if (isset($_POST['category']) && !empty($CollageCats[$_POST['category']]) && $_POST['category'] != $CategoryID && (0 != $_POST['category'] || check_perms('site_collages_delete'))) {
    $Updates[] = 'CategoryID = ' . $_POST['category'];
}

if (check_perms('site_collages_delete')) {
    if (isset($_POST['locked']) != $Locked) {
        $Updates[] = 'Locked = ' . ($Locked ? "'0'" : "'1'");
    }
    if (isset($_POST['maxgroups']) && (0 == $_POST['maxgroups'] || is_number($_POST['maxgroups'])) && $_POST['maxgroups'] != $MaxGroups) {
        $Updates[] = 'MaxGroups = ' . $_POST['maxgroups'];
    }
    if (isset($_POST['maxgroups']) && (0 == $_POST['maxgroupsperuser'] || is_number($_POST['maxgroupsperuser'])) && $_POST['maxgroupsperuser'] != $MaxGroupsPerUser) {
        $Updates[] = 'MaxGroupsPerUser = ' . $_POST['maxgroupsperuser'];
    }
}

if (!empty($Updates)) {
    $DB->query('
    UPDATE collages
    SET ' . implode(', ', $Updates) . "
    WHERE ID = {$CollageID}");
}
$Cache->delete_value('collage_' . $CollageID);
header('Location: collages.php?id=' . $CollageID);
