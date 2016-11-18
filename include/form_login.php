<?php
/**************/
/* Login Form */
/**************/

/* Pass on the URL we should return to after log-in */
global $aClean;

$sUserEmail = (!empty($aClean['sUserEmail']) ? $aClean['sUserEmail'] : '');
$sReturnTo = (!empty($aClean['sReturnTo']) ? $aClean['sReturnTo'] : '');

?>

<!-- start of login form -->
<script>
<!--
$(document).ready(function()
{
    $("button#send_passwd").click(function(){
        $("input[name='sCmd']").val('send_passwd');
        $("form#sFlogin").submit();
    });
});
//-->
</script>

<h1 class="whq-app-title">WineHQ AppDB Login</h1>

<form method="post" id="sFlogin" action="account.php" class="form-horizontal">

<div class="form-group">
    <label class="col-sm-2 control-label">Email</label>
    <div class="col-sm-10">
        <input type="text" name="sUserEmail" value='<?php echo $sUserEmail; ?>' class="form-control">
    </div>
</div>
<div class="form-group">
    <label class="col-sm-2 control-label">Password</label>
    <div class="col-sm-10">
        <input type="password" name="sUserPassword" class="form-control">
    </div>
</div>
<div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
        <button type="submit" name="sLogin" class="btn btn-default"><i class="fa fa-sign-in"></i> Login</button>
    </div>
</div>

<div class="form-group">
    <label class="col-sm-2 control-label">Don't have an account yet?</label>
    <div class="col-sm-10">
        <a href="account.php?sCmd=new" class="btn btn-default"><i class="fa fa-user-plus"></i> Create a New Account</a>
    </div>
</div>

<div class="form-group">
    <label class="col-sm-2 control-label">Lost your password?</label>
    <div class="col-sm-10">
        <button id="send_passwd" class="btn btn-default"><i class="fa fa-envelope-o"></i> Email a New Password</button>
    </div>
</div>

<input type="hidden" name="sCmd" value="do_login">
<input type="hidden" name="sExtReferer" value="<?php echo $_SERVER['HTTP_REFERER']; ?>">
<input type="hidden" name="sReturnTo" value="<?php echo $sReturnTo; ?>">
</form>
