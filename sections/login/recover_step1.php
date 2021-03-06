<?php declare(strict_types=1);
View::show_header('Recover Password', 'validate');
echo $Validate->GenerateJS('recoverform');
?>
<form class="auth_form" name="recovery" id="recoverform" method="post" action="" onsubmit="return formVal();">
  <div style="width: 320px;">
    <span class="titletext">Reset your password - Step 1</span><br /><br />
<?php
if (empty($Sent) || (!empty($Sent) && 1 != $Sent)) {
    if (!empty($Err)) {
        ?>
    <strong class="important_text"><?=$Err ?></strong><br /><br />
<?php
    } ?>
    An email will be sent to your email address with information on how to reset your password.<br /><br />
    <table class="layout" cellpadding="2" cellspacing="1" border="0" align="center">
      <tr valign="top">
        <td align="right">Email address:&nbsp;</td>
        <td align="left"><input type="email" name="email" id="email" class="inputtext" /></td>
      </tr>
      <tr>
        <td colspan="2" align="right"><input type="submit" name="reset" value="Reset!" class="submit" /></td>
      </tr>
    </table>
<?php
} else { ?>
  An email has been sent to you; please follow the directions in that email to reset your password.
<?php
} ?>
  </div>
</form>
<?php
View::show_footer(['recover' => true]);
?>
