<?php

require_once("path.php");
require_once("test_common.php");
require_once(BASE."include/appData.php");
require_once(BASE."include/url.php");

function test_appData_listSubmittedBy()
{
    $bSuccess = true;

    test_start(__FUNCTION__);

    $sTestEmail = __FUNCTION__."@localhost.com";
    $sTestPassword = "password";
    if(!$oUser = create_and_login_user($sTestEmail, $sTestPassword))
    {
        error("Failed to create and log in user");
        return FALSE;
    }

    /* Create a queued Url, which will be stored in the appData table */
    $oUrl = new Url;
    $bResult = $oUrl->create("test description", "http://testurl", 1, null, true);
    if(!$bResult)
    {
        $bSuccess = false;
        error("Failed to create Url");
    } else
    {
        $shReturn = appData::listSubmittedBy($oUser->iUserId, true);
        if(!$shReturn)
        {
            $bSuccess = false;
            error("Got empty list.");
        } else
        {
            /* There should be two lines; one header and one for the linked data */
            $iExpected = 2;
            $iReceived = substr_count($shReturn, "</tr>");
            if($iExpected != $iReceived)
            {
                error("Got $iReceived rows instead of $iExpected.");
                $bSuccess = false;
            }
        }
    
        /* Clean up */
        /* This is needed for deleting the entry */
        $oUser->addPriv("admin");
    
        if(!$oUrl->purge())
        {
            $bSuccess = false;
            error("Failed to delete oUrl!");
        }
    
        $oUser->delete();
    }
    
    return $bSuccess;
}

if(!test_appData_listSubmittedBy())
{
    echo "test_appData_listSubmittedBy() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_appData_listSubmittedBy() passed\n";
}


