<?php
require_once(BASE."include/util.php");

$aClean = array(); //array of filtered user input

$aClean['sUserEmail'] = makeSafe($_POST['sUserEmail']);

/**************/
/* Login Form */
/**************/
echo '<form method="post" name="flogin" action="account.php">',"\n";
echo html_frame_start("Login to Application DB","400","",0)
?>

<!-- start of login form -->
<script type="text/javascript">
<!--
function cmd_send_passwd() {
    document.flogin.cmd.value = "send_passwd";
    document.flogin.submit();
}
//-->
</script>

<table border="0" width="100%" cellspacing=0 cellpadding="10">  
    <tr>
        <td class=color1> E-mail </td> 
        <td class=color0> <input type="text" name="sUserEmail" value='<?php if(!empty($aClean['sUserEmail'])) echo $aClean['sUserEmail']?>'> </td>
    </tr>
    <tr>
        <td class=color1> Password </td> 
        <td class=color0> <input type="password" name="sUserPassword"> </td>
    </tr>
    <tr>
        <td colspan=2 align=center class=color3>
            <input type="submit" name="login" value="  Login  " class=button>
        </td>
      </tr>
  </table>
  
<!-- end of login form -->

<?php

echo html_frame_end("&nbsp;");
echo '<input type="hidden" name="cmd" value="do_login">',"\n";
echo '<input type="hidden" name="ext_referer" value="'.$_SERVER['HTTP_REFERER'].'">',"\n";
echo '</form>',"\n";

?>
   
  <p align=center>Don't have an account yet?<br>
  [<a href="account.php?cmd=new" onMouseOver="document.status='';return true;">Create a New Account</a>]</p>

  <p align=center>Lost your password?<br>
  [<a href="javascript:cmd_send_passwd();" onMouseOver="document.status='';return true;">Email a New Password</a>]</p>

<?php

echo p(),p(),p();

?>
