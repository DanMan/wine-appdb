<?php

// perform tests to verify that users can login with each of the
// possible hashing algorithms and that upon logging in a users
// passsword will be upgraded to the latest hashing scheme

function test_user_password_migration()
{
    test_start(__FUNCTION__);

    $bSuccess = true;

    $sTestEmail = "user_password_migration@localhost.com";
    $sTestPassword = "password";

    if(!($oUser = create_and_login_user($sTestEmail, $sTestPassword)))
        return false;

    // generate the SHA1() of the users password
    $sQuery = "select SHA1('?') as password;";
    $hResult = query_parameters($sQuery, $sTestPassword);
    $oRow = query_fetch_object($hResult);
    $sTestUserPasswordSHA1 = $oRow->password;

    // test that the user was created with the sha1 hash of their password
    $sQuery = "select password from user_list where userid = '?';";
    $hResult = query_parameters($sQuery, $oUser->iUserId);
    $oRow = query_fetch_object($hResult);
    if($sTestUserPasswordSHA1 != $oRow->password)
    {
        error("sTestUserPasswordSHA1 $sTestUserPasswordSHA1 doesn't match oRow->password of $oRow->password after user::create()");
        $bSuccess = false;
    }

    // build an array of the different types of password hashing
    $aPasswordForm = array();
    $aPasswordForm[] = "old_password('?')";
    $aPasswordForm[] = "password('?')";
    $aPasswordForm[] = "sha1('?')";

    foreach($aPasswordForm as $sPasswordForm)
    {
        // manually set the users password
        $sQuery = "update user_list set password = ".$sPasswordForm." where userid = '?';";
        query_parameters($sQuery, $sTestPassword, $oUser->iUserId);

        // attempt to login
        $retval = $oUser->login($sTestEmail, $sTestPassword);
        if($retval != SUCCESS)
        {
            error("Failed to login when the user has an $sPasswordForm generated hash!");
            $bSuccess = false;
        }

        // test that the users password has been updated to the SHA1 hash
        // after the user was logged in
        $sQuery = "select password from user_list where userid = '?';";
        $hResult = query_parameters($sQuery, $oUser->iUserId);
        $oRow = query_fetch_object($hResult);
        if($sTestUserPasswordSHA1 != $oRow->password)
        {
            error("sTestUserPasswordSHA1 $sTestUserPasswordSHA1 doesn't match oRow->password of $oRow->password");
            $bSuccess = false;
        }
    }

    // delete the user we created, we want the database to be left
    // as it was before we ran our tests on it
    $oUser->delete();

    return $bSuccess;
}



if(!test_user_password_migration())
{
    echo "test_user_password_migration() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_user_password_migration() passed\n";
}


