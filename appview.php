<?php
/**********************************/
/* code to display an application */
/**********************************/

/*
 * application environment
 */
include("path.php");
require(BASE."include/incl.php");
require(BASE."include/application.php");
require(BASE."include/appdb.php");
require(BASE."include/vote.php");
require(BASE."include/category.php");
require(BASE."include/maintainer.php");
require(BASE."include/mail.php");
require(BASE."include/monitor.php");


$oApp = new Application($_REQUEST['appId']);
$oVersion = new Version($_REQUEST['versionId']);

/**
 * display the full path of the Category we are looking at
 */
function display_catpath($catId, $appId, $versionId = '')
{
    $cat = new Category($catId);

    $catFullPath = make_cat_path($cat->getCategoryPath(), $appId, $versionId);
    echo html_frame_start("",'98%','',2);
    echo "<p><b>Category: ". $catFullPath ."</b><br />\n";
    echo html_frame_end();
}


/**
 * display the SUB apps that belong to this app 
 */
function display_bundle($appId)
{
    $oApp = new Application($appId);
    $result = query_appdb("SELECT appFamily.appId, appName, description FROM appBundle, appFamily ".
                        "WHERE appFamily.queued='false' AND bundleId = $appId AND appBundle.appId = appFamily.appId");
    if(!$result || mysql_num_rows($result) == 0)
    {
         return; // do nothing
    }

    echo html_frame_start("","98%","",0);
    echo "<table width=\"100%\" border=\"0\" cellpadding=\"3\" cellspacing=\"1\">\n\n";

    echo "<tr class=\"color4\">\n";
    echo "    <td>Application Name</td>\n";
    echo "    <td>Description</td>\n";
    echo "</tr>\n\n";

    $c = 0;
    while($ob = mysql_fetch_object($result)) {
        //set row color
        $bgcolor = ($c % 2 == 0) ? "color0" : "color1";

        //display row
        echo "<tr class=\"$bgcolor\">\n";
        echo "    <td><a href=\"appview.php?appId=$ob->appId\">".stripslashes($ob->appName)."</a></td>\n";
        echo "    <td>".trim_description($oApp->sDescription)."</td>\n";
        echo "</tr>\n\n";

        $c++;
    }

    echo "</table>\n\n";
    echo html_frame_end();
}

/* Show note */
function show_note($sType,$oData){
    global $oVersion;

    switch($sType)
    {
        case 'WARNING':
        $color = 'red';
        $title = 'Warning';
        break;

        case 'HOWTO';
        $color = 'green';
        $title = 'HOWTO';
        break;

        default:
        
        if(!empty($oData->noteTitle))
            $title = $oData->noteTitle;
        else 
            $title = 'Note';
            
        $color = 'blue';
    }
    
    $s = html_frame_start("","98%",'',0);

    $s .= "<table width=\"100%\" border=\"0\" cellspacing=\"0\">\n";
    $s .= "<tr bgcolor=\"".$color."\" align=\"center\" valign=\"top\"><td><b>".$title."</b></td></tr>\n";
    $s .= "<tr><td class=\"note\">\n";
    $s .= $oData->noteDesc;
    $s .= "</td></tr>\n";

    if ($_SESSION['current']->hasPriv("admin") || $_SESSION['current']->isMaintainer($oVersion->iVersionId) || $_SESSION['current']->isSuperMaintainer($oVersion->iAppId))
    {
        $s .= "<tr class=\"color1\" align=\"center\" valign=\"top\"><td>";
        $s .= "<form method=\"post\" name=\"message\" action=\"admin/editAppNote.php?noteId={$oData->noteId}\">";
        $s .= '<input type="submit" value="Edit Note" class="button">';
        $s .= '</form></td></tr>';
    }

    $s .= "</table>\n";
    $s .= html_frame_end();

    return $s;
}

if(!is_numeric($_REQUEST['appId']) && !is_numeric($_REQUEST['versionId']))
{
    errorpage("Something went wrong with the application or version id");
    exit;
}

if ($_REQUEST['sub'])
{
    if(($_REQUEST['sub'] == 'delete' ) && ($_REQUEST['buglinkId']))
    {
        if(($_SESSION['current']->hasPriv("admin") ||
            $_SESSION['current']->isMaintainer($oVersion->iVersionId) ||
            $_SESSION['current']->isSuperMaintainer($oVersion->iAppId)))
        {
            $oBuglink = new bug($_REQUEST['buglinkId']);
            $oBuglink->delete();
            redirect(apidb_fullurl("appview.php?versionId=".$_REQUEST['versionId']));
            exit;
        }
 
    }
    if(($_REQUEST['sub'] == 'unqueue' ) && ($_REQUEST['buglinkId']))
    {
        if(($_SESSION['current']->hasPriv("admin") ||
            $_SESSION['current']->isMaintainer($oVersion->iVersionId) ||
            $_SESSION['current']->isSuperMaintainer($oVersion->iAppId)))
        {
            $oBuglink = new bug($_REQUEST['buglinkId']);
            $oBuglink->unqueue();
            redirect(apidb_fullurl("appview.php?versionId=".$_REQUEST['versionId']));
            exit;
        }
 
    }
    if(($_REQUEST['sub'] == 'Submit a new bug link.' ) && ($_REQUEST['buglinkId']))
    {
        $oBuglink = new bug();
        $oBuglink->create($_REQUEST['versionId'],$_REQUEST['buglinkId']);
        redirect(apidb_fullurl("appview.php?versionId=".$_REQUEST['versionId']));
        exit;
    }
    if($_REQUEST['sub'] == 'StartMonitoring')
    {
        $oMonitor = new Monitor();
        $oMonitor->create($_SESSION['current']->iUserId,$_REQUEST['appId'],$_REQUEST['versionId']);
        redirect(apidb_fullurl("appview.php?versionId=".$_REQUEST['versionId']));
        exit;
    }
    if($_REQUEST['sub'] == 'StopMonitoring')
    {
        $oMonitor = new Monitor();
        $oMonitor->find($_SESSION['current']->iUserId,$_REQUEST['appId'],$_REQUEST['versionId']);
        if($oMonitor->iMonitorId)
        {
            $oMonitor->delete();
        }
        redirect(apidb_fullurl("appview.php?versionId=".$_REQUEST['versionId']));
        exit;
    }

}

/**
 * We want to see an application family (=no version).
 */
if($_REQUEST['appId'])
{
    $oApp = new Application($_REQUEST['appId']);

    // show Vote Menu
    if($_SESSION['current']->isLoggedIn())
        apidb_sidebar_add("vote_menu");

    // header
    apidb_header("Viewing App - ".$oApp->sName);

    // cat display
    display_catpath($oApp->iCatId, $oApp->iAppId);

    // set Vendor
    $oVendor = new Vendor($oApp->iVendorId);

    // set URL
    $appLinkURL = ($oApp->sWebpage) ? "<a href=\"".$oApp->sWebpage."\">".substr(stripslashes($oApp->sWebpage),0,30)."</a>": "&nbsp;";
  
    // start display application
    echo html_frame_start("","98%","",0);
    echo "<tr><td class=color4 valign=top>\n";
    echo "  <table>\n";
    echo "    <tr><td>\n";

    echo '      <table width="250" border="0" cellpadding="3" cellspacing="1">',"\n";
    echo "        <tr class=color0 valign=top><td width=\"100\"><b>Name</b></td><td width='100%'> ".$oApp->sName." </td>\n";
    echo "        <tr class=\"color1\"><td><b>Vendor</b></td><td> ".
         "        <a href='vendorview.php?vendorId=$oVendor->iVendorId'> ".$oVendor->sName." </a> &nbsp;\n";
    echo "        <tr class=\"color0\"><td><b>Votes</b></td><td> ";
    echo vote_count_app_total($oApp->iAppId);
    echo "        </td></tr>\n";
    
    // main URL
    echo "        <tr class=\"color1\"><td><b>URL</b></td><td>".$appLinkURL."</td></tr>\n";

    // optional links
    $result = query_appdb("SELECT * FROM appData WHERE appId = ".$_REQUEST['appId']." AND versionID = 0 AND type = 'url'");
    if($result && mysql_num_rows($result) > 0)
    {
        echo "        <tr class=\"color1\"><td> <b>Links</b></td><td>\n";
        while($ob = mysql_fetch_object($result))
        {
            echo "        <a href='$ob->url'>".substr(stripslashes($ob->description),0,30)."</a> <br />\n";
        }
            echo "        </td></tr>\n";
        }
  
    // image
    $img = get_screenshot_img($oApp->iAppId);
    echo "<tr><td align=\"center\" colspan=\"2\">$img</td></tr>\n";
    
    echo "      </table>\n"; /* close of name/vendor/bugs/url table */

    echo "    </td></tr>\n";
    echo "    <tr><td>\n";

    // Display all supermaintainers maintainers of this application
    echo "      <table class=\"color4\" width=\"250\" border=\"1\">\n";
    echo "        <tr><td align=\"left\"><b>Super maintainers:</b></td></tr>\n";
    $other_maintainers = getSuperMaintainersUserIdsFromAppId($oApp->iAppId);
    if($other_maintainers)
    {
        echo "        <tr><td align=\"left\"><ul>\n";
        while(list($index, $userIdValue) = each($other_maintainers))
        {
            $oUser = new User($userIdValue);
            echo "        <li>".$oUser->sRealname."</li>\n";
        }
        echo "</ul></td></tr>\n";
    } else
    {
        echo "        <tr><td align=right>No maintainers.Volunteer today!</td></tr>\n";
    }

    // Display the app maintainer button
    echo '        <tr><td align="center">';
    if($_SESSION['current']->isLoggedIn())
    {
        /* are we already a maintainer? */
        if($_SESSION['current']->isSuperMaintainer($oApp->iAppId)) /* yep */
        {
            echo '        <form method="post" name="message" action="maintainerdelete.php"><input type=submit value="Remove yourself as a super maintainer" class="button">';
        } else /* nope */
        {
            echo '        <form method="post" name="message" action="maintainersubmit.php"><input type="submit" value="Be a super maintainer of this app" class="button" title="Click here to know more about super maintainers.">';
        }

        echo "        <input type=\"hidden\" name=\"appId\" value=\"".$oApp->iAppId."\">";
        echo "        <input type=\"hidden\" name=\"superMaintainer\" value=\"1\">"; /* set superMaintainer to 1 because we are at the appFamily level */
        echo "        </form>";

        if($_SESSION['current']->isSuperMaintainer($oApp->iAppId) || $_SESSION['current']->hasPriv("admin"))
        {
            echo '        <form method="post" name="edit" action="admin/editAppFamily.php"><input type="hidden" name="appId" value="'.$_REQUEST['appId'].'"><input type="submit" value="Edit Application" class="button"></form>';
        }
        if($_SESSION['current']->isLoggedIn())
        {
            echo '<form method="post" name="message" action="appsubmit.php?appId='.$oApp->iAppId.'&amp;apptype=2">';
            echo '<input type=submit value="Submit new version" class="button">';
            echo '</form>';
        }
        if($_SESSION['current']->hasPriv("admin"))
        {
            $url = BASE."admin/deleteAny.php?what=appFamily&amp;appId=".$oApp->iAppId."&amp;confirmed=yes";
            echo "        <form method=\"post\" name=\"edit\" action=\"javascript:deleteURL('Are you sure?', '".$url."')\"><input type=\"submit\" value=\"Delete App\" class=\"button\"></form>";
            echo '        <form method="post" name="edit" action="admin/editBundle.php"><input type="hidden" name="bundleId" value="'.$oApp->iAppId.'"><input type="submit" value="Edit Bundle" class="button"></form>';
        }
    } else
    {
        echo '<form method="post" action="account.php?cmd=login"><input type="submit" value="Log in to become a super maintainer" class="button"></form>';
    }
    echo "        </td></tr>\n";
    echo "      </table>\n"; /* close of super maintainers table */
    echo "    </td></tr>\n";
    echo "  </table>\n"; /* close the table that contains the whole left hand side of the upper table */

    // description
    echo "  <td class=color2 valign=top width='100%'>\n";
    echo "    <table width='100%' border=0><tr><td width='100%' valign=top><span class=\"title\">Description</span>\n";
    echo $oApp->sDescription;
    echo "    </td></tr></table>\n";
    echo html_frame_end("For more details and user comments, view the versions of this application.");

    // display versions
    display_versions($oApp->iAppId,$oApp->aVersionsIds);

    // display bundle
    display_bundle($oApp->iAppId);

    // disabled for now
    //log_application_visit($oApp->iAppId);
}


/*
 * We want to see a particular version.
 */
else if($_REQUEST['versionId'])
{
    $oVersion = new Version($_REQUEST['versionId']);
    $oApp = new Application($oVersion->iAppId);
    if(!$oApp->iAppId) 
    {
        // Oops! application not found or other error. do something
        errorpage('Internal Database Access Error. No App found.');
        exit;
    }

    if(!$oVersion->iVersionId) 
    {
        // Oops! Version not found or other error. do something
        errorpage('Internal Database Access Error. No Version Found.');
        exit;
    }

    // header
    apidb_header("Viewing App- ".$oApp->sName." Version - ".$oVersion->sName);

    // cat
    display_catpath($oApp->iCatId, $oApp->iAppId, $oVersion->iVersionId);
  
    // set URL
    $appLinkURL = ($oApp->sWebpage) ? "<a href=\"".$oApp->sWebpage."\">".substr(stripslashes($oApp->sWebpage),0,30)."</a>": "&nbsp;";

    // start version display
    echo html_frame_start("","98%","",0);
    echo '<tr><td class="color4" valign="top">',"\n";
    echo '<table width="250" border="0" cellpadding="3" cellspacing="1">',"\n";
    echo "<tr class=\"color0\" valign=\"top\"><td width=\"100\"> <b>Name</b></td><td width=\"100%\">".$oApp->sName."</td>\n";
    echo "<tr class=\"color1\" valign=\"top\"><td><b>Version</b></td><td>".$oVersion->sName."</td></tr>\n";

    // main URL
    echo "        <tr class=\"color1\"><td><b>URL</b></td><td>".$appLinkURL."</td></tr>\n";

    // links
    $result = query_appdb("SELECT * FROM appData WHERE versionID = ".$oVersion->iVersionId." AND type = 'url'");
    if($result && mysql_num_rows($result) > 0)
    {
        echo "        <tr class=\"color1\"><td><b>Links</b></td><td>\n";
        while($ob = mysql_fetch_object($result))
        {
            echo "        <a href=\"$ob->url\">".substr(stripslashes($ob->description),0,30)."</a> <br />\n";
        }
            echo "        </td></tr>\n";
    }    

    // rating Area
    echo "<tr class=\"color1\" valign=\"top\"><td><b>Maintainer Rating</b></td><td>".$oVersion->sTestedRating."</td></tr>\n";
    echo "<tr class=\"color0\" valign=\"top\"><td><b>Maintainers Version</b></td><td>".$oVersion->sTestedRelease."</td></tr>\n";

    // image
    $img = get_screenshot_img($oApp->iAppId, $oVersion->iVersionId);
    echo "<tr><td align=\"center\" colspan=\"2\">$img</td></tr>\n";

    // display all maintainers of this application
    echo "<tr class=\"color0\"><td align=\"left\" colspan=\"2\"><b>Maintainers of this version:</b>\n";
    echo "<table width=\"250\" border=\"0\">";
    $aMaintainers = getMaintainersUserIdsFromAppIdVersionId($oVersion->iVersionId);
    $aSupermaintainers = getSuperMaintainersUserIdsFromAppId($oVersion->iAppId);
    $aAllMaintainers = array_merge($aMaintainers,$aSupermaintainers);
    $aAllMaintainers = array_unique($aAllMaintainers);
    if(sizeof($aAllMaintainers)>0)
    {
        echo "<tr class=\"color0\"><td align=\"left\" colspan=\"2\"><ul>";
        while(list($index, $userIdValue) = each($aAllMaintainers))
        {
            $oUser = new User($userIdValue);
            echo "<li>".$oUser->sRealname."</li>";
        }
        echo "</ul></td></tr>\n";
    } else
    {
        echo "<tr class=color0><td align=right colspan=2>";
        echo "No maintainers. Volunteer today!</td></tr>\n";
    }
    echo "</table></td></tr>";

    // display the app maintainer button
    echo '<tr><td colspan="2" align="center">';
    if($_SESSION['current']->isLoggedIn())
    {
        /* is this user a maintainer of this version by virtue of being a super maintainer */
        /* of this app family? */
        if($_SESSION['current']->isSuperMaintainer($oApp->iAppId))
        {
            echo '<form method="post" name="message" action="maintainerdelete.php">';
            echo '<input type="submit" value="Remove yourself as a supermaintainer" class="button">';
            echo '<input type="hidden" name="superMaintainer" value="1">';
            echo "<input type=hidden name=\"appId\" value=\"".$oApp->iAppId."\">";
            echo "<input type=hidden name=\"versionId\" value=\"".$oVersion->iVersionId."\">";
            echo "</form>";
        } else
        {
            /* are we already a maintainer? */
            if($_SESSION['current']->isMaintainer($oVersion->iVersionId)) /* yep */
            {
                echo '<form method="post" name="message" action="maintainerdelete.php">';
                echo '<input type="submit" value="Remove yourself as a maintainer" class=button>';
                echo '<input type="hidden" name="superMaintainer" value="0">';
                echo "<input type=hidden name=\"appId\" value=\"".$oApp->iAppId."\">";
                echo "<input type=hidden name=\"versionId\" value=\"".$oVersion->iVersionId."\">";
                echo "</form>";
            } else /* nope */
            {
                echo '<form method="post" name="message" action="maintainersubmit.php">';
                echo '<input type="submit" value="Be a maintainer for this app" class="button" title="Click here to know more about maintainers.">';
                echo "<input type=hidden name=\"appId\" value=\"".$oApp->iAppId."\">";
                echo "<input type=hidden name=\"versionId\" value=\"".$oVersion->iVersionId."\">";
                echo "</form>";
                $oMonitor = new Monitor();
                $oMonitor->find($_SESSION['current']->iUserId,$oApp->iAppId,$oVersion->iVersionId);
                if(!$oMonitor->iMonitorId)
                {
                    echo '<form method=post name=message action=appview.php?versionId='.$oVersion->iVersionId.'&appId='.$oApp->iAppId.'>';
                    echo '<input type=hidden name="sub" value="StartMonitoring" />';
                    echo '<input type=submit value="Monitor Version" class="button" />';
                    echo "</form>";
                }
            }
        }

    } else
    {
        echo '<form method="post" name="message" action="account.php">';
        echo '<input type="hidden" name="cmd" value="login">';
        echo '<input type=submit value="Log in to become an app maintainer" class="button">';
        echo '</form>';
    }
    
    echo "</td></tr>";

    if ($_SESSION['current']->hasPriv("admin") || $_SESSION['current']->isMaintainer($oVersion->iVersionId) || $_SESSION['current']->isSuperMaintainer($oVersion->iAppId))
    {
        echo '<tr><td colspan="2" align="center">';
        echo '<form method="post" name="message" action="admin/editAppVersion.php">';
        echo '<input type="hidden" name="appId" value="'.$oApp->iAppId.'" />';
        echo '<input type="hidden" name="versionId" value="'.$oVersion->iVersionId.'" />';
        echo '<input type=submit value="Edit Version" class="button" />';
        echo '</form>';
        $url = BASE."admin/deleteAny.php?what=appVersion&amp;appId=".$oApp->iAppId."&amp;versionId=".$oVersion->iVersionId."&amp;confirmed=yes";
        echo "<form method=\"post\" name=\"delete\" action=\"javascript:deleteURL('Are you sure?', '".$url."')\">";
        echo '<input type=submit value="Delete Version" class="button" />';
        echo '</form>';
        echo '<form method="post" name="message" action="admin/addAppNote.php">';
        echo '<input type="hidden" name="versionId" value="'.$oVersion->iVersionId.'" />';
        echo '<input type="submit" value="Add Note" class="button" />';
        echo '</form>';
        echo '<form method=post name=message action=admin/addAppNote.php?versionId='.$oVersion->iVersionId.'>';
        echo '<input type=hidden name="noteTitle" value="HOWTO" />';
        echo '<input type=submit value="Add How To" class="button" />';
        echo '</form>';
        echo '<form method=post name=message action=admin/addAppNote.php?versionId='.$oVersion->iVersionId.'>';
        echo '<input type=hidden name="noteTitle" value="WARNING" />';
        echo '<input type=submit value="Add Warning" class="button" />';
        echo '</form>';
        echo "</td></tr>";
    }
    $oMonitor = new Monitor();
    $oMonitor->find($_SESSION['current']->iUserId,$oApp->iAppId,$oVersion->iVersionId);
    if($oMonitor->iMonitorId)
    {

        echo '<tr><td colspan="2" align="center">';
        echo '</form>';
        echo '<form method=post name=message action=appview.php?versionId='.$oVersion->iVersionId.'>';
        echo '<input type=hidden name="sub" value="StopMonitoring" />';
        echo '<input type=submit value="Stop Monitoring Version" class="button" />';
        echo '</form>';
        echo "</td></tr>";
    } 
    echo "</table><td class=color2 valign=top width='100%'>\n";

    // description
    echo "<table width='100%' border=0><tr><td width='100%' valign=top> <b>Description</b><br />\n";
    echo $oVersion->sDescription;
    echo "</td></tr>";

    /* close the table */
    echo "</table>\n";

    echo html_frame_end();

    view_version_bugs($oVersion->iVersionId, $oVersion->aBuglinkIds);    

    $rNotes = query_appdb("SELECT * FROM appNotes WHERE versionId = ".$oVersion->iVersionId);
    
    while( $oNote = mysql_fetch_object($rNotes) )
    {
        echo show_note($oNote->noteTitle,$oNote);
    }
    
    // Comments Section
    view_app_comments($oVersion->iVersionId);
  
} else 
{
    // Oops! Called with no params, bad llamah!
    errorpage('Page Called with No Params!');
    exit;
}

apidb_footer();
?>
