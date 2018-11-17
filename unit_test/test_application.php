<?php

require_once("path.php");
require_once(BASE.'include/maintainer.php');
require_once(BASE.'include/user.php');
require_once(BASE.'include/version.php');
require_once(BASE.'include/application.php');
require_once("test_common.php");

/* test that Application::purge() properly deletes data dependent on */
/* having an application */
//TODO: need to test that we delete all urls, maintainers and other things
//      tested under an application
function test_application_delete()
{
    test_start(__FUNCTION__);

    $sTestEmail = "test_application_delete@somesite.com";
    $sTestPassword = "password";

    if(!$oUser = create_and_login_user($sTestEmail, $sTestPassword))
        return false;
    
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
        error("Failed to create application!");
        return false;
    }

    $iAppId = $oApp->iAppId; /* use the iAppId of the application we just created */
    
    for($iVersionIdIndex = 0; $iVersionIdIndex < 10; $iVersionIdIndex++)
    {
        $oVersion = new Version();
        $oVersion->versionName = "Some Version".$iVersionIdIndex;
        $oVersion->description = "Some Version description".$iVersionIdIndex;
        $oVersion->iAppId = $oApp->iAppId;
        
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
        $iRows = query_num_rows($hResult);
        if($iRows > 0)
        {
            echo "Found '".$iRows."' versions for this application left over!";
            return false;
        }
    }
    
    return true;
    
}


function test_application_getWithRating()
{
    test_start(__FUNCTION__);

    $sTestEmail = "test_application_getwithrating@somesite.com";
    $sTestPassword = "password";

    if(!$oUser = create_and_login_user($sTestEmail, $sTestPassword))
        return false;

    /* make this user an admin so we can create applications without having them queued */
    $hResult = query_parameters("INSERT into user_privs values ('?', '?')",
                                $oUser->iUserId, "admin");

    $oApp = new Application();
    $oApp->sName = "Some application";
    $oApp->sDescription = "some description";
    $oApp->submitterId = $oUser->iUserId;
    if(!$oApp->create())
    {
        $oUser->delete();
        error("Failed to create application!");
        return false;
    }

    $iAppId = $oApp->iAppId; /* use the iAppId of the application we just created */
    
 
    /* Create several versions of the new application to test uniqueness of getWithRating() results */
    
    for($iVersionIdIndex = 0; $iVersionIdIndex < 10; $iVersionIdIndex++)
    {    
        $oVersion = new Version();
        $oVersion->versionName = "Some Version".$iVersionIdIndex;
        $oVersion->description = "Some Version description".$iVersionIdIndex;
        $oVersion->iAppId = $oApp->iAppId;
        
        
        /* Create Several Ratings, some duplicate */
        if ($iVersionIdIndex < 4)
        {
            $oVersion->sTestedRating = "Platinum";
        }
        elseif ($iVersionIdIndex  < 8)
        {
            $oVersion->sTestedRating = "Gold";
        }
        else
        {
            $oVersion->sTestedRating = "Bronze";
        }
        
        if(!$oVersion->create())
        {
            delete_app_and_user($oApp, $oUser);  
            error("Failed to create version!");
            return false;
        }
    }
    

    $iItemsPerPage = 50;
    $iOffset = 0;
    $sRating = 'Bronze';        
    $aApps = Application::getWithRating($sRating, $iOffset, $iItemsPerPage);
    $aTest = array();//array to test the uniqueness our query results
    while(list($i, $iId) = each($aApps)) //cycle through results returned by getWithRating
    {
        if ( in_array($iId, $aTest) ) //if the appId is already in our test results fail unique test
        {
            delete_app_and_user($oApp, $oUser);  
            error("getWithRating failed to return a unique result set");
            return false;
        }
           
        array_push($aTest, $iId); //push the appId on to our test array
      
    }
    
 
    delete_app_and_user($oApp, $oUser);  
    
    
    //test to ensure getWithRating doesn't return applications that are queued
    
    //create user without admin priveliges
    if(!$oUser = create_and_login_user($sTestEmail, $sTestPassword))
        return false;
    
    
    $oApp = new Application();
    $oApp->sName = "Some application";
    $oApp->sDescription = "some description";
    $oApp->submitterId = $oUser->iUserId;
    if(!$oApp->create())
    {
        $oUser->delete(); /* clean up the user we created prior to exiting */
        error("Failed to create application!");
        return false;
    }

    $iAppId = $oApp->iAppId; /* use the iAppId of the application we just created */    
    
    $oVersion = new Version();
    $oVersion->versionName = "Some Version".$iVersionIdIndex;
    $oVersion->description = "Some Version description".$iVersionIdIndex;
    $oVersion->iAppId = $oApp->iAppId;
    $oVersion->sTestedRating = "Bronze";
    $oVersion->objectSetState('queued');
        
    if(!$oVersion->create())
    {
        delete_app_and_user($oApp, $oUser);  
        error("Failed to create version!");
        return false;
    }        
    
    
    $aApps=Application::getWithRating($sRating, $iOffset, $iItemsPerPage);
        
    if ( in_array($iAppId, $aApps )) //if the appId is in our test results fail queued test
    {
        delete_app_and_user($oApp, $oUser);  
        error("getWithRating failed to return a unique result set");
        return false;
    }
    
    delete_app_and_user($oApp, $oUser);

    return true;
} 

function delete_app_and_user($oApp, $oUser)
{
    $bSuccess = true;
    if(!$oApp->purge())
    {
        echo __FUNCTION__."() oApp->purge() failed\n";
        $bSuccess = false;
    }

    if(!$oUser->delete())
    {
        echo __FUNCTION__."() oUser->delete() failed\n";
        $bSuccess = false;
    }

    return $bSuccess;
}

if(!test_application_delete())
{
    echo "test_application_delete() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_application_delete() passed\n";
}
    

if(!test_application_getWithRating())
{
    echo "test_application_getWithRating() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_application_getWithRating() passed\n"; 
}
    


