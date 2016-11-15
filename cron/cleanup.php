#!/usr/bin/php
<?php
/**************************************************/
/* this script has to be run once a month by cron */
/* it's purpose is to clean the user's table.     */
/**************************************************/

require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/mail.php");

$sEmailSubject = "[Cron maintenance] - ";

inactiveUserCheck();

/* check to see if there are orphaned versions in the database */
orphanVersionCheck();

/* report error log entries to admins and flush the error log after doing so */
// temporarily disabled - it apperas we have too many errors
// reportErrorLogEntries();

/* remove screenshots that are missing their screenshot and thumbnail files */
removeScreenshotsWithMissingFiles();

/* check and notify maintainers about data they have pending in their queues */
/* if they don't process the data soon enough we'll strip them of their maintainer */
/* status since they aren't really maintaining the application/version */
maintainerCheck();

/* remove votes for versions that have been deleted */
cleanupVotes();

/* Updates the rating info for all versions based on test results */
//updateRatings();

/*
 * Warn users that have been inactive for some number of months
 * If it has been some period of time since the user was warned
 *   the user is deleted if they don't have any pending appdb data
 */
function inactiveUserCheck()
{
  $usersWarned = 0;
  $usersUnwarnedWithData = 0; /* users we would normally warn but who have data */
  $usersDeleted = 0;
  $usersWithData = 0; /* users marked for deletion that have data */

  notifyAdminsOfCleanupStart();

  /* users inactive for 6 months that haven't been warned already */
  $hUsersToWarn = unwarnedAndInactiveSince(6);
  if($hUsersToWarn)
  {
    while($oRow = query_fetch_object($hUsersToWarn))
    {
      $oUser = new User($oRow->userid);

      /* if we get back true the user was warned and flagged as being warned */
      /* if we get back false we didn't warn the user and didn't flag the user as warned */
      /*  because they have data associated with their account */
      if($oUser->warnForInactivity())
      {
        $usersWarned++;
      } else
      {
        $usersUnwarnedWithData++;
      }
    }
  }

  /* warned >= 1 month ago */
  $hUsersToDelete = warnedSince(1);
  if($hUsersToDelete)
  {
    while($oRow = query_fetch_object($hUsersToDelete))
    {
      $oUser = new User($oRow->userid);
      if(!$oUser->hasDataAssociated())
      {
        $usersDeleted++;
        deleteUser($oRow->userid);
      } else
      {
        /* is the user a maintainer?  if so remove their maintainer privileges */
        if($oUser->isMaintainer())
        {
          Maintainer::deleteMaintainer($oUser);
        }

        $usersWithData++;
      }
    }
  }

  notifyAdminsOfCleanupExecution($usersWarned, $usersUnwarnedWithData, $usersDeleted, $usersWithData);
}

/* Users that are unwarned and inactive since $iMonths */
function unwarnedAndInactiveSince($iMonths)
{
    $sQuery = "SELECT userid FROM user_list WHERE DATE_SUB(CURDATE(),INTERVAL $iMonths MONTH) >= stamp AND inactivity_warned='false'";
    $hResult = query_appdb($sQuery);
    return $hResult;
}

/* users that were warned at least $iMonths ago */
function warnedSince($iMonths)
{
    $sQuery  = "SELECT userid FROM user_list WHERE DATE_SUB(CURDATE(),INTERVAL $iMonths MONTH) >= inactivity_warn_stamp ";
    $sQuery .= "AND inactivity_warned='true'";
    $hResult = query_appdb($sQuery);
    return $hResult;
}

function deleteUser($iUserId)
{
    $oUser = new User($iUserId);
    warnUserDeleted($oUser->sEmail);
    $oUser->delete();
    echo "user ".$oUser->sEmail." deleted.\n";
}

function warnUserDeleted($sEmail)
{
    $sSubject  = "Warning: account removed";
    $sMsg  = "You didn't log in in the past seven months to the AppDB.\r\n";
    $sMsg .= "As you don't have any data associated to your account we have removed it.\r\n";
    $sMsg .= "Please feel free to recreate an account anytime.\r\n";

    mail_appdb($sEmail, $sSubject, $sMsg);
}

function notifyAdminsOfCleanupStart()
{
    global $sEmailSubject;

    $sSubject  = $sEmailSubject."Cleanup script starting\r\n";
    $sMsg  = "Appdb cleanup cron script started.\r\n";
    $sEmail = User::get_notify_email_address_list(null, null); /* get list admins */
    if($sEmail)
        mail_appdb($sEmail, $sSubject, $sMsg);
}

/* email all admins that the appdb cleanup script is executing */
/* so we admins have some visibility into the background cleanup */
/* events of the appdb */
function notifyAdminsOfCleanupExecution($usersWarned, $usersUnwarnedWithData, $usersDeleted, $usersWithData)
{
    global $sEmailSubject;

    $warnedUsers = User::get_inactive_users_pending_deletion();

    $sSubject  = $sEmailSubject."Inactive users\r\n";
    $sMsg  = "Appdb inactive users cleanup executed.\r\n";
    $sMsg .= "Status\r\n";
    $sMsg .= "--------------------------\r\n";
    $sMsg .= "Users warned:".$usersWarned."\r\n";
    $sMsg .= "Users we would warn, but don't because they have data associated:".$usersUnwarnedWithData."\r\n";
    $sMsg .= "Warned users pending deletion:".$warnedUsers."\r\n";
    $sMsg .= "Users deleted:".$usersDeleted."\r\n";
    $sMsg .= "Users pending deletion but have appdb data:".$usersWithData."\r\n";
    $sEmail = User::get_notify_email_address_list(null, null); /* get list admins */
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
    $found_orphans = false;

    $sMsg = "Found these orphaned versions in the database with\r\n";
    $sMsg.= "this sql command '".$sQuery."'\r\n";

    /* don't report anything if no orphans are found */
    if(query_num_rows($hResult) == 0)
        return;

    $sMsg .= "versionId/name\r\n";
    while($oRow = query_fetch_object($hResult))
    {
        $sMsg .= $oRow->versionId."/".$oRow->versionName."\r\n";
    }

    $sSubject = $sEmailSubject."Orphan version cleanup\r\n";

    $sEmail = User::get_notify_email_address_list(null, null); /* get list admins */
    if($sEmail)
        mail_appdb($sEmail, $sSubject, $sMsg);
}

// report the database error log entries to the mailing list
function reportErrorLogEntries()
{
    global $sEmailSubject;
    error_log::mail_admins_error_log($sEmailSubject);
    error_log::flush();
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
    global $sEmailSubject;

    $aMissingScreenshotIds = getMissingScreenshotArray();

    if(sizeof($aMissingScreenshotIds))
    {
        $sPlural = (sizeof($aMissingScreenshotIds) == 1) ? "" : "s";
        // build the email to admins about what we are doing
        $sMsg = "Found ".count($aMissingScreenshotIds)." screenshot$sPlural with missing files.\r\n";

        if($sPlural)
            $sMsg.= "Deleting these screenshots.\r\n";
        else
            $sMsgm.= "Deleting it.\r\n";

        // add the screenshot ids to the email so we can see which screenshots are
        // going to be deleted
        $sMsg.="\r\n";
        $sMsg.="Screenshot ID$sPlural:\r\n";
        foreach($aMissingScreenshotIds as $iScreenshotId)
        {
            $sMsg.=$iScreenshotId."\r\n";
        }
    } else
    {
        $sMsg = "No screenshot entries with missing files were found.\r\n";
    }

    $sSubject = $sEmailSubject."Missing screenshot cleanup\r\n";

    $sEmail = User::get_notify_email_address_list(null, null); /* get list admins */
    if($sEmail)
        mail_appdb($sEmail, $sSubject, $sMsg);

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

    $sEmails = user::get_notify_email_address_list(null, null); // only admins

    if($sEmails)
    {
        global $sEmailSubject;
        $sSubject = $sEmailSubject . 'Vote Cleanup';
        $sPlural = ($iDeleted == 1) ? '' : 's';
        $sMsg = "Removed $iDeleted vote$sPlural cast for deleted versions\n";
        if($iFailed)
        {
            $sPlural = ($iFailed == 1) ? '' : 's';
            $sMsg .= "WARNING: Failed to delete $iFailed vote$sPlural\n";
        }
        mail_appdb($sEmails, $sSubject, $sMsg);
    }
}

function updateRatings()
{
    $hResult = query_parameters("SELECT * FROM appVersion");

    if(!$hResult)
        return;

    while($oRow = query_fetch_object($hResult))
    {
        $oVersion = new version(0, $oRow);
        $oVersion->updateRatingInfo();
    }
}

?>
