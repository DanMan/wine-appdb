<?php

/* common functions used in appdb unit tests */

function test_start($sFunctionName)
{
    echo $sFunctionName."() starting\n";
}

// create an application and a version of that application
// return the iVersionId of the created version
function create_version_and_parent_app($sId = "")
{
    $oApp = new application();
    $oApp->sName = "OM App ".$sId;
    if(!$oApp->create())
    {
        error("oApp->create() failed\n");
    }

    $oVersion = new version();
    $oVersion->sName = "OM version ".$sId;
    $oVersion->iAppId = $oApp->iAppId;
    if(!$oVersion->create())
    {
        error("oVersion->create() failed");
    }
    return $oVersion->iVersionId;
}

// delete a version based on the $iVersionId parameter
// and delete its parent application
// NOTE: we enable admin permissions here to ensure that
//       the application and version are deleted
function delete_version_and_parent_app($iVersionId)
{
    $bWasAdmin = $_SESSION['current']->hasPriv("admin");
  
    $_SESSION['current']->addPriv("admin");

    $oVersion = new version($iVersionId);
    $oApp = new application($oVersion->iAppId);
    if(!$oApp->purge())
    {
        echo __FUNCTION__."() oApp->purge() failed, returned false!\n";
    }

    // remove the admin privileges only if the user didn't
    // have them to begin with
    if(!$bWasAdmin)
        $_SESSION['current']->delPriv("admin");
}

function create_and_login_user($sTestEmail, $sTestPassword)
{
    $oUser = new User();

    /* delete the user if they already exist */
    if($oUser->login($sTestEmail, $sTestPassword) == SUCCESS)
    {
        $oUser->delete();
        $oUser = new User();
    }

    // create the user
    // NOTE: user::create() will call user::login() to login the user
    //       if the user creation is successful
    $retval = $oUser->create($sTestEmail, $sTestPassword, "Test user", "20051020");
    if($retval != SUCCESS)
    {
        if($retval == USER_CREATE_EXISTS)
            error("The user already exists!");
        else if($retval == USER_LOGIN_FAILED)
            error("User login failed!");
        else
            error("ERROR: UNKNOWN ERROR!!");
            
        return false;
    }

    return $oUser;
}

function error($sMsg)
{
    $aBT = debug_backtrace();

    // get class, function called by caller of caller of caller
    if(isset($aBT[1]['class']))
        $sClass = $aBT[1]['class'];
    else
        $sClass = "";
    $sFunction = $aBT[1]['function'];
       
    // get file, line where call to caller of caller was made
    $sFile = $aBT[0]['file'];
    $sLine = $aBT[0]['line'];
       
    // build & return the message
    echo "$sClass::$sFunction:$sFile:$sLine $sMsg\n";
}

function run_test($sTestName)
{
    if(!$sTestName())
    {
        global $bTestSuccess;
        echo "$sTestName() failed!\n";
        $bTestSuccess = false;
    } else
    {
        echo "$sTestName() passed.\n";
    }
}


