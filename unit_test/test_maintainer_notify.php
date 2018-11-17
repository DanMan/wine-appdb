<?php

class notifyContainer
{
  var $oUser;
  var $oTestData;
  var $oMaintainer;

  var $iVersionId; // the version created by create_version_and_parent_app()

  function __construct($bTestAsMaintainer)
  {
    $this->__internal_setupQueuedDataForUser($bTestAsMaintainer);
  }

  function cleanup()
  {
    $this->oTestData->purge();   // deleted the created testData
    $this->oMaintainer->delete(); // delete the created maintainer entry
                                  // (Should be deleted when the user is deleted)

    // delete the version and its parent application that we created
    delete_version_and_parent_app($this->iVersionId);

    // delete the user we created
    $this->oUser->delete();
  }

  //FIXME: this should be private when we get php5
  function __internal_setupQueuedDataForUser($bTestAsMaintainer)
  {
    $bDebugOutputEnabled = false;

    $this->oUser = new User();
    $sUserEmail = "notifyMaintainerUser@somesite.com";
    $sUserPassword = "password";

    // if we can login as the user, delete the user
    if($this->oUser->login($sUserEmail, $sUserPassword) == SUCCESS)
    {
      // delete the user and create the new user object
      $this->oUser->delete();
      $this->oUser = new User();
    }

    // create the user
    $iRetval = $this->oUser->create("notifyMaintainerUser@somesite.com",
                                    "password", "testuser1", "0.1.0");
    if($iRetval != SUCCESS)
    {
      echo "User creation failed, returned $iRetval\n";
    }

    // flag the testing user as the current user
    $_SESSION['current'] = $this->oUser;

    // create a fake version
    $this->iVersionId = create_version_and_parent_app("test_maintainer_notify");

    // create a queued entry for the user we just created
    $this->oTestData = new testData();
    $this->oTestData->iVersionId = $this->iVersionId;
    $this->oTestData->shWhatWorks = "nothing";
    $this->oTestData->shWhatDoesnt = "nothing";
    $this->oTestData->shWhatNotTested = "nothing";
    $this->oTestData->sTestedDate = "20070404";
    $this->oTestData->sInstalls = "Yes";
    $this->oTestData->sRuns = "Yes";
    $this->oTestData->sTestedRating = "Gold";
    $this->oTestData->sComments = "";
    if(!$this->oTestData->create())
    {
      echo "Failed to create testData\n";
    }

    // make this testData queued, we need to manually override this
    // because the user created the application and version and 
    // because of this the user is allowed to submit unqueued test data
    // for an unqueued version that they created
    $this->oTestData->ReQueue();

    // refresh the testdata object now that we've modified its queued status
    $this->oTestData = new testData($this->oTestData->iTestingId);
    
    if($bDebugOutputEnabled)
    {
      echo "testData:\n";
      print_r($this->oTestData);
    }

    // NOTE: we don't unqueue $oTestData because maintainer notification
    // only applies to queued testData


    $this->oVersion = new Version($this->iVersionId);

    // make the user a maintainer of the fake version or application
    // depending on whether this user is a maintainer or super maintainer
    $this->oMaintainer = new maintainer();
    $this->oMaintainer->iUserId = $this->oUser->iUserId;
    $this->oMaintainer->iAppId = $this->oVersion->iAppId;
    $this->oMaintainer->sMaintainReason = "for testing";
    if($bTestAsMaintainer) // create a version maintainer entry
    {
      $this->oMaintainer->bSuperMaintainer = false;
      $this->oMaintainer->iVersionId = $this->iVersionId;
    } else // create an application maintainer entry, a super maintainer entry
    {
      $this->oMaintainer->bSuperMaintainer = true;
      $this->oMaintainer->iVersionId = 0;
    }

    if(!$this->oMaintainer->create())
    {
      echo "Failed to create maintainer!\n";
    }

    // notification code checks versions of a super maintainers application
    // we must unqueue the version. if the version is queued
    // we will only process this queued version during notification
    // and will skip over queued entries that depend on this version
    if(!$bTestAsMaintainer)
    {
      $sVersionUnqueue = "update appVersion set state='?' where versionId = '?'";
      $hResult = query_parameters($sVersionUnqueue, 'accepted', $this->iVersionId);
    }

    // debug printing
    if($bDebugOutputEnabled)
    {
      $sQuery2 = "select * from appMaintainers where maintainerId = '?'";
      $hResult = query_parameters($sQuery2, $this->oMaintainer->iMaintainerId);
      $oObject = query_fetch_object($hResult);
      print_r($oObject);
    }

    // adjust the maintainer submitTime because the oldest entry is
    // truncated at the time the user became a maintainer to prevent punishing
    // them for signing up for an app that has had queued data for a long time
    $iAdjustedTime = strtotime("now") - (100 * 24 * 60 * 60); // 100 days ago
    $sQuery = "update appMaintainers set submitTime = '?' where maintainerId = '?'";
    $sDate = date('Y-m-d H:i:s', $iAdjustedTime);
    query_parameters($sQuery, $sDate, $this->oMaintainer->iMaintainerId);

    if($bDebugOutputEnabled)
    {
      echo "iAdjustedTime for maintainer ".$iAdjustedTime."\n";
      echo "iMaintainerId: ".$this->oMaintainer->iMaintainerId."\n";
    }

    // update our maintainer object to get the new submit time
    $this->oMaintainer = new Maintainer($this->oMaintainer->iMaintainerId);

    //    echo "new maintainer class:\n";
    //    print_r($this->oMaintainer);
  }

  // adjust the submission time of the $oTestData object
  // to make it appear as if it is $iAgeInSeconds old
  function adjustTestDataSubmitTime($iAgeInSeconds)
  {
    // now adjust the submission time of this entry
    // so it is older than iNotificationIntervalDays
    //    echo "strtotime('now') is ".strtotime("now")."\n";
    //    echo "iAgeInSeconds is ".$iAgeInSeconds."\n";
    $iAdjustedTime = strtotime("now") - $iAgeInSeconds;
    $sDate = date('Y-m-d H:i:s', $iAdjustedTime);
    $sQuery = "update testResults set submitTime = '?' where testingId = '?'";
    query_parameters($sQuery, $sDate, $this->oTestData->iTestingId);

    // debug printing
    //    echo "iAdjustedTime is ".$iAdjustedTime."\n";
    //    echo "Original date is: ".date('Y-m-d H:i:s', strtotime("now"))."\n";
    //    echo "New date is ".$sDate."\n";
  }

  // returns true if the level is correct, false if not
  function checkLevel($iExpectedLevel)
  {
    $bSuccess = true;

    // create a new maintainer so we can check its level, we COULD use
    // the level of the existing $oMaintainer object but we want to
    // check both to make sure that the $oMaintainer was updated
    // AND that the database was updated
    $oMaintainer2 = new maintainer($this->oMaintainer->iMaintainerId);

    //    print_r($this->oMaintainer);

    if($this->oMaintainer->iNotificationLevel != $iExpectedLevel)
    {
      echo "oMaintainer expected iNotificationLevel of ".$iExpectedLevel.
        ", actual level is ".$this->oMaintainer->iNotificationLevel."\n";
      $bSuccess = false;
    }

    //    echo "maintainer2\n";
    //    print_r($oMaintainer2);

    if($oMaintainer2->iNotificationLevel != $iExpectedLevel)
    {
      echo "oMaintainer2 expected iNotificationLevel of ".$iExpectedLevel.
        ", actual level is ".$oMaintainer2->iNotificationLevel."\n";
      $bSuccess = false;
    }
    
    return $bSuccess;
  }
}



// test a user going from nothing(level 0) to level 1
function test_maintainer_notifyLevel_0_to_1($bTestAsMaintainer)
{
  echo __FUNCTION__."\n";

  $bSuccess = true; // default to success

  $oNotifyContainer = new notifyContainer($bTestAsMaintainer);

  // adjust the test data submit time back a little bit
  // to be in the level 1 range
  $iSecondsOlder = ((iNotificationIntervalDays + 1) * 24 * 60 * 60);
  $oNotifyContainer->adjustTestDataSubmitTime($iSecondsOlder);
 
  // run the notification code to update the users level
  $oNotifyContainer->oMaintainer->notifyMaintainerOfQueuedData();

  $bSuccess = $oNotifyContainer->checkLevel(1);
  if(!$bSuccess)
    echo "checkLevel() failed\n";

  // cleanup the things we created during the notification test
  $oNotifyContainer->cleanup();

  return $bSuccess;
}

// test a user going from level 1 to level 2, so the queued time is
// >= (iNotificationIntervalDays * 2)
function test_maintainer_notifyLevel_1_to_2($bTestAsMaintainer)
{
  echo __FUNCTION__."\n";

  $bSuccess = true; // default to success

  $oNotifyContainer = new notifyContainer($bTestAsMaintainer);

  // adjust the test data submit time back two intervals + 1 day
  // this should be in the level 2 range
  $iSecondsOlder = (((iNotificationIntervalDays * 2) + 1) * 24 * 60 * 60);
  $oNotifyContainer->adjustTestDataSubmitTime($iSecondsOlder);

  // update the maintainers level to be level 1 to begin with and have them be
  // notified iNotificationIntervalDays + 1 ago because we don't check the user
  // again unless it has been more thatn iNotificationIntervalDays since
  // the last check
  $iAdjustedTime = strtotime("now") - ((iNotificationIntervalDays + 1) * 24 * 60 * 60);
  $sDate = date('Y-m-d H:i:s', $iAdjustedTime);
  $sQuery = "update appMaintainers set notificationLevel = '?', notificationTime = '?' ".
            "where maintainerId = '?'";
  $iStartingLevel = 1;
  query_parameters($sQuery, $iStartingLevel, $sDate,
                   $oNotifyContainer->oMaintainer->iMaintainerId);
  
  // update the maintainer class so our manual changes take effect
  $oNotifyContainer->oMaintainer = new Maintainer($oNotifyContainer->oMaintainer->iMaintainerId);

  // run the notification code to update the users level
  $oNotifyContainer->oMaintainer->notifyMaintainerOfQueuedData();

  $bSuccess = $oNotifyContainer->checkLevel(2);
  if(!$bSuccess)
    echo "checkLevel() failed\n";

  // cleanup the things we created during the notification test
  $oNotifyContainer->cleanup();

  return $bSuccess;
}

// test user going from level 2 to 3, where the user will be deleted
function test_maintainer_notifyLevel_2_to_3($bTestAsMaintainer)
{
  echo __FUNCTION__."\n";

  $bSuccess = true; // default to success

  $oNotifyContainer = new notifyContainer($bTestAsMaintainer);

  // adjust the test data submit time back three intervals + 1 day
  // this should be in the level 3 range
  $iSecondsOlder = (((iNotificationIntervalDays * 3) + 1) * 24 * 60 * 60);
  $oNotifyContainer->adjustTestDataSubmitTime($iSecondsOlder);

  // update the maintainers level to be level 2 to begin with and have them be
  // notified iNotificationIntervalDays + 1 ago
  // update the maintainers level to be level 1 to begin with and have them be
  // notified iNotificationIntervalDays + 1 ago because we don't check the user
  // again unless it has been more thatn iNotificationIntervalDays since
  // the last check
  $iAdjustedTime = strtotime("now") - ((iNotificationIntervalDays + 1) * 24 * 60 * 60);
  $sDate = date('Y-m-d H:i:s', $iAdjustedTime);
  $sQuery = "update appMaintainers set notificationLevel = '?', ".
            "notificationTime = '?' where maintainerId = '?'";
  $iStartingLevel = 2;
  query_parameters($sQuery, $iStartingLevel, $sDate,
                   $oNotifyContainer->oMaintainer->iMaintainerId);
  
  // update the maintainer class so our manual changes take effect
  $oNotifyContainer->oMaintainer = new Maintainer($oNotifyContainer->oMaintainer->iMaintainerId);

  // run the notification code to update the users level
  $oNotifyContainer->oMaintainer->notifyMaintainerOfQueuedData();

  // check to make sure the maintainer doesn't exist
  $sQuery = "select count(*) as cnt from appMaintainers where maintainerId = '?'";
  $hResult = query_parameters($sQuery, $oNotifyContainer->oMaintainer->iMaintainerId);
  $oRow = query_fetch_object($hResult);
  if($oRow->cnt != 0)
  {
    $bSuccess = false;
  }

  // cleanup the things we created during the notification test
  $oNotifyContainer->cleanup();

  return $bSuccess;
}
  // test user going from level 2 to level 1
function test_maintainer_notifyLevel_2_to_1($bTestAsMaintainer)
{
  echo __FUNCTION__."\n";

  $bSuccess = true; // default to success

  $oNotifyContainer = new notifyContainer($bTestAsMaintainer);

  // adjust the test data submit time back one interval + 1 day
  // this should be in the level 1 range
  $iSecondsOlder = ((iNotificationIntervalDays + 1) * 24 * 60 * 60);
  $oNotifyContainer->adjustTestDataSubmitTime($iSecondsOlder);

  // update the maintainers level to be level 2 to begin with and have them be
  // notified iNotificationIntervalDays + 1 ago
  // update the maintainers level to be level 1 to begin with and have them be
  // notified iNotificationIntervalDays + 1 ago because we don't check the user
  // again unless it has been more thatn iNotificationIntervalDays since
  // the last check
  $iAdjustedTime = strtotime("now") - ((iNotificationIntervalDays + 1) * 24 * 60 * 60);
  $sDate = date('Y-m-d H:i:s', $iAdjustedTime);
  $sQuery = "update appMaintainers set notificationLevel = '?', notificationTime = '?' ".
            "where maintainerId = '?'";
  $iStartingLevel = 2;
  query_parameters($sQuery, $iStartingLevel, $sDate,
                   $oNotifyContainer->oMaintainer->iMaintainerId);
  
  // update the maintainer class so our manual changes take effect
  $oNotifyContainer->oMaintainer = new Maintainer($oNotifyContainer->oMaintainer->iMaintainerId);

  // run the notification code to update the users level
  $oNotifyContainer->oMaintainer->notifyMaintainerOfQueuedData();

  $bSuccess = $oNotifyContainer->checkLevel(1);
  if(!$bSuccess)
    echo "checkLevel() failed\n";

  // cleanup the things we created during the notification test
  $oNotifyContainer->cleanup();

  return $bSuccess;
}

// test user going from level 1 to level 0
function test_maintainer_notifyLevel_1_to_0($bTestAsMaintainer)
{
  echo __FUNCTION__."\n";

  $bSuccess = true; // default to success

  $oNotifyContainer = new notifyContainer($bTestAsMaintainer);

  // this is only a few seconds old
  $iSecondsOlder = 60;
  $oNotifyContainer->adjustTestDataSubmitTime($iSecondsOlder);

  // update the maintainers level to be level 1 to begin with and have them be
  // notified iNotificationIntervalDays + 1 ago
  // update the maintainers level to be level 1 to begin with and have them be
  // notified iNotificationIntervalDays + 1 ago because we don't check the user
  // again unless it has been more thatn iNotificationIntervalDays since
  // the last check
  $iAdjustedTime = strtotime("now") - ((iNotificationIntervalDays + 1) * 24 * 60 * 60);
  $sDate = date('Y-m-d H:i:s', $iAdjustedTime);
  $sQuery = "update appMaintainers set notificationLevel = '?', notificationTime = '?' ".
            "where maintainerId = '?'";
  $iStartingLevel = 1;
  query_parameters($sQuery, $iStartingLevel, $sDate,
                   $oNotifyContainer->oMaintainer->iMaintainerId);
  
  // update the maintainer class so our manual changes take effect
  $oNotifyContainer->oMaintainer = new Maintainer($oNotifyContainer->oMaintainer->iMaintainerId);

  // run the notification code to update the users level
  $oNotifyContainer->oMaintainer->notifyMaintainerOfQueuedData();

  $bSuccess = $oNotifyContainer->checkLevel(0);
  if(!$bSuccess)
    echo "checkLevel() failed\n";

  // cleanup the things we created during the notification test
  $oNotifyContainer->cleanup();

  return $bSuccess;
}

// test user going from level 2 to level 0
function test_maintainer_notifyLevel_2_to_0($bTestAsMaintainer)
{
  echo __FUNCTION__."\n";

  $bSuccess = true; // default to success

  $oNotifyContainer = new notifyContainer($bTestAsMaintainer);

  // this is only a few seconds old
  $iSecondsOlder = 60;
  $oNotifyContainer->adjustTestDataSubmitTime($iSecondsOlder);

  // update the maintainers level to be level 1 to begin with and have them be
  // notified iNotificationIntervalDays + 1 ago
  // update the maintainers level to be level 1 to begin with and have them be
  // notified iNotificationIntervalDays + 1 ago because we don't check the user
  // again unless it has been more thatn iNotificationIntervalDays since
  // the last check
  $iAdjustedTime = strtotime("now") - ((iNotificationIntervalDays + 1) * 24 * 60 * 60);
  $sDate = date('Y-m-d H:i:s', $iAdjustedTime);
  $sQuery = "update appMaintainers set notificationLevel = '?', notificationTime = '?' ".
            "where maintainerId = '?'";
  $iStartingLevel = 2;
  query_parameters($sQuery, $iStartingLevel, $sDate,
                   $oNotifyContainer->oMaintainer->iMaintainerId);
  
  // update the maintainer class so our manual changes take effect
  $oNotifyContainer->oMaintainer = new Maintainer($oNotifyContainer->oMaintainer->iMaintainerId);

  // run the notification code to update the users level
  $oNotifyContainer->oMaintainer->notifyMaintainerOfQueuedData();

  $bSuccess = $oNotifyContainer->checkLevel(0);
  if(!$bSuccess)
    echo "checkLevel() failed\n";

  // cleanup the things we created during the notification test
  $oNotifyContainer->cleanup();

  return $bSuccess;
}

// test that a user that should go from level 1 to level 2 will not do so
// because the last update time is too soon
function test_maintainer_notifyLevel_1_to_2_delay($bTestAsMaintainer)
{
  echo __FUNCTION__."\n";

  $bSuccess = true; // default to success

  $oNotifyContainer = new notifyContainer($bTestAsMaintainer);

  // adjust the test data submit time back two intervals + 1 day
  // this should be in the level 2 range
  $iSecondsOlder = (((iNotificationIntervalDays * 2) + 1) * 24 * 60 * 60);
  $oNotifyContainer->adjustTestDataSubmitTime($iSecondsOlder);

  // set the notification time as if we just notified this particular
  // maintainer a few seconds ago
  $iAdjustedTime = strtotime("now") - 60;
  $sDate = date('Y-m-d H:i:s', $iAdjustedTime);
  $sQuery = "update appMaintainers set notificationLevel = '?', notificationTime = '?' ".
            "where maintainerId = '?'";
  $iStartingLevel = 1;
  query_parameters($sQuery, $iStartingLevel, $sDate,
                   $oNotifyContainer->oMaintainer->iMaintainerId);
  
  // update the maintainer class so our manual changes take effect
  $oNotifyContainer->oMaintainer = new Maintainer($oNotifyContainer->oMaintainer->iMaintainerId);

  // run the notification code to update the users level
  $oNotifyContainer->oMaintainer->notifyMaintainerOfQueuedData();

  $bSuccess = $oNotifyContainer->checkLevel(1);
  if(!$bSuccess)
    echo "checkLevel() failed\n";

  // cleanup the things we created during the notification test
  $oNotifyContainer->cleanup();

  return $bSuccess;
}

// test that a user with queued items not old enough to cause a transition
//   from level 0 to 1 will remain at level 0
function test_maintainer_notifyLevel_0_to_0($bTestAsMaintainer)
{
  echo __FUNCTION__."\n";

  $bSuccess = true; // default to success

  $oNotifyContainer = new notifyContainer($bTestAsMaintainer);

  // adjust the test data submit time back two intervals + 1 day
  // this should be in the level 2 range
  $iSecondsOlder = 60;
  $oNotifyContainer->adjustTestDataSubmitTime($iSecondsOlder);

  // flag this maintainer as being notified at least a iNotificationIntervalDays
  // ago so they should certainly be notified again
  $iAdjustedTime = strtotime("now") - ((iNotificationIntervalDays + 1) * 24 * 60 * 60);
  $sDate = date('Y-m-d H:i:s', $iAdjustedTime);
  $sQuery = "update appMaintainers set notificationLevel = '?', notificationTime = '?' ".
            "where maintainerId = '?'";
  $iStartingLevel = 1;
  query_parameters($sQuery, $iStartingLevel, $sDate,
                   $oNotifyContainer->oMaintainer->iMaintainerId);
  
  // update the maintainer class so our manual changes take effect
  $oNotifyContainer->oMaintainer = new Maintainer($oNotifyContainer->oMaintainer->iMaintainerId);

  // run the notification code to update the users level
  $oNotifyContainer->oMaintainer->notifyMaintainerOfQueuedData();

  $bSuccess = $oNotifyContainer->checkLevel(0);
  if(!$bSuccess)
    echo "checkLevel() failed\n";

  // cleanup the things we created during the notification test
  $oNotifyContainer->cleanup();

  return $bSuccess;
}

// test that a user with queued data that is really really old will
// only go from level 0 to level 1
function test_maintainer_notifyLevel_0_to_1_with_old_queued_data($bTestAsMaintainer)
{
  echo __FUNCTION__."\n";

  $bSuccess = true; // default to success

  $oNotifyContainer = new notifyContainer($bTestAsMaintainer);

  // adjust the test data submit time back a little bit
  // to be in the level 1 range
  $iSecondsOlder = (((iNotificationIntervalDays * 5) + 1) * 24 * 60 * 60);
  $oNotifyContainer->adjustTestDataSubmitTime($iSecondsOlder);
 
  // run the notification code to update the users level
  $oNotifyContainer->oMaintainer->notifyMaintainerOfQueuedData();

  $bSuccess = $oNotifyContainer->checkLevel(1);
  if(!$bSuccess)
    echo "checkLevel() failed\n";

  // cleanup the things we created during the notification test
  $oNotifyContainer->cleanup();

  return $bSuccess;
}

function test_maintainer_notifyLevel_starts_at_zero($bTestAsMaintainer)
{
  echo __FUNCTION__."\n";

  $bSuccess = true; // default to success

  $oNotifyContainer = new notifyContainer($bTestAsMaintainer);

  // see if our notification level is zero
  if($oNotifyContainer->oMaintainer->iNotificationLevel != 0)
  {
    $bSuccess = false;
  }

  $oNotifyContainer->cleanup();

  return $bSuccess;
}

// test maintainer::notifyMaintainerOfQueuedData() in various
// potential situations
// $bTestAsMaintainer == true - the user being tested is a normal maintainer
// $bTestAsMaintainer == false - the user being tested is a super maintainer
function _test_maintainer_notifyMaintainersOfQueuedData($bTestAsMaintainer)
{
  $bSuccess = true;  // default to success

  test_start(__FUNCTION__);

  $sFunction = "test_maintainer_notifyLevel_0_to_1";
  if(!$sFunction($bTestAsMaintainer))
  {
    echo $sFunction." failed!\n";
    $bSuccess = false;
  }

  $sFunction = "test_maintainer_notifyLevel_1_to_2"; 
  if(!$sFunction($bTestAsMaintainer))
  {
    echo $sFunction." failed!\n";
    $bSuccess = false;
  }

  $sFunction = "test_maintainer_notifyLevel_2_to_3";
  if(!$sFunction($bTestAsMaintainer))
  {
    echo $sFunction." failed!\n";
    $bSuccess = false;
  }

  $sFunction = "test_maintainer_notifyLevel_2_to_1";
  if(!$sFunction($bTestAsMaintainer))
  {
    echo $sFunction." failed!\n";
    $bSuccess = false;
  }

  $sFunction = "test_maintainer_notifyLevel_1_to_0";
  if(!$sFunction($bTestAsMaintainer))
  {
    echo $sFunction." failed!\n";
    $bSuccess = false;
  }

  $sFunction = "test_maintainer_notifyLevel_2_to_0";
  if(!$sFunction($bTestAsMaintainer))
  {
    echo $sFunction." failed!\n";
    $bSuccess = false;
  }

  $sFunction = "test_maintainer_notifyLevel_1_to_2_delay";
  if(!$sFunction($bTestAsMaintainer))
  {
    echo $sFunction." failed!\n";
    $bSuccess = false;
  }

  $sFunction = "test_maintainer_notifyLevel_0_to_0";
  if(!$sFunction($bTestAsMaintainer))
  {
    echo $sFunction." failed!\n";
    $bSuccess = false;
  }

  $sFunction = "test_maintainer_notifyLevel_0_to_1_with_old_queued_data";
  if(!$sFunction($bTestAsMaintainer))
  {
    echo $sFunction." failed!\n";
    $bSuccess = false;
  }

  $sFunction = "test_maintainer_notifyLevel_starts_at_zero";
  if(!$sFunction($bTestAsMaintainer))
  {
    echo $sFunction." failed!\n";
    $bSuccess = false;
  }

  return $bSuccess;
}


// test maintainer::notifyMaintainerOfQueuedData() in various
// potential situations
// NOTE: we test one as a normal maintainer and once as a super maintainer
function test_maintainer_notifyMaintainersOfQueuedData()
{
  $bSuccess = true;

  test_start(__FUNCTION__);

  echo "\nTesting as maintainer\n";
  if(!_test_maintainer_notifyMaintainersOfQueuedData(true))
  {
    echo "Maintainer test failed!\n";
    $bSuccess = false;
  }

  echo "\n\nTesting as super maintainer\n";
  if(!_test_maintainer_notifyMaintainersOfQueuedData(false))
  {
    echo "Super maintainer test failed!\n";
    $bSuccess = false;
  }

  return $bSuccess;
}

