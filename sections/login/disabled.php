<?php declare(strict_types=1);
View::show_header('Disabled');

if (isset($_POST['email']) && FEATURE_EMAIL_REENABLE) {
    // Handle auto-enable request
    if ('' != $_POST['email']) {
        $Output = AutoEnable::new_request(db_string($_POST['username']), db_string($_POST['email']));
    } else {
        $Output = "Please enter a valid email address.";
    }

    $Output .= "<br><br><a href='login.php?action=disabled'>Back</a>";
}

if ((empty($_POST['submit']) || empty($_POST['username'])) && !isset($Output)) {
    ?>
<p class="warning">
Your account has been disabled.<br>
This is either due to inactivity or rule violation(s).<br><br></p>
<?php if (FEATURE_EMAIL_REENABLE) { ?>
If you believe your account was in good standing and was disabled for inactivity, you may request it be re-enabled via email using the form below.<br>
Please use an email service that actually delivers mail. Outlook/Hotmail is known not to.<br>
Most requests are handled within minutes. If a day or two goes by without a response, try again with a different email or try asking in IRC.<br><br>
<form action="" method="POST">
  <input type="email" class="inputtext" placeholder="Email Address" name="email" required /> <input type="submit" value="Submit" />
  <input type="hidden" name="username" value="<?=$_COOKIE['username']?>" />
</form><br /><br />
<?php } ?>
If you are unsure why your account is disabled, or you wish to discuss this with staff, come to our IRC network at: <?=BOT_SERVER?><br>
And join <?=BOT_DISABLED_CHAN?><br><br>
<strong>Be honest.</strong> At this point, lying will get you nowhere.<br /><br><br>
</p>

<strong>Before joining the disabled channel, please read our <br> <span>Golden Rules</span> which can be found <a style="color: #1464F4;" href="#" onclick="toggle_visibility('golden_rules')">here</a>.</strong> <br><br>

<script type="text/javascript">
function toggle_visibility(id) {
  var e = document.getElementById(id);
  if (e.style.display == 'block') {
    e.style.display = 'none';
  } else {
    e.style.display = 'block';
  }
}
</script>

<div id="golden_rules" class="rule_summary" style="width: 35%; font-weight: bold; display: none; text-align: left;">
<?php Rules::display_golden_rules(); ?>
<br /><br />
</div>

<p class="strong">
If you do not have access to an IRC client, you can use the WebIRC interface provided below.<br />
Please use your <?=SITE_NAME?> username.
</p>
<br />
<form class="confirm_form" name="chat" action="<?echo 'https://chat.'.SITE_DOMAIN.BOT_DISABLED_CHAN?>" target="_blank" method="pre">
  <input type="text" name="nick" width="20" />
  <input type="submit" value="Join WebIRC" />
</form>
<?php
} elseif (!isset($Output)) {
        $Nick = $_POST['username'];
        $Nick = preg_replace('#[^a-zA-Z0-9\[\]\`\^\{\}\|_]#', '', $Nick);
        if (0 == strlen($Nick)) {
            $Nick = SITE_NAME . 'Guest????';
        } elseif (is_numeric(substr($Nick, 0, 1))) {
            $Nick = '_' . $Nick;
        } ?>
<div class="thin">
  <div class="header">
    <h3 id="general">Disabled IRC</h3>
  </div>
  <div class="box pad" style="padding: 10px 0px 10px 0px;">
    <div style="padding: 0px 10px 10px 20px;">
      <p>Please read the topic carefully.</p>
    </div>
    <applet codebase="static/irc/" code="IRCApplet.class" archive="irc.jar,sbox.jar" width="800" height="600" align="center">
      <param name="nick" value="<?=($Nick)?>" />
      <param name="alternatenick" value="<?=SITE_NAME?>Guest????" />
      <param name="name" value="Java IRC User" />
      <param name="host" value="<?=(BOT_SERVER)?>" />
      <param name="multiserver" value="false" />
      <param name="autorejoin" value="false" />
      <param name="command1" value="JOIN <?=BOT_DISABLED_CHAN?>" />
      <param name="gui" value="sbox" />
      <param name="pixx:highlight" value="true" />
      <param name="pixx:highlightnick" value="true" />
      <param name="pixx:prefixops" value="true" />
      <param name="sbox:scrollspeed" value="5" />
    </applet>
  </div>
</div>
<?php
    } else {
        echo $Output;
    }

View::show_footer();
?>
