#!/usr/bin/php
<?php
##################################################
# this script has to be run once a month by cron #
# it's purpose is to clean the user's table.     #
##################################################

include("path.php");
include(BASE."include/"."incl.php");

/*
 * Let:
 * 1) you did not log in for six month
 * 2) you don't have any data associated                                               
 * 3) you are a maintainer
 * 4) you receive a warning e-mail
 * 5) you did not log in for seven month
 * 6) your account is deleted
 * 7) you are not a maintainer anymore
 * 
 * The rules are the following:
 * if(1 AND 2) then 4
 * if(1 AND 3) then 4
 * if(5 AND 2) then 6
 * if(5 AND 3) then 7
 */


$hSixMonth = inactiveSince(6);
while($oRow = mysql_fetch_object($hSixMonth))
{
    if(isMaintainer($oRow->userid))
        warnMaintainer(lookupEmail($oRow->userid));
    elseif(!hasDataAssociated($oRow->userid))
        warnUser(lookupEmail($oRow->userid));    
}

$hSevenMonth = inactiveSince(7);
while($oRow = mysql_fetch_object($hSevenMonth))
{
    if(isMaintainer($oRow->userid))
        deleteMaintainer($oRow->userid);
    elseif(!hasDataAssociated($oRow->userid))
        deleteUser($oRow->userid);    
}


function inactiveSince($iMonths)
{
    $sQuery = "SELECT userid FROM user_list WHERE DATE_SUB(CURDATE(),INTERVAL $iMonths MONTH) >= stamp";
    $hResult = query_appdb($sQuery);
    return $hResult;
}

function hasDataAssociated($iUserId)
{
    $sQuery = "SELECT * FROM appComments WHERE userId = $iUserId";
    $hResult = query_appdb($sQuery);
    if(mysql_num_rows($hResult)) return true;

    $sQuery = "SELECT * FROM appDataQueue WHERE userId = $iUserId";
    $hResult = query_appdb($sQuery);
    if(mysql_num_rows($hResult)) return true;

    $sQuery = "SELECT * FROM appMaintainerQueue WHERE userId = $iUserId";
    $hResult = query_appdb($sQuery);
    if(mysql_num_rows($hResult)) return true;

    $sQuery = "SELECT * FROM appOwners WHERE ownerId = $iUserId";
    $hResult = query_appdb($sQuery);
    if(mysql_num_rows($hResult)) return true;

    $sQuery = "SELECT * FROM appVotes WHERE userId = $iUserId";
    $hResult = query_appdb($sQuery);
    if(mysql_num_rows($hResult)) return true;

    return false;
}


function deleteUser($iUserId)
{
    warnUserDeleted(lookupEmail($iUserId));
    echo "user ".lookupEmail($iUserId)." deleted.\n";
    $sQuery = "DELETE FROM user_list WHERE userid = $iUserId";
    $hResult = query_appdb($sQuery);
    $sQuery = "DELETE FROM user_prefs WHERE userid = $iUserId";
    $hResult = query_appdb($sQuery);
}

function deleteMaintainer()
{
    $sQuery = "DELETE FROM appMaintainers WHERE userId = $iUserId";
    $hResult = query_appdb($sQuery);
    warnMaintainerDeleted(lookupEmail($iUserId));
    echo "user ".lookupEmail($iUserId)." is not a maintainer anymore.\n";
}

function warnUser($sEmail)
{
    $sHeaders  = "MIME-Version: 1.0\r\n";
    $sHeaders .= "From: AppDB <appdb@winehq.org>\r\n";
    $sHeaders .= "Reply-to: AppDB <appdb@winehq.orgl>\r\n";
    $sHeaders .= "X-Priority: 3\r\n";
    $sHeaders .= "X-Mailer: ".APPDB_OWNER." mailer\r\n";

    $sMsg  = "AppDB Warning: inactivity detected\r\n";
    $sMsg .= "-----------------------------------\r\n";
    $sMsg .= "You didn't log in in the past six month to the AppDB (".APPDB_OWNER_URL.")\r\n";
    $sMsg .= "Please log in or your account will automatically be deleted in one month.\r\n\r\n";
    $sMsg .= "Best regards.\r\n";
    $sMsg .= "The AppDB team.\r\n";

    mail($sEmail, "[AppDB] Warning: inactivity detected", $sMsg, $sHeaders, "-fappdb@winehq.org");
}

function warnMaintainer($sEmail)
{
    $sHeaders  = "MIME-Version: 1.0\r\n";
    $sHeaders .= "From: AppDB <appdb@winehq.org>\r\n";
    $sHeaders .= "Reply-to: AppDB <appdb@winehq.orgl>\r\n";
    $sHeaders .= "X-Priority: 3\r\n";
    $sHeaders .= "X-Mailer: ".APPDB_OWNER." mailer\r\n";

    $sMsg  = "AppDB Warning: inactivity detected\r\n";
    $sMsg .= "-----------------------------------\r\n";
    $sMsg .= "You didn't log in in the past six month to the AppDB (".APPDB_OWNER_URL.")\r\n";
    $sMsg .= "As a maintainer we would be pleased to see you once in a while\r\n";
    $sMsg .= "Please log in or you will lose your maintainer's abilities in one month.\r\n\r\n";
    $sMsg .= "Best regards.\r\n";
    $sMsg .= "The AppDB team.\r\n";

    mail($sEmail, "[AppDB] Warning: inactivity detected", $sMsg, $sHeaders, "-fappdb@winehq.org");
}

function warnUserDeleted($sEmail)
{
    $sHeaders  = "MIME-Version: 1.0\r\n";
    $sHeaders .= "From: AppDB <appdb@winehq.org>\r\n";
    $sHeaders .= "Reply-to: AppDB <appdb@winehq.orgl>\r\n";
    $sHeaders .= "X-Priority: 3\r\n";
    $sHeaders .= "X-Mailer: ".APPDB_OWNER." mailer\r\n";

    $sMsg  = "AppDB Warning: account removed\r\n";
    $sMsg .= "-----------------------------------\r\n";
    $sMsg .= "You didn't log in in the past seven month to the AppDB (".APPDB_OWNER_URL.")\r\n";
    $sMsg .= "As you don't have any data associated to your account we have removed it.\r\n\r\n";
    $sMsg .= "Please feel free to recreate an account anytime.\r\n\r\n";
    $sMsg .= "Best regards.\r\n";
    $sMsg .= "The AppDB team.\r\n";

    mail($sEmail, "[AppDB] Warning: account removed", $sMsg, $sHeaders, "-fappdb@winehq.org");
}

function warnMaintainerDeleted($sEmail)
{
    $sHeaders  = "MIME-Version: 1.0\r\n";
    $sHeaders .= "From: AppDB <appdb@winehq.org>\r\n";
    $sHeaders .= "Reply-to: AppDB <appdb@winehq.orgl>\r\n";
    $sHeaders .= "X-Priority: 3\r\n";
    $sHeaders .= "X-Mailer: ".APPDB_OWNER." mailer\r\n";

    $sMsg  = "AppDB Warning: maintainer rights revoked\r\n";
    $sMsg .= "----------------------------------------\r\n";
    $sMsg .= "You didn't log in in the past seven month to the AppDB (".APPDB_OWNER_URL.")\r\n";
    $sMsg .= "As a result, you are not a maintainer anymore.\r\n\r\n";
    $sMsg .= "Please feel free to enroll again as a maintainer anytime.\r\n\r\n";
    $sMsg .= "Best regards.\r\n";
    $sMsg .= "The AppDB team.\r\n";

    mail($sEmail, "[AppDB] Warning: maintainer rights revoked", $sMsg, $sHeaders, "-fappdb@winehq.org");
}
?>
