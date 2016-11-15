<?php

/* unit tests to make sure objects we want to use with the object manager are valid */

require_once("path.php");
require_once("test_common.php");
require_once(BASE.'include/objectManager.php');
require_once(BASE.'include/application.php');
require_once(BASE.'include/maintainer.php');
require_once(BASE.'include/testData_queue.php');
require_once(BASE.'include/version_queue.php');
require_once(BASE.'include/application_queue.php');
require_once(BASE.'include/monitor.php');
require_once(BASE.'include/bugs.php');


/* internal function */
function test_class($sClassName, $aTestMethods)
{
    $oObject = new ObjectManager($sClassName);

    /* Check whether the required methods are present */
    if(!$oObject->checkMethods($aTestMethods, false))
    {
        echo "FAILED\t\t".$oObject->getClass()." does not have valid methods for use with".
             " the object manager\n";
        return false;
    }

    /* Set up test user */
    $sTestEmail = __FUNCTION__."@localhost.com";
    $sTestPassword = "password";
    if(!$oUser = create_and_login_user($sTestEmail, $sTestPassword))
    {
        error("Failed to create and log in user.");
        return FALSE;
    } else
    {
      // assign the session variable that makes this user the current user
      $_SESSION['current'] = $oUser;
    }

    /* Test the class constructor */
    if(!$oTestObject = create_object($sClassName, $oUser))
        return FALSE;

    /* Should return 1 or more, since there may be entries present already */
    $hResult = $oTestObject->objectGetEntries('accepted');

    if(!$hResult)
    {
        error("Got '$hResult' instead of a valid query handle");
        error("FAILED\t\t$sClassName::$sClassName");
        $oTestObject->purge();
        return FALSE;
    }

    if(!($oRow = query_fetch_object($hResult)))
    {
        error("Failed to fetch query object");
        error("FAILED\t\t$sClassName::$sClassName");
        $oTestObject->purge();
        return FALSE;
    }

    $oNewTestObject = new $sClassName(null, $oRow);
    $iReceived = $oNewTestObject->objectGetId();

    if(!$iReceived || !is_numeric($iReceived))
    {
        error("Got '$iReceived' instead of a valid id");
        error("FAILED\t\t$sClassName::$sClassName()");
        $oTestObject->purge();
        return FALSE;
    }

    echo "PASSED\t\t$sClassName::$sClassName\n";

    //////////////////////////
    // cleanup after the test
    cleanup($oTestObject);

    if(!$oUser->addPriv("admin"))
        error("oUser->addPriv('admin') failed");

    if(!$oTestObject->purge())
    {
        error("sClassName of '".$sClassName."' oTestObject->purge() failed!");
        $oUser->delete();
        return false;
    }

    ////////////////////////////////

    /* Test the methods' functionality */
    foreach($aTestMethods as $sMethod)
    {
        switch($sMethod)
        {
            case 'objectGetEntries':
                if(!$oTestObject = create_object($sClassName, $oUser))
                    return FALSE;

                /* Should return 1 or more, since there may be entries present already */
                $iExpected = 1;
                $hResult = $oTestObject->objectGetEntries('accepted');
                $iReceived = query_num_rows($hResult);
                if($iExpected > $iReceived)
                {
                    error("Got $iReceived instead of >= $iExpected");
                    error("FAILED\t\t$sClassName::$sMethod");
                    cleanup_and_purge($oTestObject, $oUser);
                    return FALSE;
                }

                /* Create an object as a regular user */
                $oTestObject2 = create_object($sClassName, $oUser, false);

                /* objectGetEntries() and objectGetEntriesCount() should return matching results */
                $iExpected = query_num_rows($oTestObject2->objectGetEntries('accepted'));
                $iReceived = $oTestObject2->objectGetEntriesCount('accepted');
                if($iExpected != $iReceived)
                {
                    error("ObjectGetEntriesCount returned $iReceived, objectGetEntries fetched $iExpected rows\n");
                    error("FAILED\t\t$sClassName::$sMethod");
                    cleanup_and_purge($oTestObject, $oUser);
                    cleanup_and_purge($oTestObject2, $oUser);
                    return false;
                }

                /* Now test for queued objects, as admin */
                $oUser->addPriv('admin');
                $iExpected = query_num_rows($oTestObject2->objectGetEntries('queued'));
                $iReceived = $oTestObject2->objectGetEntriesCount('queued');
                $oUser->delPriv('admin');
                if($iExpected != $iReceived)
                {
                    error("ObjectGetEntriesCount returned $iReceived, objectGetEntries fetched $iExpected rows\n");
                    error("FAILED\t\t$sClassName::$sMethod");
                    cleanup_and_purge($oTestObject, $oUser);
                    cleanup_and_purge($oTestObject2, $oUser);
                    return false;
                }

                /* Class specific clean-up */
                cleanup_and_purge($oTestObject, $oUser);
                cleanup_and_purge($oTestObject2, $oUser);

                echo "PASSED\t\t$sClassName::$sMethod\n";
            break;
            case 'unQueue':
                $bSuccess = true;
                $oTestObject = create_object($sClassName, $oUser, false);

                $oUser->addPriv('admin');
                $oTestObject->unQueue();

                $iReceived = $oTestObject->objectGetState();
                $iExpected = 'accepted';
                if($iReceived != $iExpected)
                {
                    error("Got queue state of $iReceived instead of $iExpected\n");
                    error("FAILED\t\t$sClassName::$sMethod");
                    $bSuccess = false;
                }
                cleanup($oTestObject);
                $oTestObject->purge();
                $oUser->delPriv('admin');

                if(!$bSuccess)
                    return $bSuccess;

                echo "PASSED\t\t$sClassName::$sMethod\n";
            break;
        }
    }

    $oUser->delete();

    echo "PASSED\t\t".$oObject->getClass()."\n";
    return TRUE;
}

function cleanup($oObject)
{
    switch(get_class($oObject))
    {
        case "bug":
          // remove the bug row we created for the bug in create_object()
          $sQuery = "delete from bugs where bug_id = '?'";
          $hResult = query_bugzilladb($sQuery, $oObject->iBug_id);
        break;
        case "downloadurl":
        case "maintainer":
        case "screenshot":
        case "testData":
            delete_version_and_parent_app($oObject->iVersionId);
        break;
        case "testData_queue":
            delete_version_and_parent_app($oObject->oTestData->iVersionId);
        break;
        case "version":
            $oApp = new application($oObject->iAppId);
            $oApp->purge();
        break;
        case "version_queue":
            $oApp = new application($oObject->oVersion->iAppId);
            $oApp->purge();
        break;
    }
}

function cleanup_and_purge($oObject, $oUser)
{
    $bWasAdmin = $oUser->hasPriv('admin');

    $oUser->addPriv('admin');
    cleanup($oObject);
    $oObject->purge();

    switch(get_class($oObject))
    {
        case 'application':
            $oVendor = new vendor($oObject->iVendorId);
            $oVendor->purge();
        break;
    }

    if(!$bWasAdmin)
        $oUser->delPriv('admin');
}

function create_object($sClassName, $oUser, $bAsAdmin = true)
{
    if($bAsAdmin)
        $oUser->addPriv("admin");

    $oTestObject = new $sClassName();
    /* Set up one test entry, depending on class */
    switch($sClassName)
    {
        // Some application functions require a vendor to be set
        case 'application':
            $oVendor = new vendor();
            $oVendor->create();
            $oTestObject->iVendorId = $oVendor->objectGetId();
        break;

        case "bug":
          // create a bug in the bugzilla database, we need a valid
          // bug id to create a bug entry
          $sQuery = "insert into bugs (short_desc, bug_status, resolution)".
            " values ('?', '?', '?')";
          $hResult = query_bugzilladb($sQuery, "test_om_objects", "VERIFIED",
                                      '');

          // retrieve the bug id and assign that to our
          // bug class
          $oTestObject->iBug_id = query_bugzilla_insert_id();
        break;
        case "distribution":
            $oTestObject->sName = "Silly test distribution";
            $oTestObject->sUrl = "http://appdb.winehq.org/";
        break;
        case "downloadurl":
            $oTestObject->sUrl = "http://appdb.winehq.org/";
            $oTestObject->sDescription = "DANGER";
            $oTestObject->iVersionId = create_version_and_parent_app("create_object_downloadurl");
        break;
        case "maintainer":
            /* We create the version as admin anyway to avoid the maintainer entry getting a state of 'pending' */
            if(!$bAsAdmin)
                $oUser->addPriv('admin');

            $iVersionId = create_version_and_parent_app("create_object_maintainer");

            if(!$bAsAdmin)
                $oUser->delPriv('admin');

            $oVersion = new version($iVersionId);
            $oTestObject->iUserId = $oUser->iUserId;
            $oTestObject->iAppId = $oVersion->iAppId;
            $oTestObject->iVersionId = $oVersion->iVersionId;
            $oTestObject->sMaintainReason = "I need it";
        break;
        case "screenshot":
        case "testData":
            $oTestObject->iVersionId = create_version_and_parent_app("create_object_testData");
        break;
        case "testData_queue":
            $oTestObject->oTestData->iVersionId = create_version_and_parent_app("create_object_testData_queue");
        break;
        case "version":
            $oApp = new application();
            $oApp->create();
            $oTestObject->iAppId = $oApp->iAppId;
            $oTestObject->sName = "OM Version";
        break;
        case "version_queue":
            $oApp = new application();
            $oApp->create();
            $oTestObject->oVersion->iAppId = $oApp->iAppId;
            $oTestObject->oVersion->sName = "OM Version";
        break;
        case "vendor":
            $oTestObject->sName = "OM vendor";
        break;
    }

    /* We cannot use screenshot::create() because it requires an image */
    if($sClassName != "screenshot")
    {
        if(!$oTestObject->create())
        {
            error("FAILED\t\t$sClassName::create()");
            return FALSE;
        }
    } else
    {
        $sQuery = "INSERT INTO appData
                (versionId, type, description, state, submitterId)
                VALUES('?','?','?','?','?')";
        $hResult = query_parameters($sQuery, $oTestObject->iVersionId, 'screenshot', '', 'accepted',
                                    $oUser->iUserId);
        if(!$hResult)
        {
            error("FAILED\t\t$sClassName to create screenshot entry");
            return FALSE;
        }
        $oTestObject->iScreenshotId = query_appdb_insert_id();
    }

    if($bAsAdmin)
        $oUser->delPriv('admin');

    return $oTestObject;
}

function test_object_methods()
{
    test_start(__FUNCTION__);

    $aTestMethods = array("allowAnonymousSubmissions",
                          "canEdit",
                          "display",
                          "getOutputEditorValues",
                          "mustBeQueued",
                          "objectGetChildren",
                          "objectGetEntries",
                          'objectGetEntriesCount',
                          "objectGetHeader",
                          "objectGetId",
                          "objectGetMail",
                          "objectGetMailOptions",
                          'objectGetState',
                          "objectGetSubmitterId",
                          "objectGetTableRow",
                          "objectMakeLink",
                          "objectMakeUrl",
                          "outputEditor",
                          'purge',
                          'unQueue'
                         );

    $aTestClasses = array("application",
                          "application_queue",
                          "bug",
                          "distribution",
                          "downloadurl",
                          "maintainer",
                          "monitor",
                          "note",
                          "screenshot",
                          "testData",
                          "testData_queue",
                          "vendor",
                          "version",
                          "version_queue"
                         );

    foreach($aTestClasses as $sTestClass)
    {
        if(!test_class($sTestClass, $aTestMethods))
            return FALSE;
    }

    return true;
}

if(!test_object_methods())
{
    echo "test_object_methods() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_object_methods() passed\n";
}

?>
