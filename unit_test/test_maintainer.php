<?php

require_once("path.php");
require_once(BASE.'include/maintainer.php');

// the maintainer notification system tests have been split out
// into another file
include_once("test_maintainer_notify.php");



/* unit tests for maintainer class */

// test that the maintainer count for a given user is accurate for both
//   maintainers and super maintainers when the user is either a maintainer
//   or a super maintainer
function test_maintainer_getMaintainerCountForUser()
{
    test_start(__FUNCTION__);

    $sTestEmail = __FUNCTION__."@localhost.com";
    $sTestPassword = "password";
    if(!($oUser = create_and_login_user($sTestEmail, $sTestPassword)))
    {
        error("Failed to create and log in user!");
        return FALSE;
    }

    /**
      * make the user a super maintatiner
      */
    $iAppId = 655000;
    $iVersionId = 655200;

    /* queue up this maintainer */
    $oMaintainer = new Maintainer();
    $oMaintainer->iAppId = $iAppId;
    $oMaintainer->iVersionId = $iVersionId;
    $oMaintainer->iUserId = $_SESSION['current']->iUserId;
    $oMaintainer->sMaintainReason = "Some crazy reason";
    $oMaintainer->bSuperMaintainer = TRUE;
    $oMaintainer->create();

    /* and unqueue it to accept the user as a maintainer */
    $oMaintainer->unQueue("Some reply text");
    
    /* see that the user is a super maintainer of the one application we added them to be */
    $iExpected = 1; /* we expect 1 super maintainer for this user */
    $iSuperMaintainerCount = Maintainer::getMaintainerCountForUser($oUser, TRUE);
    if($iSuperMaintainerCount != $iExpected)
    {
        error("Got super maintainer count of '".$iSuperMaintainerCount."' instead of '".$iExpected."'");
        $oUser->delete();
        return false;
    }

    /* maintainer count should be zero */
    $iExpected = 0;
    $iMaintainerCount = Maintainer::getMaintainerCountForUser($oUser, FALSE);
    if($iMaintainerCount != $iExpected)
    {
        error("Got maintainer count of '".$iMaintainerCount."' instead of '".$iExpected."'");
        $oUser->delete();
        return false;
    }

    /* remove maintainership for this user */
    Maintainer::deleteMaintainer($oUser, $iAppId);

    /**
     * make the user a maintainer
     */

    /* queue up this maintainer */
    $oMaintainer = new Maintainer();
    $oMaintainer->iAppId = $iAppId;
    $oMaintainer->iVersionId = $iVersionId;
    $oMaintainer->iUserId = $_SESSION['current']->iUserId;
    $oMaintainer->sMaintainReason = "Some crazy reason";
    $oMaintainer->bSuperMaintainer = FALSE;
    $oMaintainer->create();

    /* and unqueue it to accept the user as a maintainer */
    $oMaintainer->unQueue("Some reply text");

    /* see that the user is a super maintainer of no applications */
    $iExpected = 0; /* we expect 1 super maintainer for this user */
    $iSuperMaintainerCount = Maintainer::getMaintainerCountForUser($oUser, TRUE);
    if($iSuperMaintainerCount != $iExpected)
    {
        error("Got super maintainer count of '".$iSuperMaintainerCount."' instead of '".$iExpected."'");
        $oUser->delete();
        return false;
    }

    /* maintainer count should be one */
    $iExpected = 1;
    $iMaintainerCount = Maintainer::getMaintainerCountForUser($oUser, FALSE);
    if($iMaintainerCount != $iExpected)
    {
        error("Got maintainer count of '".$iMaintainerCount."' instead of '".$iExpected."'");
        $oUser->delete();
        return false;
    }

    /* remove maintainership for this user */
    Maintainer::deleteMaintainer($oUser, $iAppId, $iVersionId);

    $oUser->delete();
    return true;
}

// test that applications a user maintains are accurately reported by
//  maintainer::GetAppsMaintained()
function test_maintainer_getAppsMaintained()
{
    test_start(__FUNCTION__);

    $sTestEmail = __FUNCTION__."@localhost.com";
    $sTestPassword = "password";
    if(!($oUser = create_and_login_user($sTestEmail, $sTestPassword)))
    {
        error("Failed to create and log in user!");
        return FALSE;
    }

    /* make this user an admin so we can create applications without having them queued */
    $hResult = query_parameters("INSERT into user_privs values ('?', '?')",
                                $oUser->iUserId, "admin");

    /* create a application so we have a valid appFamily for the call to user::getAppsMaintained() */
    $oApp = new Application();
    $oApp->sName = "Some application";
    $oApp->sDescription = "some description";
    $oApp->submitterId = $oUser->iUserId;
    if(!$oApp->create())
    {
        error("Failed to create application!");
        $oUser->delete();
        return false;
    }

    /**
      * make the user a super maintatiner
      */
    $iAppId = $oApp->iAppId; /* use the iAppId of the application we just created */
    $iVersionId = 655200;
    $bSuperMaintainer = TRUE;

    /* queue up the maintainership */
    // add to queue
    $oMaintainer = new Maintainer();
    $oMaintainer->iAppId = $iAppId;
    $oMaintainer->iVersionId = $iVersionId;
    $oMaintainer->iUserId = $oUser->iUserId;
    $oMaintainer->sMaintainReason = "Some crazy reason";
    $oMaintainer->bSuperMaintainer = $bSuperMaintainer;
    $oMaintainer->create();

    $statusMessage = $oMaintainer->unQueue("Some reply text"); /* accept the maintainership */

    /* get an array of the apps maintained */
    $aAppsMaintained = maintainer::getAppsMaintained($oUser);

    if(!$aAppsMaintained)
    {
        error("aAppsMaintained is null, we expected a non-null return value!");
        $oUser->delete();
        return false;
    }

    /* get only the first entry from the array of applications maintained */
    /* we only added the user as a maintainer of a single application */
    list($iAppId1, $iVersionId1, $bSuperMaintainer1) = $aAppsMaintained[0];

    /* make sure all parameters match what we added as maintainer information */
    if($iAppId1 != $iAppId)
    {
        error("Expected iAppid of ".$iAppId." but got ".$iAppId1);
        $oUser->delete();
        return false;
    }

    if($iVersionId1 != $iVersionId)
    {
        error("Expected iVersionId of ".$iVersionId." but got ".$iVersionId1);
        $oUser->delete();
        return false;
    }

    if($bSuperMaintainer1 != $bSuperMaintainer)
    {
        error("Expected bSuperMaintainer of ".$bSuperMaintainer." but got ".$bSuperMaintainer1);
        $oUser->delete();
        return false;
    }

    /* remove maintainership for this user */
    Maintainer::deleteMaintainer($oUser, $iAppId);

    /* remove this application */
    $oApp = new Application($iAppId);
    $oApp->purge();

    $oUser->delete();

    return true;
}

// test that unQueueing a queued maintainer request twice is ignored
function test_maintainer_unQueue()
{
    test_start(__FUNCTION__);

    $sTestEmail = __FUNCTION__."@localhost.com";
    $sTestPassword = "password";
    if(!($oFirstUser = create_and_login_user($sTestEmail, $sTestPassword)))
    {
        error("Failed to create and log in first user!");
        return FALSE;
    }

    // create a second user
    $sTestEmail = __FUNCTION__."2nd@localhost.com";
    $sTestPassword = "password";
    if(!($oSecondUser = create_and_login_user($sTestEmail, $sTestPassword)))
    {
        error("Failed to create and log in second user!");
        $oFirstUser->delete();
        return FALSE;
    }

    // make the first user the current user
    // because create_and_login_user() calls user::login()
    // and this sets $_SESSION['current'] so we need
    // to override the create_and_login_user() for the second user
    $_SESSION['current'] = $oFirstUser;


    $iAppId = 655000;
    $iVersionId = 655200;

    $oApp = new Application();
    $oVersion = new Version();
    $oApp->iAppId = $iAppId;
    $oVersion->iVersionId = $iVersionId;

    /* Create a non-super maintainer for a different userId; it should not be affected
       by the other user first becoming a maintainer and then a super maintainer of
       the same application */
    $oSecondUserMaintainer = new Maintainer();
    $oSecondUserMaintainer->iAppId = $iAppId;
    $oSecondUserMaintainer->iVersionId = $iVersionId;
    $oSecondUserMaintainer->iUserId = $oSecondUser->iUserId;
    $oSecondUserMaintainer->sMaintainReason = "I need it";
    $oSecondUserMaintainer->bSuperMaintainer = FALSE;
    if(!$oSecondUserMaintainer->create())
        error("oSecondUserMaintainer->create() failed");

    $oSecondUserMaintainer->unQueue();

    /* Create a super maintainer for a different userId; it should not be affected
       by the other user first becoming a maintainer and then a super maintainer of
       the same application */
    $oSecondUserSuperMaintainer = new Maintainer();
    $oSecondUserSuperMaintainer->iAppId = $iAppId;
    $oSecondUserSuperMaintainer->iVersionId = $iVersionId;
    $oSecondUserSuperMaintainer->iUserId = $oSecondUser->iUserId;
    $oSecondUserSuperMaintainer->sMaintainReason = "I need it";
    $oSecondUserSuperMaintainer->bSuperMaintainer = TRUE;

    // disable admin permissions around the creation of the second maintainer
    // so the maintainer object remains queued
    $oFirstUser->delPriv("admin");
    if(!$oSecondUserSuperMaintainer->create())
        error("oSecondUserSuperMaintainer->create() failed");
    $oFirstUser->addPriv("admin");


    /* Create a non-super maintainer
       It should be removed later because a super maintainer entry for the same
       application is added */
    $oFirstUserMaintainer = new Maintainer();
    $oFirstUserMaintainer->iAppId = $iAppId;
    $oFirstUserMaintainer->iVersionId = $iVersionId;
    $oFirstUserMaintainer->iUserId = $_SESSION['current']->iUserId;
    $oFirstUserMaintainer->sMaintainReason = "The most stupid reason";
    $oFirstUserMaintainer->bSuperMaintainer = FALSE;
    if(!$oFirstUserMaintainer->create())
        error("oFirstUserMaintainer->create() failed");

    $sStatus = $oFirstUserMaintainer->unQueue("");

    /* There should now be 1 maintainer and 0 super maintainers */
    $iExpected = 1;
    $iReceived = maintainer::getMaintainerCountForUser($oFirstUser, FALSE);
    if($iExpected != $iReceived)
    {
        error("Got maintainer count of $iReceived instead of $iExpected");
        error("sStatus is $sStatus");
        maintainer::deleteMaintainersForApplication($oApp);
        maintainer::deleteMaintainersForVersion($oVersion);
        $oFirstUser->delete();
        $oSecondUser->delete();
        return FALSE;
    }

    $iExpected = 0;
    $iReceived = maintainer::getMaintainerCountForUser($oFirstUser, TRUE);
    if($iExpected != $iReceived)
    {
        error("Got super maintainer count of $iReceived instead of $iExpected");
        maintainer::deleteMaintainersForApplication($oApp);
        maintainer::deleteMaintainersForVersion($oVersion);
        $oFirstUser->delete();
        $oSecondUser->delete();
        return FALSE;
    }

    /**
      * make the user a super maintatiner
      */
    /* queue up this maintainer */
    $oFirstUserSuperMaintainer = new Maintainer();
    $oFirstUserSuperMaintainer->iAppId = $iAppId;
    $oFirstUserSuperMaintainer->iVersionId = $iVersionId;
    $oFirstUserSuperMaintainer->iUserId = $_SESSION['current']->iUserId;
    $oFirstUserSuperMaintainer->sMaintainReason = "Some crazy reason";
    $oFirstUserSuperMaintainer->bSuperMaintainer = TRUE;
    if(!$oFirstUserSuperMaintainer->create())
        error("oFirstUserSuperMaintainer->create() failed");

    /* and unqueue it to accept the user as a maintainer */
    $oFirstUserSuperMaintainer->unQueue("Some reply text");

    /* unqueue it again to ensure that unQueueing a maintainer request twice works properly */
    $oFirstUserSuperMaintainer->unQueue("Some other reply text");


    /* see that the user is a super maintainer of the one application we added them to be */
    $iExpected = 1; /* we expect 1 super maintainer for this user */
    $iSuperMaintainerCount = maintainer::getMaintainerCountForUser($oFirstUser, TRUE);
    if($iSuperMaintainerCount != $iExpected)
    {
        error("Got super maintainer count of '".$iSuperMaintainerCount.
              "' instead of '".$iExpected."'");
        maintainer::deleteMaintainersForApplication($oApp);
        maintainer::deleteMaintainersForVersion($oVersion);
        $oFirstUser->delete();
        $oSecondUser->delete();
        return false;
    }

    /* maintainer count should be zero, because unQueue should have removed the
       previous non-super maintainer entry */
    $iExpected = 0;
    $iMaintainerCount = maintainer::getMaintainerCountForUser($oFirstUser, FALSE);
    if($iMaintainerCount != $iExpected)
    {
        error("Got maintainer count of '".$iMaintainerCount.
              "' instead of '".$iExpected."'");
        maintainer::deleteMaintainersForApplication($oApp);
        maintainer::deleteMaintainersForVersion($oVersion);
        $oFirstUser->delete();
        $oSecondUser->delete();
        return false;
    }

    /* Now the maintainer entry for the second user should still be present */
    $iExpected = 1;
    $iReceived = maintainer::getMaintainerCountForUser($oSecondUser, FALSE);
    if($iExpected != $iReceived)
    {
        error("Got maintainer count of $iReceived instead of $iExpected");
        maintainer::deleteMaintainersForApplication($oApp);
        maintainer::deleteMaintainersForVersion($oVersion);
        $oFirstUser->delete();
        $oSecondUser->delete();
        return FALSE;
    }

    // Now the super maintainer request for the second user should still be present
    $oSecondUserSuperMaintainer->unQueue();
    $iExpected = 1;
    $iReceived = maintainer::getMaintainerCountForUser($oSecondUser, TRUE);
    if($iExpected != $iReceived)
    {
        error("Got super maintainer count of $iReceived instead of $iExpected");
        maintainer::deleteMaintainersForApplication($oApp);
        maintainer::deleteMaintainersForVersion($oVersion);
        $oFirstUser->delete();
        $oSecondUser->delete();
        return FALSE;
    }

    // Now that the super maintainer entry was unqueued, the maintainer
    // entry should have been deleted
    $iExpected = 0;
    $iReceived = maintainer::getMaintainerCountForUser($oSecondUser, FALSE);
    if($iExpected != $iReceived)
    {
        error("Got maintainer count of $iReceived instead of $iExpected");
        maintainer::deleteMaintainersForApplication($oApp);
        maintainer::deleteMaintainersForVersion($oVersion);
        $oFirstUser->delete();
        $oSecondUser->delete();
        return FALSE;
    }

    /* remove maintainership for this user */
    maintainer::deleteMaintainersForApplication($oApp);
    maintainer::deleteMaintainersForVersion($oVersion);

    $oFirstUser->delete();
    $oSecondUser->delete();

    return true;
}

/* Test whether a super maintainer request submitted along with an application is also accepted when the application is accepted */
function test_superMaintainerOnAppSubmit()
{
    test_start(__FUNCTION__);

    $sTestEmail = __FUNCTION__."@localhost.com";
    $sTestPassword = "password";
    if(!($oUser = create_and_login_user($sTestEmail, $sTestPassword)))
    {
        error("Failed to create and log in user!");
        return FALSE;
    }

    $iAppId = 655000;
    $iVersionId = 655200;

    $oApp = new Application($iAppId);

    /* The user wants to be a super maintainer */
    $oApp->iMaintainerRequest = SUPERMAINTAINER_REQUEST;

    /* Make sure the user is not an admin, so the app will be queued */
    $oUser->delPriv("admin");

    $oApp->create();

    /* Make the user an admin so the app can be unqueued */
    $oUser->addPriv("admin");

    $oApp->unQueue();

    /* The user should now be a super maintainer */
    $iExpected = 1;
    $iGot = Maintainer::getMaintainerCountForUser($oUser, TRUE);

    if($iGot != $iExpected)
    {
        error("Got maintainer count of '$iGot' instead of '$iExpected'");
        $oUser->delete();
        return false;
    }

    Maintainer::deleteMaintainer($oUser, $iAppId);

    $oApp->purge();
    $oUser->delete();

    return true;
}

/* deleteMaintainersForVersion() should fail if versionId = 0
   Otherwise it will delete all super maintainers */
function test_maintainer_deleteMaintainersForVersion()
{
    test_start(__FUNCTION__);

    $sTestEmail = __FUNCTION__."@localhost.com";
    $sTestPassword = "password";
    if(!($oUser = create_and_login_user($sTestEmail, $sTestPassword)))
    {
        error("Failed to create and log in user!");
        return FALSE;
    }

    $oMaintainer = new maintainer();
    $oMaintainer->iAppId = 655000;
    $oMaintainer->iVersionId = 0;
    $oMaintainer->iUserId = 655000;
    $oMaintainer->sMaintainReason = "Silly reason";
    $oMaintainer->bSuperMaintainer = 1;

    $oMaintainer->create();

    $oVersion = new version();
    $oVersion->iVersionId = 0;

    if(maintainer::deleteMaintainersForVersion($oVersion) !== FALSE)
    {
        error("Got success, but this should fail.");
        $oUser->delete();
        return FALSE;
    }

    $oMaintainer->delete();
    $oUser->delete();

    return TRUE;
}

function test_maintainer_getMaintainersForAppIdVersionId()
{
    test_start(__FUNCTION__);

    $sTestEmail = __FUNCTION__."@localhost.com";
    $sTestPassword = "password";
    if(!($oUser = create_and_login_user($sTestEmail, $sTestPassword)))
    {
        error("Failed to create and log in user!");
        return FALSE;
    }

    $oUser->addPriv("admin");

    // assign the user with admin permissions as the current user
    $_SESSION['current'] = $oUser;

    $oSecondUser = new user();
    $oSecondUser->iUserId = $oUser->iUserId + 1;
    $oSecondUser->addPriv("admin");

    $oApp = new application();
    $oApp->create();
    $oFirstVersion = new version();
    $oFirstVersion->sName = __FUNCTION__." first version";
    $oFirstVersion->iAppId = $oApp->iAppId; // $oApp is the parent
    $oFirstVersion->create();
    $oSecondVersion = new version();
    $oSecondVersion->sName = __FUNCTION__." first version";
    $oSecondVersion->iAppId = $oApp->iAppId; // $oApp is the parent
    $oSecondVersion->create();

    $oSuperMaintainer = new maintainer();
    $oSuperMaintainer->bSuperMaintainer = TRUE;
    $oSuperMaintainer->sMaintainReason = "Because";
    $oSuperMaintainer->iAppId = $oApp->iAppId;
    $oSuperMaintainer->iUserId = $oUser->iUserId;
    $oSuperMaintainer->create();

    if(!$hResult = maintainer::getMaintainersForAppIdVersionId($oApp->iAppId))
    {
        error("Failed to get list of maintainers!");
        $oUser->delete(); // delete the user
        $oApp->purge(); // cleanup the application and its versions we created
        return FALSE;
    }

    /* The application should have one maintainer */
    $iExpected = 1;
    $iReceived = query_num_rows($hResult);
    if($iExpected != $iReceived)
    {
        error("Got super maintainer count of $iReceived instead of $iExpected!");
        $oUser->delete(); // delete the user
        $oApp->purge(); // cleanup the application and its versions we created
        return FALSE;
    }

    if(!$hResult = maintainer::getMaintainersForAppIdVersionId(null,
       $oFirstVersion->iVersionId))
    {
        error("Failed to get list of maintainers!");
        $oUser->delete(); // delete the user
        $oApp->purge(); // cleanup the application and its versions we created
        return FALSE;
    }

    /* The version should have one maintainer */
    $iExpected = 1;
    $iReceived = query_num_rows($hResult);
    if($iExpected != $iReceived)
    {
        error("Got maintainer count of $iReceived instead of $iExpected!");
        $oUser->delete(); // delete the user
        return FALSE;
    }

    $oSuperMaintainer->delete(); // cleanup the super maintainer we created

    /* Become a maintainer for one of the versions only */
    $oFirstVersionMaintainer = new maintainer();
    $oFirstVersionMaintainer->sMaintainReason = "I need it";
    $oFirstVersionMaintainer->iVersionId = $oFirstVersion->iVersionId;
    $oFirstVersionMaintainer->iAppId = $oFirstVersion->iAppId;
    $oFirstVersionMaintainer->bSuperMaintainer = FALSE;
    $oFirstVersionMaintainer->iUserId = $oUser->iUserId;
    $oFirstVersionMaintainer->create();

    /* Become a maintainer for the other version */
    $oSecondVersionMaintainer = new maintainer();
    $oSecondVersionMaintainer->sMaintainReason = "I need it";
    $oSecondVersionMaintainer->iVersionId = $oSecondVersion->iVersionId;
    $oSecondVersionMaintainer->iAppId = $oFirstVersion->iAppId;
    $oSecondVersionMaintainer->bSuperMaintainer = FALSE;
    $oSecondVersionMaintainer->iUserId = $oSecondUser->iUserId;
    $oSecondVersionMaintainer->create();

    if(!$hResult = maintainer::getMaintainersForAppIdVersionId(null,
       $oFirstVersion->iVersionId))
    {
        error("Failed to get list of maintainers!");
        $oUser->delete(); // delete the user
        $oApp->purge(); // cleanup the application and its versions we created
        return FALSE;
    }

    /* The first version should have one maintainer */
    $iExpected = 1;
    $iReceived = query_num_rows($hResult);
    if($iExpected != $iReceived)
    {
        error("Got maintainer count of $iReceived instead of $iExpected!");
        $oUser->delete(); // delete the user
        $oApp->purge(); // cleanup the application and its versions we created
        return FALSE;
    }

    if(!$hResult = maintainer::getMaintainersForAppIdVersionId(null,
           $oSecondVersion->iVersionId))
    {
        error("Failed to get list of maintainers!");
        $oUser->delete(); // delete the user
        $oApp->purge(); // cleanup the application and its versions we created
        return FALSE;
    }

    /* The second version should have 1 maintainer */
    $iExpected = 1;
    $iReceived = query_num_rows($hResult);
    if($iExpected != $iReceived)
    {
        error("Got maintainer count of $iReceived instead of $iExpected!");
        $oUser->delete(); // clean up the user
        $oApp->purge(); // cleanup the application and its versions we created
        return FALSE;
    }

    if(!$oApp->purge())
      echo __FUNCTION__." oApp->purge() failed\n";

    $oUser->delete(); // clean up the user

    return TRUE;
}

if(!test_maintainer_getMaintainerCountForUser())
{
    echo "test_maintainer_getMaintainerCountForUser() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_maintainer_getMaintainerCountForUser() passed\n";
}


if(!test_maintainer_getAppsMaintained())
{
    echo "test_maintainer_getAppsMaintained() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_maintainer_getAppsMaintained() passed\n";
}


if(!test_maintainer_unQueue())
{
    echo "test_maintainer_unQueue() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_maintainer_unQueue() passed\n";
}

if(!test_superMaintainerOnAppSubmit())
{
    echo "test_superMaintainerOnAppSubmit() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_superMaintainerOnAppSubmit() passed\n";
}

if(!test_maintainer_deleteMaintainersForVersion())
{
    echo "test_maintainer_deleteMaintainersForVersion() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_maintainer_deleteMaintianersForVersion() passed\n";
}

if(!test_maintainer_getMaintainersForAppIdVersionId())
{
    echo "test_maintainer_getMaintainersForAppIdVersionId() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_maintainer_getMaintainersForAppIdVersionId() passed\n";
}

if(!test_maintainer_notifyMaintainersOfQueuedData())
{
  echo "test_maintainer_notifyMaintainersOfQueuedData() failed!\n";
  $bTestSuccess = false;
} else
{
  echo "test_maintainer_notifyMaintainersOfQueuedData() passed\n";
}


