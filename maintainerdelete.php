<?php
/**
 * Deletes a maintainer.
 *
 * Mandatory parameters:
 *  - iAppId, application identifier
 *            AND/OR
 *  - iVersionId, version identifier
 * 
 * Optional parameters:
 *  - iSuperMaintainer, 1 if we want to delete a supermaintainer instead of a normal maintainer
 *  - iConfirmed, 1 if the deletion is confirmed
 * 
 * TODO:
 *  - replace iSuperMaintainer with bIsSuperMaintainer
 *  - replace iConfirmed with bHasConfirmed
 *  - $oApp is not defined in the else part of this script
 */

// application environment
require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/category.php");
require_once(BASE."include/application.php");

if(!$_SESSION['current']->isLoggedIn())
    util_show_error_page_and_exit("You need to be logged in to resign from being a maintainer.");


if($aClean['iConfirmed'])
{
    $oApp = new Application($aClean['iAppId']);
    if($aClean['iSuperMaintainer'])
    {
        apidb_header("You have resigned as super maintainer of ".$oApp->sName);
        $oMaintainer = maintainer::findAppMaintainer($_SESSION['current']->iUserId, $aClean['iAppId']);
        $result = $oMaintainer->delete();
    } else
    {
        $oVersion = new Version($aClean['iVersionId']);
        apidb_header("You have resigned as maintainer of ".$oApp->sName." ".$oVersion->sName);
        $oMaintainer = maintainer::findVersionMaintainer($_SESSION['current']->iUserId, $aClean['iVersionId']);
        $result = $oMaintainer->delete();
    }
/*   echo html_frame_start("Removing",400,"",0);
*/
    if($result)
    {
        if($aClean['iSuperMaintainer'])
            echo "You were removed as a super maintainer of ".$oApp->sName;
        else
            echo "You were removed as a maintainer of ".$oApp->sName." ".$oVersion->sName;
    }
} else
{
    if($aClean['iSuperMaintainer'])
        apidb_header("Confirm super maintainer resignation of ".$oApp->sName);
    else
        apidb_header("Confirm maintainer resignation of ".$oApp->sName." ".$oVersion->sName);


    echo '<form name="sDeleteMaintainer" action="maintainerdelete.php" method="post" enctype="multipart/form-data">',"\n";

    echo html_frame_start("Confirm",400,"",0);
    echo "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";
    echo "<input type=hidden name='iAppId' value={$aClean['iAppId']}>";
    echo "<input type=hidden name='iVersionId' value={$aClean['iVersionId']}>";
    echo "<input type=hidden name='iSuperMaintainer' value={$aClean['iSuperMaintainer']}>";
    echo "<input type=hidden name='iConfirmed' value=1>";

    if($aClean['iSuperMaintainer'])
    {
        echo "<tr><td>Are you sure that you want to be removed as a super maintainer of this application?</tr></td>\n";
        echo '<tr><td align=center><input type=submit value=" Confirm resignation as supermaintainer " class=button>', "\n";
    } else
    {
        echo "<tr><td>Are you sure that you want to be removed as a maintainer of this application?</tr></td>\n";
        echo '<tr><td align=center><input type=submit value=" Confirm resignation as maintainer " class=button>', "\n";
    }

    echo "</td></tr></table>";
}

echo html_frame_end();

apidb_footer();


