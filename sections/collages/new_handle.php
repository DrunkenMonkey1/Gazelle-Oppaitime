<?php

declare(strict_types=1);

authorize();

include SERVER_ROOT . '/classes/validate.class.php';
$Val = new VALIDATE();

$P = [];
$P = db_array($_POST);

if ($P['category'] > 0 || check_perms('site_collages_renamepersonal')) {
    $Val->SetFields('name', '1', 'string', 'The name must be between 3 and 100 characters', ['maxlength' => 100, 'minlength' => 3]);
} else {
    // Get a collage name and make sure it's unique
    $name = $LoggedUser['Username'] . "'s personal collage";
    $P['name'] = db_string($name);
    $DB->query("
    SELECT ID
    FROM collages
    WHERE Name = '" . $P['name'] . "'");
    $i = 2;
    while ($DB->has_results()) {
        $P['name'] = db_string(sprintf('%s no. %s', $name, $i));
        $DB->query("
      SELECT ID
      FROM collages
      WHERE Name = '" . $P['name'] . "'");
        ++$i;
    }
}
$Val->SetFields('description', '1', 'string', 'The description must be between 10 and 65535 characters', ['maxlength' => 65535, 'minlength' => 10]);

$Err = $Val->ValidateForm($_POST);

if (!$Err && '0' === $P['category']) {
    $DB->query("
    SELECT COUNT(ID)
    FROM collages
    WHERE UserID = '$LoggedUser[ID]'
      AND CategoryID = '0'
      AND Deleted = '0'");
    [$CollageCount] = $DB->next_record();
    if (($CollageCount >= $LoggedUser['Permissions']['MaxCollages']) || !check_perms('site_collages_personal')) {
        $Err = 'You may not create a personal collage.';
    } elseif (check_perms('site_collages_renamepersonal') && !stristr($P['name'], $LoggedUser['Username'])) {
        $Err = "Your personal collage's title must include your username.";
    }
}

if (!$Err) {
    $DB->query("
    SELECT ID, Deleted
    FROM collages
    WHERE Name = '$P[name]'");
    if ($DB->has_results()) {
        [$ID, $Deleted] = $DB->next_record();
        if ($Deleted) {
            $Err = 'That collection already exists but needs to be recovered; please <a href="staffpm.php">contact</a> the staff team!';
        } else {
            $Err = sprintf('That collection already exists: <a href="/collages.php?id=%s">%s</a>.', $ID, $ID);
        }
    }
}

if (!$Err && empty($CollageCats[$P['category']])) {
    $Err = 'Please select a category';
}

if ($Err) {
    $Name = $_POST['name'];
    $Category = $_POST['category'];
    $Tags = $_POST['tags'];
    $Description = $_POST['description'];
    include SERVER_ROOT . '/sections/collages/new.php';
    die();
}

$TagList = explode(',', $_POST['tags']);
foreach ($TagList as $ID => $Tag) {
    $TagList[$ID] = Misc::sanitize_tag($Tag);
}
$TagList = implode(' ', $TagList);

$DB->query("
  INSERT INTO collages
    (Name, Description, UserID, TagList, CategoryID)
  VALUES
    ('$P[name]', '$P[description]', $LoggedUser[ID], '{$TagList}', '$P[category]')");

$CollageID = $DB->inserted_id();
$Cache->delete_value(sprintf('collage_%s', $CollageID));
Misc::write_log(sprintf('Collage %s (', $CollageID) . $_POST['name'] . ') was created by ' . $LoggedUser['Username']);
header(sprintf('Location: collages.php?id=%s', $CollageID));
