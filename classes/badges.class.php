<?php

declare(strict_types=1);

class Badges
{
    /**
     * Awards UserID the given BadgeID
     *
     * @param int $UserID
     * @param int $BadgeID
     *
     * @return bool success?
     */
    public static function award_badge(int $UserID, int $BadgeID): bool
    {
        if (self::has_badge($UserID, $BadgeID)) {
            return false;
        }
        
        $QueryID = G::$DB->get_query_id();
        G::$DB->query("
    INSERT INTO users_badges
      (UserID, BadgeID)
    VALUES
      ($UserID, $BadgeID)");
        G::$DB->set_query_id($QueryID);
        
        G::$Cache->delete_value('user_info_' . $UserID);
        
        return true;
    }
    
    /**
     * Returns true if the given user owns the given badge
     *
     * @return bool|void
     */
    public static function has_badge(int $UserID, int $BadgeID)
    {
        $Badges = self::get_badges($UserID);
        
        if (null !== $Badges) {
            return array_key_exists($BadgeID, $Badges);
        }
    }
    
    /**
     * Given a UserID, returns that user's badges
     *
     * @param int $UserID
     *
     * @return array of BadgeIDs
     * @throws \JsonException
     */
    public static function get_badges(int $UserID): ?array
    {
        return Users::user_info($UserID)['Badges'];
    }
    
    /**
     * Given a UserID, return that user's displayed badges
     *
     * @return array of BadgeIDs
     */
    public static function get_displayed_badges(int $UserID): array
    {
        $Result = [];
        
        $Badges = self::get_badges($UserID);
        
        foreach ($Badges as $Badge => $Displayed) {
            if ($Displayed) {
                $Result[] = $Badge;
            }
        }
        
        return $Result;
    }
    
    public static function display_badges($BadgeIDs, $Tooltip = false): string
    {
        $html = "";
        foreach ($BadgeIDs as $BadgeID) {
            $html .= self::display_badge($BadgeID, $Tooltip);
        }
        
        return $html;
    }
    
    /**
     * Creates HTML for displaying a badge.
     *
     * @param bool $Tooltip Should HTML contain a tooltip?
     *
     * @return string HTML
     */
    public static function display_badge(int $BadgeID, bool $Tooltip = false): string
    {
        $html = "";
        
        if (($Badges = G::$Cache->get_value('badges')) && array_key_exists($BadgeID, $Badges)) {
            extract($Badges[$BadgeID]);
        } else {
            self::update_badge_cache();
            if (($Badges = G::$Cache->get_value('badges')) && array_key_exists($BadgeID, $Badges)) {
                extract($Badges[$BadgeID]);
            } else {
                global $Debug;
                $Debug->analysis('Invalid BadgeID ' . $BadgeID . ' requested.');
            }
        }
        
        if ($Tooltip) {
            $html .= '<a class="badge_icon"><img class="tooltip" alt="' . $Name . '" title="' . $Name . '</br>' . $Description . '" src="' . $Icon . '" /></a>';
        } else {
            $html .= '<a class="badge_icon"><img alt="' . $Name . '" title="' . $Name . '" src="' . $Icon . '" /></a>';
        }
        
        return $html;
    }
    
    private static function update_badge_cache(): void
    {
        $QueryID = G::$DB->get_query_id();
        
        G::$DB->query("
        SELECT
        ID, Icon, Name, Description
        FROM badges");
        
        $badges = [];
        if (G::$DB->has_results()) {
            while ([$id, $icon, $name, $description] = G::$DB->next_record()) {
                $badges[$id] = ['Icon' => $icon, 'Name' => $name, 'Description' => $Description];
            }
            G::$Cache->cache_value('badges', $badges);
        }
        
        G::$DB->set_query_id($QueryID);
    }
    
    /**
     * @return mixed|void
     */
    public static function get_all_badges()
    {
        if (($Badges = G::$Cache->get_value('badges'))) {
            return $Badges;
        }
        
        self::update_badge_cache();
        
        return G::$Cache->get_value('badges');
    }
}
