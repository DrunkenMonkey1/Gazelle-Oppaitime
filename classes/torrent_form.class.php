<?php declare(strict_types=1);

// This class is used in upload.php to display the upload form, and the edit
// section of torrents.php to display a shortened version of the same form

class TorrentForm
{
    /**
     * @var mixed[]
     */
    public array $MediaManga;
    /**
     * @var mixed[]
     */
    public array $Archives;
    /**
     * @var mixed[]
     */
    public array $ArchivesManga;
    public $UploadForm = '';
    /**
     * @var mixed[]
     */
    public array $Categories = [];
    /**
     * @var mixed[]
     */
    public array $Formats = [];
    /**
     * @var mixed[]
     */
    public array $Bitrates = [];
    /**
     * @var mixed[]
     */
    public array $Media = [];
    /**
     * @var mixed[]
     */
    public array $MediaManaga = [];
    /**
     * @var mixed[]
     */
    public array $Containers = [];
    /**
     * @var mixed[]
     */
    public array $ContainersGames = [];
    /**
     * @var mixed[]
     */
    public array $Codecs = [];
    /**
     * @var mixed[]
     */
    public array $Resolutions = [];
    /**
     * @var mixed[]
     */
    public array $AudioFormats = [];
    /**
     * @var mixed[]
     */
    public array $Subbing = [];
    /**
     * @var mixed[]
     */
    public array $Languages = [];
    /**
     * @var mixed[]
     */
    public array $Platform = [];
    public $TorrentID = false;
    public string $Disabled = '';
    public bool $DisabledFlag = false;
    
    public function __construct(public $Torrent = [], public $Error = false, public $NewTorrent = true)
    {
        global $UploadForm, $Categories, $Formats, $Bitrates, $Media, $MediaManga, $TorrentID, $Containers, $ContainersGames, $Codecs, $Resolutions, $AudioFormats, $Subbing, $Languages, $Platform, $Archives, $ArchivesManga;
        
        $this->UploadForm = $UploadForm;
        $this->Categories = $Categories;
        $this->Formats = $Formats;
        $this->Bitrates = $Bitrates;
        $this->Media = $Media;
        $this->MediaManga = $MediaManga;
        $this->Containers = $Containers;
        $this->ContainersGames = $ContainersGames;
        $this->Codecs = $Codecs;
        $this->Resolutions = $Resolutions;
        $this->AudioFormats = $AudioFormats;
        $this->Subbing = $Subbing;
        $this->Languages = $Languages;
        $this->TorrentID = $TorrentID;
        $this->Platform = $Platform;
        $this->Archives = $Archives;
        $this->ArchivesManga = $ArchivesManga;
        
        if ($this->Torrent && $this->Torrent['GroupID']) {
            $this->Disabled = ' readonly="readonly"';
            $this->DisabledFlag = true;
        }
    }
    
    public function upload_form(): void
    {
        $QueryID = G::$DB->get_query_id();
        $this->head();
        $Torrent = $this->Torrent; ?>
        <table cellpadding="3" cellspacing="1" border="0" class="layout slice" width="100%">
            <?php if ($this->NewTorrent) { ?>
                <tr id="javdb_tr">
                    <td class="label tooltip" title='Enter a JAV catalogue number, e.g., "CND-060"'>Catalogue Number
                    </td>
                    <td>
                        <input type="text"
                               id="catalogue"
                               name="catalogue"
                               size="10"
                               value="<?= display_str($Torrent['CatalogueNumber'] ?? '') ?>" <?= $this->Disabled ?>/>
                        <?php if (!$this->DisabledFlag) { ?>
                            <input type="button" autofill="jav" value="Autofill"></input>
                        <?php } ?>
                    </td>
                </tr>
                <tr id="anidb_tr" class="hidden">
                    <td class="label">AniDB Autofill (optional)</td>
                    <td>
                        <input type="text" id="anidb" size="10" <?= $this->Disabled ?>/>
                        <?php if (!$this->DisabledFlag) { ?>
                            <input type="button" autofill="anime" value="Autofill"/>
                        <?php } ?>
                    </td>
                </tr>
                <tr id="ehentai_tr" class="hidden">
                    <td class="label">e-hentai URL (optional)</td>
                    <td>
                        <input type="text" id="catalogue" size="50" <?= $this->Disabled ?> />
                        <?php if (!$this->DisabledFlag) { ?>
                            <input type="button" autofill="douj" value="Autofill"/>
                        <?php } ?>
                    </td>
                </tr>
                <tr id="title_tr">
                    <td class="label">Title</td>
                    <td><input type="text"
                               id="title"
                               name="title"
                               size="60"
                               value="<?= display_str($Torrent['Title'] ?? '') ?>" <?= $this->Disabled ?>/></td>
                </tr>
                <tr id="idols_tr">
                    <td class="label">Idol(s)</td>
                    <td id="idolfields">
                        <?php if (!empty($Torrent['Artists'])) {
                            foreach ($Torrent['Artists'] as $Num => $Artist) { ?>
                                <input type="text"
                                       id="idols_<?= $Num ?>"
                                       name="idols[]"
                                       size="45"
                                       value="<?= display_str($Artist['name'] ?? '') ?>" <?= $this->Disabled ?>/>
                                <?php if (0 == $Num) { ?>
                                    <a class="add_artist_button brackets">+</a><a class="remove_artist_button brackets">&minus;</a>
                                <?php }
                            }
                        } else { ?>
                            <input type="text" id="idols_0" name="idols[]" size="45" value="" <?= $this->Disabled ?> />
                            <a class="add_artist_button brackets">+</a>
                            <a class="remove_artist_button brackets">&minus;</a>
                        <?php } ?>
                    </td>
                </tr>
                <tr id="studio_tr">
                    <td class="label">Studio</td>
                    <td><input type="text"
                               id="studio"
                               name="studio"
                               size="60"
                               value="<?= display_str($Torrent['Studio'] ?? '') ?>" <?= $this->Disabled ?>/></td>
                </tr>
                <tr id="series_tr">
                    <td class="label">Series</td>
                    <td><input type="text"
                               id="series"
                               name="series"
                               size="60"
                               value="<?= display_str($Torrent['Series'] ?? '') ?>" <?= $this->Disabled ?>/></td>
                </tr>
                <tr id="year_tr">
                    <td class="label">Year</td>
                    <td><input type="text"
                               id="year"
                               name="year"
                               maxlength="4"
                               size="5"
                               value="<?= display_str($Torrent['Year'] ?? '') ?>" <?= $this->Disabled ?>/></td>
                </tr>
                <tr id="pages_tr">
                    <td class="label">Pages</td>
                    <td><input type="text"
                               id="pages"
                               name="pages"
                               maxlength="5"
                               size="5"
                               value="<?= display_str($Torrent['Pages'] ?? '') ?>" <?= $this->Disabled ?> /></td>
                </tr>
                <tr id="dlsite_tr">
                    <td class="label">DLsite ID</td>
                    <td><input type="text"
                               id="dlsiteid"
                               name="dlsiteid"
                               size="8"
                               maxlength="8"
                               value="<?= display_str($Torrent['DLsiteID'] ?? '') ?>" <?= $this->Disabled ?>/></td>
                </tr>
            <?php } ?>
            <tr id="mediainfo_tr">
                <td class="label">Media Info</td>
                <td>
                    <textarea name="mediainfo"
                              id="mediainfo"
                              onchange="MediaInfoExtract()"
                              rows="8"
                              cols="60"><?= display_str($Torrent['MediaInfo'] ?? '') ?></textarea>
                </td>
            </tr>
            <tr id="media_tr">
                <td class="label">Media</td>
                <td>
                    <select name="media">
                        <option>---</option>
                        <?php
                        foreach ($this->Media as $Media) {
                            echo "\t\t\t\t\t\t<option value=\"$Media\"";
                            if ($Media == ($Torrent['Media'] ?? false)) {
                                echo " selected";
                            }
                            echo ">$Media</option>\n";
                        } ?>
                    </select>
                </td>
            </tr>
            <tr id="media_manga_tr">
                <td class="label">Media</td>
                <td>
                    <select name="media">
                        <option>---</option>
                        <?php
                        foreach ($this->MediaManga as $Media) {
                            echo "\t\t\t\t\t\t<option value=\"$Media\"";
                            if ($Media == ($Torrent['Media'] ?? false)) {
                                echo " selected";
                            }
                            echo ">$Media</option>\n";
                        } ?>
                    </select>
                </td>
            </tr>
            <tr id="media_games_tr">
                <td class="label">Platform</td>
                <td>
                    <select id="platform" name="media">
                        <option>---</option>
                        <?php
                        foreach ($this->Platform as $Platform) {
                            echo "\t\t\t\t\t\t<option value=\"$Platform\"";
                            if ($Platform == ($Torrent['Media'] ?? false)) {
                                echo " selected";
                            }
                            echo ">$Platform</option>\n";
                        } ?>
                    </select>
                </td>
            </tr>
            <tr id="archive_tr">
                <td class='label'>Archive</td>
                <td>
                    <select name='archive'>
                        <option>---</option>
                        <?php
                        foreach ($this->Archives as $Archive) {
                            echo "\t\t\t\t\t\t<option value=\"$Archive\"";
                            if ($Archive == ($Torrent['Archive'] ?? false)) {
                                echo ' selected';
                            }
                            echo ">$Archive</option>\n";
                        } ?>
                    </select>
                </td>
            </tr>
            <tr id="archive_manga_tr">
                <td class='label'>Archive</td>
                <td>
                    <select name='archive'>
                        <option>---</option>
                        <?php
                        foreach (array_merge($this->Archives, $this->ArchivesManga) as $Archive) {
                            echo "\t\t\t\t\t\t<option value=\"$Archive\"";
                            if ($Archive == ($Torrent['Archive'] ?? false)) {
                                echo ' selected';
                            }
                            echo ">$Archive</option>\n";
                        } ?>
                    </select>
                </td>
            </tr>
            <tr id="container_tr">
                <td class="label">Container</td>
                <td>
                    <select name="container">
                        <option>---</option>
                        <?php
                        foreach ($this->Containers as $Cont) {
                            echo "\t\t\t\t\t\t<option value=\"$Cont\"";
                            if ($Cont == ($Torrent['Container'] ?? false)) {
                                echo " selected";
                            }
                            echo ">$Cont</option>\n";
                        } ?>
                    </select>
                </td>
            </tr>
            <tr id="container_games_tr">
                <td class="label">Container</td>
                <td>
                    <select id="container" name="container">
                        <option>---</option>
                        <?php
                        foreach ($this->ContainersGames as $Container) {
                            echo "\t\t\t\t\t\t<option value=\"$Container\"";
                            if ($Container == ($Torrent['Container'] ?? false)) {
                                echo " selected";
                            }
                            echo ">$Container</option>\n";
                        } ?>
                    </select>
                </td>
            </tr>
            <tr id="codec_tr">
                <td class="label">Codecs</td>
                <td>
                    <select name="codec">
                        <option>---</option>
                        <?php
                        foreach ($this->Codecs as $Codec) {
                            echo "\t\t\t\t\t\t<option value=\"$Codec\"";
                            if ($Codec == ($Torrent['Codec'] ?? false)) {
                                echo " selected";
                            }
                            echo ">$Codec</option>\n";
                        } ?>
                    </select>
                </td>
            </tr>
            <tr id="resolution_tr">
                <td class="label">Resolution</td>
                <td>
                    <select id="ressel" name="ressel" onchange="SetResolution()">
                        <option value="">---</option>
                        <?php
                        foreach ($this->Resolutions as $Res) {
                            echo "\t\t\t\t\t\t<option value=\"$Res\"";
                            if ($Res == ($Torrent['Resolution'] ?? false) || (!isset($FoundRes) && ($Torrent['Resolution'] ?? false) && "Other" == $Res)) {
                                echo " selected";
                                $FoundRes = true;
                            }
                            echo ">$Res</option>\n";
                        } ?>
                    </select>
                    <input type="text"
                           id="resolution"
                           name="resolution"
                           size="10"
                           class="hidden tooltip"
                           pattern="[0-9]+x[0-9]+"
                           title='Enter "Other" resolutions in the form ###x###'
                           value="<?= ($Torrent['Resolution'] ?? '') ?>"
                           readonly></input>
                    <script>
                        if ($('#ressel').raw().value == 'Other') {
                            $('#resolution').raw().readOnly = false;
                            $('#resolution').gshow();
                        }
                    </script>
                </td>
            </tr>
            <tr id="audio_tr">
                <td class="label">Audio</td>
                <td>
                    <select name="audioformat">
                        <option>---</option>
                        <?php
                        foreach ($this->AudioFormats as $AudioFormat) {
                            echo "\t\t\t\t\t\t<option value=\"$AudioFormat\"";
                            if ($AudioFormat == ($Torrent['AudioFormat'] ?? false)) {
                                echo " selected";
                            }
                            echo ">$AudioFormat</option>\n";
                        } ?>
                    </select>
                </td>
            </tr>
            <tr id="lang_tr">
                <td class="label">Language</td>
                <td>
                    <select name="lang">
                        <option>---</option>
                        <?php
                        foreach ($this->Languages as $Language) {
                            echo "\t\t\t\t\t\t<option value=\"$Language\"";
                            if ($Language == ($Torrent['Language'] ?? false)) {
                                echo " selected";
                            }
                            echo ">$Language</option>\n";
                        } ?>
                    </select>
                </td>
            </tr>
            <tr id="sub_tr">
                <td class="label">Subbing</td>
                <td>
                    <select name="sub">
                        <option>---</option>
                        <?php
                        foreach ($this->Subbing as $Subbing) {
                            echo "\t\t\t\t\t\t<option value=\"$Subbing\"";
                            if ($Subbing == ($Torrent['Subbing'] ?? false)) {
                                echo " selected";
                            }
                            echo ">$Subbing</option>\n";
                        } ?>
                    </select>
                </td>
            </tr>
            <tr id="trans_tr">
                <td class="label">Translation Group (optional)</td>
                <td><input type="text"
                           id="subber"
                           name="subber"
                           size="60"
                           value="<?= display_str($Torrent['Subber'] ?? '') ?>"/></td>
            </tr>
            <tr id="censored_tr">
                <td class="label">Censored?</td>
                <td>
                    <input type="checkbox"
                           name="censored"
                           value="1" <?= (($Torrent['Censored'] ?? 1) ? 'checked ' : '') ?>/>
                </td>
            </tr>
            <?php if ($this->NewTorrent) { ?>
                <tr id="tags_tr">
                    <td class="label tooltip" title="Comma seperated list of tags">Tags</td>
                    <td>
                        <?php
                        $GenreTags = G::$Cache->get_value('genre_tags');
                        if (!$GenreTags) {
                            G::$DB->query("
      SELECT Name
      FROM tags
      WHERE TagType = 'genre'
      ORDER BY Name");
                            $GenreTags = G::$DB->collect('Name');
                            G::$Cache->cache_value('genre_tags', $GenreTags, 3600 * 6);
                        }
                        ?>
                        <select id="genre_tags"
                                name="genre_tags"
                                onchange="add_tag(); return false;" <?= ($this->DisabledFlag) ? ' disabled="disabled"' : '' ?>>
                            <option>---</option>
                            <?php foreach (Misc::display_array($GenreTags) as $Genre) { ?>
                                <option value="<?= $Genre ?>"><?= $Genre ?></option>
                            <?php } ?>
                        </select>
                        <input type="text"
                               id="tags"
                               name="tags"
                               size="60"
                               value="<?= display_str(implode(', ', explode(',',
                                   $Torrent['TagList'] ?? ''))) ?>"<?php Users::has_autocomplete_enabled('other'); ?> />
                        <p class="min_padding notes"></p>
                    </td>
                </tr>
                <tr id="cover_tr">
                    <td class="label">Cover Image</td>
                    <td><input type="text"
                               id="image"
                               name="image"
                               size="60"
                               value="<?= display_str($Torrent['Image'] ?? '') ?>"<?= $this->Disabled ?> /></td>
                </tr>
                <?php if (!$this->DisabledFlag && $this->NewTorrent) { ?>
                    <tr id="screenshots_tr">
                        <td class="label">Screenshots</td>
                        <td>
                            <textarea rows="8"
                                      cols="60"
                                      name="screenshots"
                                      id="screenshots"><?= display_str($Torrent['Screenshots'] ?? '') ?></textarea>
                            <p>Enter up to 10 links to samples for the torrent, one per line. The system will
                                automatically remove malformed or invalid links, as well as any links after the 10th.
                                Remember to consult the <a href="/rules.php?p=upload#h1.4">rules for adding
                                    screenshots</a>.</p>
                            <p class="min_padding notes"></p>
                    </tr>
                <?php } ?>
                <tr id="group_desc_tr">
                    <td class="label">Torrent Group Description</td>
                    <td>
                        <p class="min_padding notes"></p>
                        <?php new TEXTAREA_PREVIEW('album_desc', 'album_desc',
                            display_str($Torrent['GroupDescription'] ?? ''), 60, 8, !$this->DisabledFlag,
                            !$this->DisabledFlag, false, [$this->Disabled]); ?>
                    </td>
                </tr>
            <?php } ?>
            <tr id="release_desc_tr">
                <td class="label">Torrent Description (optional)</td>
                <td>
                    <p class="min_padding notes"></p>
                    <?php new TEXTAREA_PREVIEW('release_desc', 'release_desc',
                        display_str($Torrent['TorrentDescription'] ?? ''), 60, 8); ?>
                </td>
            </tr>
            <tr id="anon_tr">
                <td class="label tooltip"
                    title="Checking this will hide your username from other users on the torrent details page. Stats will still be attributed to you.">
                    Upload Anonymously
                </td>
                <td><input type="checkbox"
                           name="anonymous"
                           value="1" <?= (($Torrent['Anonymous'] ?? false) ? 'checked ' : '') ?>/></td>
            </tr>
        </table>
        
        <?php
        $this->foot();
        G::$DB->set_query_id($QueryID);
    }
    
    public function head(): void
    {
        G::$DB->query("
      SELECT COUNT(ID)
      FROM torrents
      WHERE UserID = ?", G::$LoggedUser['ID']);
        [$Uploads] = G::$DB->next_record(); ?>

        <div class="thin">
        <?php if ($this->NewTorrent) { ?>
        <p style="text-align: center;">
            If you would like to use your own torrent file, add the following to it:<br/>
            <?php $Announces = call_user_func_array('array_merge', ANNOUNCE_URLS);
            foreach ($Announces as $Announce) {
                ?>
                Announce: <input type="text"
                                 value="<?= $Announce . '/' . G::$LoggedUser['torrent_pass'] . '/announce' ?>"
                                 size="74"
                                 onclick="this.select();"
                                 readonly="readonly"/> <br/>
                <?php
            } ?>
            Source: <input type="text"
                           value="<?= Users::get_upload_sources()[0] ?>"
                           size="20"
                           onclick="this.select();"
                           readonly="readonly"/>
        <p style="text-align: center;">
            Otherwise, add none of it and simply redownload the torrent file after uploading it. All of the above data
            will be added to it by the site.<br/><br/>
            <strong<?= (($Uploads) ? '' : ' class="important_text"') ?>>
                If you never have before, be sure to read this list of
                <a href="wiki.php?action=article&name=uploadingpitfalls">uploading pitfalls</a>
            </strong>
        </p>
        </p>
    <?php }
        if ($this->Error) {
            echo "\t" . '<p style="color: red; text-align: center;">' . $this->Error . "</p>\n";
        } ?>
        <form class="create_form box pad" name="torrent" action="" enctype="multipart/form-data" method="post" onsubmit="$('#post').raw().disabled = 'disabled';">
        <div>
            <input type="hidden" name="submit" value="true"/>
            <input type="hidden" name="auth" value="<?= G::$LoggedUser['AuthKey'] ?>"/>
            <?php if (!$this->NewTorrent) { ?>
                <input type="hidden" name="action" value="takeedit"/>
                <input type="hidden" name="torrentid" value="<?= display_str($this->TorrentID) ?>"/>
                <input type="hidden" name="type" value="<?= display_str($this->Torrent['CategoryID'] - 1) ?>"/>
                <?php
            } else {
                if ($this->Torrent && $this->Torrent['GroupID']) {
                    ?>
                    <input type="hidden" name="groupid" value="<?= display_str($this->Torrent['GroupID']) ?>"/>
                    <input type="hidden" name="type" value="<?= display_str($this->Torrent['CategoryID'] - 1) ?>"/>
                    <?php
                }
                if ($this->Torrent && ($this->Torrent['RequestID'] ?? false)) {
                    ?>
                    <input type="hidden" name="requestid" value="<?= display_str($this->Torrent['RequestID']) ?>"/>
                    <?php
                }
            } ?>
        </div>
        <?php if ($this->NewTorrent) { ?>
        <table cellpadding="3" cellspacing="1" border="0" class="layout" width="100%">
            <tr>
                <td class="label">Torrent file</td>
                <td><input id="file" type="file" name="file_input" size="50"/></td>
            </tr>
            <tr>
                <td class="label">Type</td>
                <td>
                    <select id="categories"
                            name="type"
                            onchange="Categories()"<?= ($this->DisabledFlag) ? ' disabled="disabled"' : '' ?>>
                        <?php
                        
                        foreach (Misc::display_array($this->Categories) as $Index => $Cat) {
                            $categoryName = $this->Torrent['CategoryName'];
                            echo "\t\t\t\t\t\t<option value=\"$Index\"";
                            if (isset($categoryName) && $Cat === $categoryName) {
                                echo ' selected="selected"';
                            }
                            echo ">$Cat</option>\n";
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </table>
    <?php }//if
        ?>
        <div id="dynamic_form">
        <?php
    }
    
    public function foot(): void
    {
        $Torrent = $this->Torrent; ?>
        </div>
        <table cellpadding="3" cellspacing="1" border="0" class="layout slice" width="100%">
            <?php
            if (!$this->NewTorrent && check_perms('torrents_freeleech')) {
                ?>
                <tr id="freetorrent">
                    <td class="label">Freeleech</td>
                    <td>
                        <select name="freeleech">
                            <?php
                            $FL = ["Normal", "Free", "Neutral"];
                            foreach ($FL as $Key => $Name) {
                                ?>
                                <option value="<?= $Key ?>"<?= ($Key == $Torrent['FreeTorrent'] ? ' selected="selected"' : '') ?>><?= $Name ?></option>
                                <?php
                            } ?>
                        </select>
                        because
                        <select name="freeleechtype">
                            <?php
                            $FL = ["N/A", "Staff Pick", "Perma-FL", "Freeleechizer", "Site-Wide FL"];
                            foreach ($FL as $Key => $Name) {
                                ?>
                                <option value="<?= $Key ?>"<?= ($Key == $Torrent['FreeLeechType'] ? ' selected="selected"' : '') ?>><?= $Name ?></option>
                                <?php
                            } ?>
                        </select>
                    </td>
                </tr>
                <?php
            } ?>
            <tr>
                <td colspan="2" style="text-align: center;">
                    <p>Be sure that your torrent is approved by the <a href="rules.php?p=upload"
                                                                       target="_blank">rules</a>. Not doing this will
                        result in a <strong class="important_text">warning</strong> or <strong class="important_text">worse</strong>.
                    </p>
                    <?php if ($this->NewTorrent) { ?>
                        <p>After uploading the torrent, you will have a one hour grace period during which no one other
                            than you can fill requests with this torrent. Make use of this time wisely, and
                            <a href="requests.php">search the list of requests</a>.</p>
                    <?php } ?>
                    <input id="post" type="submit"<?php if ($this->NewTorrent) {
                        echo ' value="Upload torrent"';
                    } else {
                        echo ' value="Edit torrent"';
                    } ?> />
                </td>
            </tr>
        </table>
        </form>
        </div>
        <?php
    }
}

?>
