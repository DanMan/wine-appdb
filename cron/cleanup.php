#!/usr/bin/php
<?php
##################################################
# this script has to be run once a month by cron #
# it's purpose is to clean the user's table.     #
##################################################

include("path.php");
include(BASE."include/incl.php");
include(BASE."include/mail.php");

/*
 * Warn users that have been inactive for some number of months
 * If it has been some period of time since the user was warned
 *   the user is deleted if they don't have any pending appdb data
 */

$usersWarned = 0;
$usersDeleted = 0;
$usersWithData = 0; /* users marked for deletion that have data */

notifyAdminsOfCleanupStart();

/* users inactive for 6 months that haven't been warned already */
$hUsersToWarn = unwarnedAndInactiveSince(6);
if($hUsersToWarn)
{
    while($oRow = mysql_fetch_object($hUsersToWarn))
    {
        $usersWarned++;
        $oUser = new User($oRow->userid);
        $oUser->warnForInactivity();
    }
}

/* warned >= 1 month ago */
$hUsersToDelete = warnedSince(1);
if($hUsersToDelete)
{
    while($oRow = mysql_fetch_object($hUsersToDelete))
    {
        $oUser = new User($oRow->userid);
        if(!$oUser->hasDataAssociated())
        {
            $usersDeleted++;
            deleteUser($oRow->userid);
        } else
        {
            /* is the user a maintainer?  if so remove their maintainer privilages */
            if($oUser->isMaintainer())
            {
                $oUser->deleteMaintainer();
            }

            $usersWithData++;
        }
    }
}

notifyAdminsOfCleanupExecution($usersWarned, $usersDeleted, $usersWithData);


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
    $sMsg  = "You didn't log in in the past seven month to the AppDB.\r\n";
    $sMsg .= "As you don't have any data associated to your account we have removed it.\r\n";
    $sMsg .= "Please feel free to recreate an account anytime.\r\n";

    mail_appdb($sEmail, $sSubject, $sMsg);
}

function notifyAdminsOfCleanupStart()
{
    $sSubject  = "Cleanup script starting\r\n";
    $sMsg  = "Appdb cleanup cron script started.\r\n";
    $sEmail = get_notify_email_address_list(null, null); /* get list admins */
    if($sEmail)
        mail_appdb($sEmail, $sSubject, $sMsg);
}

/* email all admins that the appdb cleanup script is executing */
/* so we admins have some visibility into the background cleanup */
/* events of the appdb */
function notifyAdminsOfCleanupExecution($usersWarned, $usersDeleted, $usersWithData)
{
    $sSubject  = "Cleanup script summary\r\n";
    $sMsg  = "Appdb cleanup cron script executed.\r\n";
    $sMsg .= "Status:\r\n";
    $sMsg .= "Users warned:".$usersWarned." Users deleted:".$usersDeleted."\r\n";
    $sMsg .= "Users pending deletion but have appdb data:".$usersWithData."\r\n";
    $sEmail = get_notify_email_address_list(null, null); /* get list admins */
    if($sEmail)
        mail_appdb($sEmail, $sSubject, $sMsg);
}