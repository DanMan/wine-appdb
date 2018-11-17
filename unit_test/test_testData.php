<?php

require_once("path.php");
require_once(BASE."include/version.php");

function test_testData_getNewestTestidFromVersionId()
{
    test_start(__FUNCTION__);
    $sTestEmail = __FUNCTION__."@localhost.com";
    $sTestPassword = "password";
    if(!$oUser = create_and_login_user($sTestEmail, $sTestPassword))
    {
        echo "Failed to create and log in user\n";
        return FALSE;
    }

    $iVersionId = 65555;

    $oOldTestData = new testData();
    $oOldTestData->iVersionId = $iVersionId;
    $oOldTestData->sTestedRelease = '0.9.50.';
    if(!$oOldTestData->create())
      error("oOldTestData->create() failed");

    $oNewTestData = new testData();
    $oNewTestData->iVersionId = $iVersionId;
    $oNewTestData->sTestedRelease = '0.9.51.';
    if(!$oNewTestData->create())
      error("oNewTestData->create() failed");

    $oUser->addPriv("admin");
    $oOldTestData->unQueue();

    /* Now the oldTestData should be listed as current, because the new one is queued */
    $iExpected = $oOldTestData->iTestingId;
    $iReceived = testData::getNewestTestidFromVersionId($iVersionId);
    if($iExpected != $iReceived)
    {
        error("Got testData id of $iReceived instead of $iExpected!");
        $oOldTestData->purge();
        $oNewTestData->purge();
        $oUser->delete();
        return FALSE;
    }

    $oOldTestData->purge();
    $oNewTestData->purge();
    $oUser->delete();

    return TRUE;
}

if(!test_testData_getNewestTestidFromVersionId())
{
    echo "test_testData_getNewestTestidFromVersionId() failed!\n";
    $bTestSuccess = false;
} else
    echo "test_testData_getNewestTestidFromVersionId() passed\n";

