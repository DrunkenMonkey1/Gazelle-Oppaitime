<?
$IPs = [];
$DB->query("SELECT IP FROM users_main WHERE Enabled = '1'");
while(list($EncIP) = $DB->next_record()) {
  $IPs[] = Crypto::decrypt($EncIP);
}
$DB->query("CREATE TEMPORARY TABLE users_ips_decrypted (IP VARCHAR(45) NOT NULL)");
$DB->query("INSERT INTO users_ips_decrypted (IP) VALUES('".implode("'),('", $IPs)."')");
$DB->query("TRUNCATE TABLE users_geodistribution");
$DB->query("
  INSERT INTO users_geodistribution
    (Code, Users)
  SELECT g.Code, COUNT(u.IP) AS Users
  FROM geoip_country AS g
    JOIN users_ips_decrypted AS u ON INET_ATON(u.IP) BETWEEN g.StartIP AND g.EndIP
  GROUP BY g.Code
  ORDER BY Users DESC");
$DB->query("DROP TABLE users_ips_decrypted");
$Cache->delete_value('geodistribution');
?>
