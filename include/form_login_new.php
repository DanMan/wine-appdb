<?php
/********************/
/* New Account Form */
/********************/

global $aClean;

$sWineList = make_bugzilla_version_list("sWineRelease", isset($aClean['sWineRelease']) ? $aClean['sWineRelease'] : '');
$sUserEmail = (!empty($aClean['sUserEmail']) ? $aClean['sUserEmail'] : '');
$sUserRealname = (!empty($aClean['sUserRealname']) ? $aClean['sUserRealname'] : '');
$sReturnTo = (!empty($aClean['sReturnTo']) ? $aClean['sReturnTo'] : '');

?>

<h1 class="whq-app-title">WineHQ AppDB Create Account</h1>

<form method="post" name="sFnew" action="account.php" class="form-horizontal">
<div class="form-group">
    <label class="col-sm-2 control-label">Email</label>
    <div class="col-sm-10">
        <input type="text" name="sUserEmail" value='<?php echo $sUserEmail; ?>' class="form-control">
    </div>
</div>
<div class="form-group">
    <label class="col-sm-2 control-label">Real Name</label>
    <div class="col-sm-10">
        <input type="text" name="sUserRealname" value='<?php echo $sUserRealname ?>' class="form-control">
    </div>
</div>
<div class="form-group">
    <label class="col-sm-2 control-label">Wine Version</label>
    <div class="col-sm-10">
        <?php echo $sWineList; ?>
    </div>
</div>
<div class="form-group">
    <label class="col-sm-2 control-label"></label>
    <div class="col-sm-10">
        <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_KEY; ?>"></div>
    </div>
</div>
<div class="form-group">
    <div class="col-sm-offset-2 col-sm-10">
        <button type="submit" name="sCreate" class="btn btn-default"><i class="fa fa-sign-in"></i> Create Account</button>
    </div>
</div>
<input type="hidden" name="sCmd" value="do_new">
<input type="hidden" name="sExtReferer" value="<?php echo $_SERVER['HTTP_REFERER']; ?>">
<input type="hidden" name="sReturnTo" value="<?php echo $sReturnTo; ?>">
</form>
