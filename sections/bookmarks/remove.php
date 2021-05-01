<?php

declare(strict_types=1);

authorize();

if (!Bookmarks::can_bookmark($_GET['type'])) {
    error(404);
}

$Type = $_GET['type'];

[$Table, $Col] = Bookmarks::bookmark_schema($Type);

if (!is_number($_GET['id'])) {
    error(0);
}
$PageID = $_GET['id'];

$DB->query("
  DELETE FROM {$Table}
  WHERE UserID = $LoggedUser[ID]
    AND {$Col} = {$PageID}");
$Cache->delete_value(sprintf('bookmarks_%s_%s', $Type, $UserID));

if ($DB->affected_rows()) {
    if ('torrent' === $Type) {
        $Cache->delete_value(sprintf('bookmarks_group_ids_%s', $UserID));
    } elseif ('request' === $Type) {
        $DB->query("
      SELECT UserID
      FROM {$Table}
      WHERE {$Col} = {$PageID}");
        if ($DB->record_count() < 100) {
            // Sphinx doesn't like huge MVA updates. Update sphinx_requests_delta
            // and live with the <= 1 minute delay if we have more than 100 bookmarkers
            $Bookmarkers = implode(',', $DB->collect('UserID'));
            $SphQL = new SphinxqlQuery();
            $SphQL->raw_query(sprintf('UPDATE requests, requests_delta SET bookmarker = (%s) WHERE id = %s', $Bookmarkers, $PageID));
        } else {
            Requests::update_sphinx_requests($PageID);
        }
    }
}
