<?php

declare(strict_types=1);

class Top10View
{
    public static function render_linkbox($Selected): void
    {
        ?>
        <div class="linkbox">
            <a href="top10.php?type=torrents" class="brackets"><?= self::get_selected_link("Torrents",
                    "torrents" == $Selected) ?></a>
            <a href="top10.php?type=users" class="brackets"><?= self::get_selected_link("Users",
                    "users" == $Selected) ?></a>
            <a href="top10.php?type=tags" class="brackets"><?= self::get_selected_link("Tags",
                    "tags" == $Selected) ?></a>
            <?php if (FEATURE_DONATE) { ?>
                <a href="top10.php?type=donors" class="brackets"><?= self::get_selected_link("Donors",
                        "donors" == $Selected) ?></a>
            <?php } ?>
        </div>
        <?php
    }
    
    /**
     * @return mixed|void
     */
    private static function get_selected_link($String, $Selected)
    {
        if ($Selected) {
            return "<strong>$String</strong>";
        }
        
        return $String;
    }
    
    public static function render_artist_tile($Artist, $Category): void
    {
        if (self::is_valid_artist($Artist)) {
            switch ($Category) {
                case 'weekly':
                case 'hyped':
                    self::render_tile("artist.php?artistname=", $Artist['name'], $Artist['image'][3]['#text']);
                    break;
                default:
                    break;
            }
        }
    }
    
    private static function is_valid_artist($Artist): bool
    {
        return '[unknown]' != $Artist['name'];
    }
    
    private static function render_tile($Url, $Name, $Image): void
    {
        if (!empty($Image)) {
            $Name = display_str($Name); ?>
            <li>
                <a href="<?= $Url ?><?= $Name ?>">
                    <img class="tooltip large_tile"
                         alt="<?= $Name ?>"
                         title="<?= $Name ?>"
                         src="<?= ImageTools::process($Image) ?>"/>
                </a>
            </li>
            <?php
        }
    }
    
    public static function render_artist_list($Artist, $Category): void
    {
        if (self::is_valid_artist($Artist)) {
            switch ($Category) {
                case 'weekly':
                case 'hyped':
                    self::render_list("artist.php?artistname=", $Artist['name'], $Artist['image'][3]['#text']);
                    break;
                default:
                    break;
            }
        }
    }
    
    private static function render_list($Url, $Name, $Image): void
    {
        if (!empty($Image)) {
            $Image = ImageTools::process($Image);
            $Title = "title=\"&lt;img class=&quot;large_tile&quot; src=&quot;$Image&quot; alt=&quot;&quot; /&gt;\"";
            $Name = display_str($Name); ?>
            <li>
                <a class="tooltip_image"
                   data-title-plain="<?= $Name ?>" <?= $Title ?>
                   href="<?= $Url ?><?= $Name ?>"><?= $Name ?></a>
            </li>
            <?php
        }
    }
}
