<?php

/* unit tests for user class */

require_once("path.php");
require_once("test_common.php");
require_once(BASE."include/incl.php");
require_once(BASE."include/user.php");
require_once(BASE."include/application.php");

/* TODO: check permissions functions */

$test_email = "testemail@somesite.com";
$test_password = "password";

/* NOTE: test_user_login() relies on this function leaving the test user */
/* in the database */
function test_user_create()
{
    test_start(__FUNCTION__);

    global $test_email, $test_password;

    if(!($oUser = create_and_login_user($test_email, $test_password)))
    {
        return false;
    }

    /* try creating the user again, see that we get USER_CREATE_EXISTS */
    $retval = $oUser->create($test_email, $test_password, "Test user", "20051020");
    if($retval != USER_CREATE_EXISTS)
    {
        echo "Got '".$retval."' instead of USER_CREATE_EXISTS(".USER_CREATE_EXISTS.")\n";
        return false;
    }

    return true;
}

/* NOTE: relies on test_create_user() being run first and leaving a user */
/*  created in the db */
function test_user_login()
{
    test_start(__FUNCTION__);

    global $test_email, $test_password;

    /* test that correct information results in a correct login */
    $oUser = new User();
    $retval = $oUser->login($test_email, $test_password);
    if($retval != SUCCESS)
    {
        echo "Test that correct information results in a correct login FAILED\n";
        echo "Got '".$retval."' instead of SUCCESS(".SUCCESS.")\n";
        return false;
    }

    /* test that incorrect user results in a login failed */
    $oUser = new User();
    $retval = $oUser->login("some nutty username", $test_password);
    if($retval != USER_LOGIN_FAILED)
    {
        echo "Test that incorrect user results in a failed login FAILED\n";
        echo "Got '".$retval."' instead of SUCCESS(".SUCCESS.")\n";
        return false;
    }

    /* test that incorrect password results in a login failed */
    $oUser = new User();
    $retval = $oUser->login($test_email, "some password");
    if($retval != USER_LOGIN_FAILED)
    {
        echo "Test that incorrect passowrd results in a login failed FAILED\n";
        echo "Got '".$retval."' instead of SUCCESS(".SUCCESS.")\n";
        return false;
    }

    return true;
}


function test_user_update_set_test($realname, $winerelease)
{
    global $test_email, $test_password;

    /* log the user in */
    $oUser = new User();
    $retval = $oUser->login($test_email, $test_password);
    if($retval != SUCCESS)
    {
        echo "Got '".$retval."' instead of SUCCESS(".SUCCESS.")\n";
        return false;
    }

    /* modify the users realname and wine release */
    $oUser->sRealname = $realname;
    $oUser->sWineRelease = $winerelease;
    $oUser->update(); /* save the changes */

    /* log the user in again */
    $oUser = new User();
    $retval = $oUser->login($test_email, $test_password);
    if($retval != SUCCESS)
    {
        echo "Got '".$retval."' instead of SUCCESS(".SUCCESS.")\n";
        return false;
    }

    /* make sure the realname and wine release match */
    if($oUser->sRealname != $realname)
    {
        echo "Realname of '".$oUser->sRealname."' doesn't match expected realname of '".$realname."'\n";
        return false;
    }

    if($oUser->sWineRelease != $winerelease)
    {
        echo "Wine release of '".$oUser->sWineRelease."' doesn't match expected wine release of '".$winerelease."'\n";
        return false;
    }

    return true;
}

/* test that we can set values and call user::update() and have the values be saved */
function test_user_update()
{
    test_start(__FUNCTION__);

    global $test_email, $test_password;

    if(!test_user_update_set_test("some bogus realname", "some crazy wine release"))
    {
        return false;
    }

    if(!test_user_update_set_test("some new bogus realname", "some new crazy wine release"))
    {
        return false;
    }

    return true;
}

function test_user_delete()
{
    test_start(__FUNCTION__);

    global $test_email, $test_password;

    /* login the user */
    $oUser = new User();
    $retval = $oUser->login($test_email, $test_password);
    if($retval != SUCCESS)
    {
        echo "Got '".$retval."' instead of SUCCESS(".SUCCESS.")\n";
        return false;
    }

    /* delete the user */
    $oUser->delete();

    /* try to log in again */
    $oUser = new User();
    $retval = $oUser->login($test_email, $test_password);
    if($retval != USER_LOGIN_FAILED)
    {
        echo "Got '".$retval."' instead of USER_LOGIN_FAILED(".USER_LOGIN_FAILED.")\n";
        return false;
    }


    /* now create the user again and see that it is created successfully */

    /* create the user */
    $oUser = new User();
    $retval = $oUser->create($test_email, $test_password, "Test user", "20051020");
    if($retval != SUCCESS)
    {
        if($retval == USER_CREATE_EXISTS)
            echo "The user already exists!\n";
        else if($retval == USER_LOGIN_FAILED)
            echo "User login failed!\n";
        else
            echo "ERROR: UNKNOWN ERROR!!\n";
            
        return false;
    }

    return true;
}

function test_user_getpref_setpref()
{
    test_start(__FUNCTION__);

    global $test_email, $test_password;

    /* login the user */
    $oUser = new User();
    $retval = $oUser->login($test_email, $test_password);
    if($retval != SUCCESS)
    {
        echo "Got '".$retval."' instead of SUCCESS(".SUCCESS.")\n";
        return false;
    }

    /* set a preference and retrieve it */
    $pref_key = "testpreference";
    $pref_value = "test value";
    $oUser->setPref($pref_key, $pref_value);

    $got_pref = $oUser->getPref($pref_key);
    if($got_pref != $pref_value)
    {
        echo "Expected preference value of '".$pref_value."' got preference value of '".$got_pref."'\n";
        return false;
    }
    
    return true;
}

function test_user_update_password()
{
    test_start(__FUNCTION__);

    global $test_email, $test_password;

    /* login the user */
    $oUser = new User();
    $retval = $oUser->login($test_email, $test_password);
    if($retval != SUCCESS)
    {
        echo "Got '".$retval."' instead of SUCCESS(".SUCCESS.")\n";
        return false;
    }

    /* change the users password to something new */
    $sNewPassword = $test_password.$test_password;
    if(!$oUser->update_password($sNewPassword))
    {
        echo "user::update_password() failed to update password to '".$sNewPassword."'\n";
        return false;
    }

    /* log the user in again, using the new password this time */
    $oUser = new User();
    $retval = $oUser->login($test_email, $sNewPassword);
    if($retval != SUCCESS)
    {
        echo "Failed to login with new password, got '".$retval."' instead of SUCCESS(".SUCCESS.")\n";
        return false;
    }

    /* change the password back to the original one */
    if(!$oUser->update_password($test_password))
    {
        echo "user::update_password() failed, unable to restore password to .".$test_password."'\n";
        return false;
    }

    return true;
}

/*************************/
/* Main test routines */

if(!test_user_create())
{
    echo "test_user_create() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_user_create() passed\n";
}

if(!test_user_login())
{
    echo "test_user_login() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_user_login() passed\n";
}

if(!test_user_update())
{
    echo "test_user_update() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_user_update() passed\n";
}

if(!test_user_delete())
{
    echo "test_user_delete() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_user_delete() passed\n";
}

if(!test_user_getpref_setpref())
{
    echo "test_user_getpref_setpref() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_user_getpref_setpref() passed\n";
}

if(!test_user_update_password())
{
    echo "test_user_update_password() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_user_update_password() passed\n";
}

// perform tests related to user password migration
include_once("test_user_password_migration.php");

/* Perform the maintainer tests here because they require that a user we can log into */
/* and we want to save on having to clean up the user by duplicating the cleanup code below */
include_once("test_maintainer.php");


/* TODO: the rest of the user member functions we don't currently test */

/* clean up the user we created during the tests */
/* so the unit test leaves no trace that it ran */
$oUser = new User();

/* delete the user if they already exist */
if($oUser->login($test_email, $test_password) == SUCCESS)
{
    $oUser->delete();
    $oUser = new User();
}

