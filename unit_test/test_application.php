<?php

require_once("path.php");
require_once(BASE.'include/maintainer.php');
require_once(BASE.'include/user.php');
require_once(BASE.'include/version.php');
require_once(BASE.'include/application.php');

$test_email = "testemail@somesite.com";
$test_password = "password";

/* test that Application::delete() properly deletes data dependent on */
/* having an application */
//TODO: need to test that we delete all urls, maintainers and other things
//      tested under an application
function test_application_delete()
{
    test_start(__FUNCTION__);

    global $test_email, $test_password;

    $oUser = new User();

    /* delete the user if they already exist */
    if($oUser->login($test_email, $test_password) == SUCCESS)
    {
        $oUser->delete();
        $oUser = new User();
    }

    /* create the user */
    $retval = $oUser->create("testemail@somesite.com", "password", "Test user", "20051020");
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

    /* login the user */
    $retval = $oUser->login($test_email, $test_password);
    if($retval != SUCCESS)
    {
        echo "Got '".$retval."' instead of SUCCESS(".SUCCESS.")\n";
        return false;
    }

    /* make this user an admin so we can create applications without having them queued */
    $hResult = query_parameters("INSERT into user_privs values ('?', '?')",
                                $oUser->iUserId, "admin");

    $oApp = new Application();
    $oApp->sName = "Some application";
    $oApp->sDescription = "some description";
    $oApp->submitterId = $oUser->iUserId;
    if(!$oApp->create())
    {
        $oUser->delete(); /* clean up the user we created prior to exiting */
        echo "Failed to create application!\n";
        return false;
    }

    $iAppId = $oApp->iAppId; /* use the iAppId of the application we just created */
    
    $iVersionIdBase = 400000;
    for($iVersionIdIndex = 0; $iVersionIdIndex < 10; $iVersionIdIndex++)
    {
        $iVersionId = $iVersionIdBase + $iVersionIdIndex;

        $oVersion = new Version();
        $oVersion->versionName = "Some Version".$iVersionId;
        $oVersion->description = "Some Version description".$iVersionId;
        $oVersion->iAppId = $oApp->iAppId;
        $oVersion->iVersionId = $iVersionId;
        
        if(!$oVersion->create())
        {
            delete_app_and_user($oApp, $oUser);
            echo "Failed to create version!\n";
            return false;
        }
    }
    
            
    delete_app_and_user($oApp, $oUser);
    
    $sQuery = "SELECT appId
                      FROM appVersion
                      WHERE appId = '?'";
                      
    if($hResult = query_parameters($sQuery, $iAppId))
    {
        $iRows = mysql_num_rows($hResult);
        if($iRows > 0)
        {
            echo "Found '".$iRows."' versions for this application left over!";
            return false;
        }
    }
    
    return true;
    
}

function delete_app_and_user($oApp, $oUser)
{
    $oApp->delete();
    $oUser->delete();
}

if(!test_application_delete())
    echo "test_application_delete() failed!\n";
else
    echo "test_application_delete() passed!\n";
    
?>

