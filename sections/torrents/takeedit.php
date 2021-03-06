<?php

declare(strict_types=1);

//******************************************************************************//
//--------------- Take edit ----------------------------------------------------//
// This pages handles the backend of the 'edit torrent' function. It checks     //
// the data, and if it all validates, it edits the values in the database       //
// that correspond to the torrent in question.                                  //
//******************************************************************************//

enforce_login();
authorize();

require_once SERVER_ROOT . '/classes/validate.class.php';

$Validate = new VALIDATE();

//******************************************************************************//
//--------------- Set $Properties array ----------------------------------------//
// This is used if the form doesn't validate, and when the time comes to enter  //
// it into the database.                                                        //
//******************************************************************************//

$Properties=[];
++$_POST['type'];
$TypeID = (int)$_POST['type'];
$Type = $Categories[$TypeID-1];
$TorrentID = (int)$_POST['torrentid'];
$Properties['Remastered'] = (isset($_POST['remaster']))? 1 : 0;
if ($Properties['Remastered']) {
    $Properties['UnknownRelease'] = (isset($_POST['unknown'])) ? 1 : 0;
}
if (!$Properties['Remastered']) {
    $Properties['UnknownRelease'] = 0;
}
$Properties['BadTags'] = (isset($_POST['bad_tags']))? 1 : 0;
$Properties['BadFolders'] = (isset($_POST['bad_folders']))? 1 : 0;
$Properties['BadFiles'] = (isset($_POST['bad_files'])) ? 1 : 0;
$Properties['Format'] = $_POST['format'];
$Properties['Media'] = $_POST['media'];
$Properties['Bitrate'] = $_POST['bitrate'];
$Properties['Encoding'] = $_POST['bitrate'];
$Properties['Trumpable'] = (isset($_POST['make_trumpable'])) ? 1 : 0;
$Properties['TorrentDescription'] = $_POST['release_desc'];
$Properties['MediaInfo'] = $_POST['mediainfo'];
$Properties['Name'] = $_POST['title'];
$Properties['Container'] = $_POST['container'];
$Properties['Codec'] = $_POST['codec'];
$Properties['Resolution'] = $_POST['resolution'];
$Properties['AudioFormat'] = $_POST['audioformat'];
$Properties['Subbing'] = $_POST['sub'];
$Properties['Language'] = $_POST['lang'];
$Properties['Subber']= $_POST['subber'];
$Properties['Censored'] = (isset($_POST['censored'])) ? '1' : '0';
$Properties['Anonymous'] = (isset($_POST['anonymous'])) ? '1' : '0';
$Properties['Archive'] = (isset($_POST['archive']) && '---' != $_POST['archive']) ? $_POST['archive'] : '';

if ($_POST['album_desc']) {
    $Properties['GroupDescription'] = $_POST['album_desc'];
}
if (check_perms('torrents_freeleech')) {
    $Free = (int)$_POST['freeleech'];
    if (!in_array($Free, [0, 1, 2], true)) {
        error(404);
    }
    $Properties['FreeLeech'] = $Free;

    if (0 == $Free) {
        $FreeType = 0;
    } else {
        $FreeType = (int)$_POST['freeleechtype'];
        if (!in_array($Free, [0, 1, 2, 3], true)) {
            error(404);
        }
    }
    $Properties['FreeLeechType'] = $FreeType;
}

//******************************************************************************//
//--------------- Validate data in edit form -----------------------------------//

/*
$DB->query("
  SELECT UserID, Remastered, RemasterYear, FreeTorrent
  FROM torrents
  WHERE ID = $TorrentID");
*/
$DB->query("
  SELECT UserID, FreeTorrent
  FROM torrents
  WHERE ID = {$TorrentID}");
if (!$DB->has_results()) {
    error(404);
}
// list($UserID, $Remastered, $RemasterYear, $CurFreeLeech) = $DB->next_record(MYSQLI_BOTH, false);
[$UserID, $CurFreeLeech] = $DB->next_record(MYSQLI_BOTH, false);

if ($LoggedUser['ID'] != $UserID && !check_perms('torrents_edit')) {
    error(403);
}

/*
if ($Remastered == '1' && !$RemasterYear && !check_perms('edit_unknowns')) {
  error(403);
}
*/
//It's Unknown now, and it wasn't before
if ($Properties['UnknownRelease'] && !('1' == $Remastered && !$RemasterYear) && !check_perms('edit_unknowns') && $LoggedUser['ID'] != $UserID) {
    //Hax
    die();
}

$Validate->SetFields('type', '1', 'number', 'Not a valid type.', ['maxlength' => count($Categories), 'minlength' => 1]);
switch ($Type) {
  case 'Music':
    if (!empty($Properties['Remastered']) && !$Properties['UnknownRelease']) {
        $Validate->SetFields('remaster_year', '1', 'number', 'Year of remaster/re-issue must be entered.');
    } else {
        $Validate->SetFields('remaster_year', '0', 'number', 'Invalid remaster year.');
    }

    if (!empty($Properties['Remastered']) && !$Properties['UnknownRelease'] && $Properties['RemasterYear'] < 1982 && 'CD' == $Properties['Media']) {
        error('You have selected a year for an album that predates the medium you say it was created on.');
        header(sprintf('Location: torrents.php?action=edit&id=%s', $TorrentID));
        die();
    }

    $Validate->SetFields('remaster_title', '0', 'string', 'Remaster title must be between 2 and 80 characters.', ['maxlength' => 80, 'minlength' => 2]);

    if ('Original Release' == $Properties['RemasterTitle']) {
        error('"Original Release" is not a valid remaster title.');
        header(sprintf('Location: torrents.php?action=edit&id=%s', $TorrentID));
        die();
    }

    $Validate->SetFields('remaster_record_label', '0', 'string', 'Remaster record label must be between 2 and 80 characters.', ['maxlength' => 80, 'minlength' => 2]);

    $Validate->SetFields('remaster_catalogue_number', '0', 'string', 'Remaster catalogue number must be between 2 and 80 characters.', ['maxlength' => 80, 'minlength' => 2]);


    $Validate->SetFields('format', '1', 'inarray', 'Not a valid format.', ['inarray' => $Formats]);

    $Validate->SetFields('bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => $Bitrates]);


    // Handle 'other' bitrates
    if ('Other' == $Properties['Encoding']) {
        $Validate->SetFields('other_bitrate', '1', 'text', 'You must enter the other bitrate (max length: 9 characters).', ['maxlength' => 9]);
        $enc = trim($_POST['other_bitrate']);
        if (isset($_POST['vbr'])) {
            $enc .= ' (VBR)';
        }

        $Properties['Encoding'] = $enc;
        $Properties['Bitrate'] = $enc;
    } else {
        $Validate->SetFields('bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => $Bitrates]);
    }

    $Validate->SetFields('media', '1', 'inarray', 'Not a valid media.', ['inarray' => $Media]);

    $Validate->SetFields('release_desc', '0', 'string', 'Invalid release description.', ['maxlength' => 1_000_000, 'minlength' => 0]);

    break;

  case 'Audiobooks':
  case 'Comedy':
    /*$Validate->SetFields('title', '1', 'string', 'Title must be between 2 and 300 characters.', array('maxlength' => 300, 'minlength' => 2));
    ^ this is commented out because there is no title field on these pages*/
    $Validate->SetFields('year', '1', 'number', 'The year of the release must be entered.');

    $Validate->SetFields('format', '1', 'inarray', 'Not a valid format.', ['inarray' => $Formats]);

    $Validate->SetFields('bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => $Bitrates]);


    // Handle 'other' bitrates
    if ('Other' == $Properties['Encoding']) {
        $Validate->SetFields('other_bitrate', '1', 'text', 'You must enter the other bitrate (max length: 9 characters).', ['maxlength' => 9]);
        $enc = trim($_POST['other_bitrate']);
        if (isset($_POST['vbr'])) {
            $enc .= ' (VBR)';
        }

        $Properties['Encoding'] = $enc;
        $Properties['Bitrate'] = $enc;
    } else {
        $Validate->SetFields('bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => $Bitrates]);
    }

    $Validate->SetFields('release_desc', '0', 'string', 'The release description has a minimum length of 10 characters.', ['maxlength' => 1_000_000, 'minlength' => 10]);

    break;

  case 'Applications':
  case 'Comics':
  case 'E-Books':
  case 'E-Learning Videos':
    /*$Validate->SetFields('title', '1', 'string', 'Title must be between 2 and 300 characters.', array('maxlength' => 300, 'minlength' => 2));
      ^ this is commented out because there is no title field on these pages*/
    break;
}

$Err = $Validate->ValidateForm($_POST); // Validate the form

if ($Properties['Remastered'] && !$Properties['RemasterYear']) {
    //Unknown Edit!
    if ($LoggedUser['ID'] == $UserID || check_perms('edit_unknowns')) {
        //Fine!
    } else {
        $Err = "You may not edit someone else's upload to unknown release.";
    }
}

// Strip out Amazon's padding
$AmazonReg = '/(http:\/\/ecx.images-amazon.com\/images\/.+)(\._.*_\.jpg)/i';
$Matches = [];
if (preg_match($RegX, $Properties['Image'], $Matches)) {
    $Properties['Image'] = $Matches[1] . '.jpg';
}
ImageTools::blacklisted($Properties['Image']);

if ($Err) { // Show the upload form, with the data the user entered
    if (check_perms('site_debug')) {
        die($Err);
    }
    error($Err);
}


//******************************************************************************//
//--------------- Make variables ready for database input ----------------------//

// Shorten and escape $Properties for database input
$T = [];
foreach ($Properties as $Key => $Value) {
    $T[$Key] = "'" . db_string(trim($Value)) . "'";
    if ('' === $T[$Key]) {
        $T[$Key] = null;
    }
}

$T['Censored'] = $Properties['Censored'];
$T['Anonymous'] = $Properties['Anonymous'];


//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$DBTorVals = [];
$DB->query("
  SELECT Media, Container, Codec, Resolution, AudioFormat, Subbing, Language, Description, MediaInfo, Censored, Anonymous, Archive, Subber
  FROM torrents
  WHERE ID = {$TorrentID}");
$DBTorVals = $DB->to_array(false, MYSQLI_ASSOC);
$DBTorVals = $DBTorVals[0];
$LogDetails = '';
foreach ($DBTorVals as $Key => $Value) {
    $Value = sprintf('\'%s\'', $Value);
    if ($Value != $T[$Key]) {
        if (!isset($T[$Key])) {
            continue;
        }
        if ((empty($Value) && empty($T[$Key])) || ("'0'" == $Value && "''" == $T[$Key])) {
            continue;
        }
        $LogDetails = '' == $LogDetails ? sprintf('%s: %s -> ', $Key, $Value) . $T[$Key] : sprintf('%s, %s: %s -> ', $LogDetails, $Key, $Value) . $T[$Key];
    }
}
$T['Censored'] = $Properties['Censored'];
$T['Anonymous'] = $Properties['Anonymous'];

// Update info for the torrent
/*
$SQL = "
  UPDATE torrents
  SET
    Media = $T[Media],
    Format = $T[Format],
    Encoding = $T[Encoding],
    RemasterYear = $T[RemasterYear],
    Remastered = $T[Remastered],
    RemasterTitle = $T[RemasterTitle],
    RemasterRecordLabel = $T[RemasterRecordLabel],
    RemasterCatalogueNumber = $T[RemasterCatalogueNumber],
    Scene = $T[Scene],";
*/

$SQL = "
  UPDATE torrents
  SET
    Media = $T[Media],
    Container = $T[Container],
    Codec = $T[Codec],
    Resolution = $T[Resolution],
    AudioFormat = $T[AudioFormat],
    Subbing = $T[Subbing],
    Language = $T[Language],
    Subber = $T[Subber],
    Archive = $T[Archive],
    MediaInfo = $T[MediaInfo],
    Censored = $T[Censored],
    Anonymous = $T[Anonymous],";

if (check_perms('torrents_freeleech')) {
    $SQL .= sprintf('FreeTorrent = %s,', $T[FreeLeech]);
    $SQL .= sprintf('FreeLeechType = %s,', $T[FreeLeechType]);
}

if (check_perms('users_mod')) {
    /*  if ($T[Format] != "'FLAC'") {
        $SQL .= "
          HasLog = '0',
          HasCue = '0',";
      } else {
        $SQL .= "
          HasLog = $T[HasLog],
          HasCue = $T[HasCue],";
      }
    */
    $DB->query("
    SELECT TorrentID
    FROM torrents_bad_tags
    WHERE TorrentID = '{$TorrentID}'");
    [$btID] = $DB->next_record();

    if (!$btID && $Properties['BadTags']) {
        $DB->query("
      INSERT INTO torrents_bad_tags
      VALUES ({$TorrentID}, $LoggedUser[ID], NOW())");
    }
    if ($btID && !$Properties['BadTags']) {
        $DB->query("
      DELETE FROM torrents_bad_tags
      WHERE TorrentID = '{$TorrentID}'");
    }

    $DB->query("
    SELECT TorrentID
    FROM torrents_bad_folders
    WHERE TorrentID = '{$TorrentID}'");
    [$bfID] = $DB->next_record();

    if (!$bfID && $Properties['BadFolders']) {
        $DB->query("
      INSERT INTO torrents_bad_folders
      VALUES ({$TorrentID}, $LoggedUser[ID], NOW())");
    }
    if ($bfID && !$Properties['BadFolders']) {
        $DB->query("
      DELETE FROM torrents_bad_folders
      WHERE TorrentID = '{$TorrentID}'");
    }

    $DB->query("
    SELECT TorrentID
    FROM torrents_bad_files
    WHERE TorrentID = '{$TorrentID}'");
    [$bfiID] = $DB->next_record();

    if (!$bfiID && $Properties['BadFiles']) {
        $DB->query("
      INSERT INTO torrents_bad_files
      VALUES ({$TorrentID}, $LoggedUser[ID], NOW())");
    }
    if ($bfiID && !$Properties['BadFiles']) {
        $DB->query("
      DELETE FROM torrents_bad_files
      WHERE TorrentID = '{$TorrentID}'");
    }

    $DB->query("
    SELECT TorrentID
    FROM library_contest
    WHERE TorrentID = '{$TorrentID}'");
    [$lbID] = $DB->next_record();
    if (!$lbID && $Properties['LibraryUpload'] && $Properties['LibraryPoints'] > 0) {
        $DB->query("
      SELECT UserID
      FROM torrents
      WHERE ID = {$TorrentID}");
        [$UploaderID] = $DB->next_record();
        $DB->query("
      INSERT INTO library_contest
      VALUES ({$UploaderID}, {$TorrentID}, $Properties[LibraryPoints])");
    }
    if ($lbID && !$Properties['LibraryUpload']) {
        $DB->query("
      DELETE FROM library_contest
      WHERE TorrentID = '{$TorrentID}'");
    }
}

$SQL .= "
    Description = $T[TorrentDescription]
  WHERE ID = {$TorrentID}";
$DB->query($SQL);

if (check_perms('torrents_freeleech') && $Properties['FreeLeech'] != $CurFreeLeech) {
    Torrents::freeleech_torrents($TorrentID, $Properties['FreeLeech'], $Properties['FreeLeechType']);
}

$DB->query("
  SELECT GroupID, Time
  FROM torrents
  WHERE ID = '{$TorrentID}'");
[$GroupID, $Time] = $DB->next_record();

// Competition
if (strtotime($Time) > 1_241_352_173 && '100' == $_POST['log_score']) {
    $DB->query("
      INSERT IGNORE into users_points (GroupID, UserID, Points)
      VALUES ('{$GroupID}', '{$UserID}', '1')");
}
// End competiton

$DB->query("
  SELECT Enabled
  FROM users_main
  WHERE ID = {$UserID}");
[$Enabled] = $DB->next_record();

$DB->query("
  SELECT Name
  FROM torrents_group
  WHERE ID = {$GroupID}");
[$Name] = $DB->next_record(MYSQLI_NUM, false);

Misc::write_log(sprintf('Torrent %s (%s) in group %s was edited by ', $TorrentID, $Name, $GroupID) . $LoggedUser['Username'] . sprintf(' (%s)', $LogDetails)); // TODO: this is probably broken
Torrents::write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], $LogDetails, 0);
$Cache->delete_value(sprintf('torrents_details_%s', $GroupID));
$Cache->delete_value(sprintf('torrent_download_%s', $TorrentID));

Torrents::update_hash($GroupID);
// All done!

header(sprintf('Location: torrents.php?id=%s', $GroupID));
