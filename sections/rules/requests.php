<?
//Include the header
View::show_header('Request Rules');
?>
<div class="thin">
  <div class="header">
    <h2 class="center">Requests</h2>
  </div>
  <div class="box pad rule_summary" style="padding: 10px 10px 10px 20px;">
    <ul>
      <li>
        <strong>Uploads fulfilling a request must actually meet the request requirements.</strong> It doesn't matter if you think your upload is better than what was requested, or even if the requested content doesn't exist and the request is unfillable. You may not fill a request with any upload that does not meet all of the given request criteria. If you feel that the requester made a mistake, contact them and get them to change the request before you try to fill it.
      </li>
      <li>
        <strong>Do not make requests for torrents that break the rules.</strong> It is your responsibility that the request follows the rules. Your request will be deleted, and you will not get your bounty back. Requests cannot be more specific than the upload (and trumping) rules.
      </li>
      <li>
        <strong>Put format specifics in the request description.</strong> If you're requesting a particular container, resolution, translation group, etc., put that information in the request description. Do not put it in the title of the request.
      </li>
      <li>
        <strong>Only one title (movie, game, etc.) per request.</strong> No requests for multiple titles or vague requirements. You may ask for any of several formats, but you cannot demand all of them. For example, you may ask for either an MKV or OGM, but not both formats. Game requests can consist of only one game, but may span a range of different versions. Such a request will be filled with any one version of that title.
      </li>
      <li>
        <strong>When uploading to fill a request, use the [Upload request] link on the request's page.</strong> This will autofill some of the metadata for the torrent, such as title and artist. This prevents some errors that may cause your upload to break the rules, have your request unfilled, have your bounty removed, and possibly cause you to receive a warning.
      </li>
      <li>
        <strong>Do not unfill requests for trivial reasons.</strong> If you did not specify in your request what you wanted (such as encoding or a particular edition), it's your fault. Do not unfill and later change the description. Do not unfill requests if you are unsure of what you are doing. Ask for help from <a href="/staff.php">first-line support or staff</a> in that case. You may only unfill the request if the torrent does not fit your specifications stated clearly in the request.
      </li>
      <li>
        <strong>All users must have an equal chance to fill a request.</strong> Exchanging favors for other users is probably fine, but abusing the request system is not tolerated. That includes making specific requests for certain users (whether explicitly named or not). Making requests and then unfilling so that one particular user can fill the request is not allowed. If reported, both the requester and user filling the request will receive a warning and lose the request bounty.
      </li>
      <li>
        <strong>No manipulation of the requester for bounty.</strong> The bounty is a reward for helping other users &mdash; it should not be a ransom. Any user who openly refuses to fill a request unless the bounty is increased will face harsh punishment if they're being a shithead about it.
      </li>
    </ul>
  </div>
<? include('jump.php'); ?>
</div>
<?
View::show_footer();
?>
