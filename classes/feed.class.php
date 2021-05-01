<?php

declare(strict_types=1);

class FEED
{
    public function open_feed(): void
    {
        header("Content-type: application/xml; charset=UTF-8");
        echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n", "<rss xmlns:dc=\"http://purl.org/dc/elements/1.1/\" version=\"2.0\">\n\t<channel>\n";
    }
    
    public function close_feed(): void
    {
        echo "\t</channel>\n</rss>";
    }
    
    public function channel($Title, $Description, $Section = ''): void
    {
        echo "\t\t<title>$Title :: " . SITE_NAME . "</title>\n";
        $Site = site_url();
        echo "\t\t<link>$Site$Section</link>\n";
        echo "\t\t<description>$Description</description>\n";
        echo "\t\t<language>en-us</language>\n";
        echo "\t\t<lastBuildDate>" . date('r') . "</lastBuildDate>\n";
        echo "\t\t<docs>http://blogs.law.harvard.edu/tech/rss</docs>\n";
        echo "\t\t<generator>Gazelle Feed Class</generator>\n\n";
    }
    
    public function item($Title, $Description, $Page, $Creator, $Comments = '', $Category = '', $Date = ''): string
    {
        $Date = '' == $Date ? date('r') : date('r', strtotime($Date));
        $Site = site_url();
        $Item = "\t\t<item>\n";
        $Item .= "\t\t\t<title><![CDATA[$Title]]></title>\n";
        $Item .= "\t\t\t<description><![CDATA[$Description]]></description>\n";
        $Item .= "\t\t\t<pubDate>$Date</pubDate>\n";
        $Item .= "\t\t\t<link>$Site$Page</link>\n";
        $Item .= "\t\t\t<guid>$Site$Page</guid>\n";
        if ('' != $Comments) {
            $Item .= "\t\t\t<comments>$Site$Comments</comments>\n";
        }
        if ('' != $Category) {
            $Item .= "\t\t\t<category><![CDATA[$Category]]></category>\n";
        }
        
        return $Item . "\t\t\t<dc:creator>$Creator</dc:creator>\n\t\t</item>\n";
    }
    
    public function retrieve($CacheKey, $AuthKey, $PassKey): void
    {
        global $Cache;
        $Entries = $Cache->get_value($CacheKey);
        if (!$Entries) {
            $Entries = [];
        } else {
            foreach ($Entries as $Item) {
                echo str_replace(['[[PASSKEY]]', '[[AUTHKEY]]'], [display_str($PassKey), display_str($AuthKey)], $Item);
            }
        }
    }
    
    public function populate($CacheKey, $Item): void
    {
        global $Cache;
        $Entries = $Cache->get_value($CacheKey, true);
        if (!$Entries) {
            $Entries = [];
        } elseif (count($Entries) >= 50) {
            array_pop($Entries);
        }
        array_unshift($Entries, $Item);
        $Cache->cache_value($CacheKey, $Entries, 0); //inf cache
    }
}
