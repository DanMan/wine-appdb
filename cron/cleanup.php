#!/usr/bin/php
<?php
/**************************************************/
/* this script has to be run daily by cron        */
/* it's purpose is to clean the user's table.     */
/**************************************************/

require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/mail.php");

$sEmailSubject = "[Cron maintenance] - ";

notifyAdminsOfCleanupStart();

/* check to see if there are orphaned versions in the database */
orphanVersionCheck();

// delete error logs over 12 months old
deleteOldErrorLogs();

/* remove screenshots that are missing their screenshot and thumbnail files */
removeScreenshotsWithMissingFiles();

/* check and notify maintainers about data they have pending in their queues */
/* if they don't process the data soon enough we'll strip them of their maintainer */
/* status since they aren't really maintaining the application/version */
maintainerCheck();

// remove maintainers who have not logged in in over 24 months
deleteInactiveMaintainers();

/* remove votes for versions that have been deleted */
cleanupVotes();


function notifyAdminsOfCleanupStart()
{
    global $sEmailSubject;

    $sSubject  = $sEmailSubject."Cleanup script starting\r\n";
    $sMsg  = "Appdb cleanup cron script started.\r\n";
    $sEmail = user::getAdminEmails(); /* get list admins */
    if($sEmail)
        mail_appdb($sEmail, $sSubject, $sMsg);
}

/* check for and report orphaned versions in the database */
/* we don't report anything if no orphans are found */
function orphanVersionCheck()
{
    global $sEmailSubject;

    $sQuery = "select versionId, versionName from appVersion where appId = 0 and state != 'deleted'";
    $hResult = query_appdb($sQuery);
}

function deleteOldErrorLogs()
{
    $sQuery = "DELETE FROM error_log WHERE submitTime <= DATE_SUB(CURDATE(), INTERVAL '12' MONTH)";
    $hResult = query_parameters($sQuery);
}

// returns an array of iScreenshotIds of screenshots that are
// missing their files
function getMissingScreenshotArray()
{
  $aMissingScreenshotIds = array();

  // retrieve all screenshots, not queued, not rejected
  $hResult = Screenshot::objectGetEntries(false, false);

  // go through each screenshot
  while($oRow = query_fetch_object($hResult))
  {
    $iScreenshotId = $oRow->id;
    $oScreenshot = new Screenshot($iScreenshotId);

    // load the screenshot and thumbnail
    $oScreenshot->load_image(true);
    $oScreenshot->load_image(false);

    // are the screenshot and thumbnail images not loaded? if so
    // add this screenshot id to the array
    if(!$oScreenshot->oScreenshotImage->isLoaded() &&
       !$oScreenshot->oThumbnailImage->isLoaded())
    {
      // add the screenshot id to the array
      $aMissingScreenshotIds[] = $iScreenshotId;
    }
  }

  return $aMissingScreenshotIds;
}

function removeScreenshotsWithMissingFiles()
{
   $aMissingScreenshotIds = getMissingScreenshotArray();

    // log in as admin user with user id 1000
    // NOTE: this is a bit of a hack but we need admin
    //       access to delete these screenshots
    $oUser = new User();
    $oUser->iUserId = 1000;
    $_SESSION['current'] = $oUser;

    // remove the screenshots with missing files
    foreach($aMissingScreenshotIds as $iScreenshotId)
    {
        $oScreenshot = new Screenshot($iScreenshotId);
        $oScreenshot->delete(); // delete the screenshot
    }

    // log out as user
    $oUser->logout();
}

/* check and notify maintainers about data they have pending in their queues */
/* if they don't process the data soon enough we'll strip them of their maintainer */
/* status since they aren't really maintaining the application/version */
function maintainerCheck()
{
  maintainer::notifyMaintainersOfQueuedData();
}

// removes maintainers who have not logged in in over 24 months
function deleteInactiveMaintainers()
{
    $hResult = maintainer::getInactiveMaintainers(24);
    $i = 0;
    while($oRow = query_fetch_object($hResult))
    {
        $oMaintainer = new maintainer(null, $oRow);
        $oMaintainer->delete();
        $oUser = new User($oRow->userid);
        $sEmail = $oUser->sEmail;
        $sSubject  = "Maintainer status removed";
        $sMsg  = "Your maintainer status has been removed because you have not logged into the AppDB in over 24 months.";
        mail_appdb($sEmail, $sSubject, $sMsg);
        $i++;
    }     
}

/* remove votes for versions that have been deleted */
function cleanupVotes()
{
    $hResult = query_appdb("SELECT appVotes.* FROM appVotes,appVersion WHERE
                            appVotes.versionId = appVersion.versionId
                            AND appVersion.state = 'deleted'");

    if(!$hResult)
        return;

    $iDeleted = 0;
    $iFailed = 0;

    while($oRow = query_fetch_object($hResult))
    {
        $oVote = new vote(null, $oRow);
        if($oVote->delete())
            $iDeleted++;
        else
            $iFailed++;
    }
}

?>
