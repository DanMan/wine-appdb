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
require_once(BASE."include/testResults.php");

$aClean = array(); //array of filtered user input

$aClean['iAppId'] = makeSafe($_REQUEST['iAppId']);
$aClean['iVersionId'] = makeSafe($_REQUEST['iVersionId']);
$aClean['sSub'] = makeSafe($_REQUEST['sSub']);
$aClean['iBuglinkId'] = makeSafe($_REQUEST['iBuglinkId']);

$oApp = new Application($aClean['iAppId']);
$oVersion = new Version($aClean['iVersionId']);

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
function display_bundle($iAppId)
{
    $oApp = new Application($appId);
    $hResult = query_parameters("SELECT appFamily.appId, appName, description FROM appBundle, appFamily ".
                            "WHERE appFamily.queued='false' AND bundleId = '?' AND appBundle.appId = appFamily.appId",
                            $iAppId);
    if(!$hResult || mysql_num_rows($hResult) == 0)
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
    while($ob = mysql_fetch_object($hResult))
    {
        //set row color
        $bgcolor = ($c % 2 == 0) ? "color0" : "color1";

        //display row
        echo "<tr class=\"$bgcolor\">\n";
        echo "    <td><a href=\"appview.php?iAppId=$ob->appId\">".stripslashes($ob->appName)."</a></td>\n";
        echo "    <td>".util_trim_description($oApp->sDescription)."</td>\n";
        echo "</tr>\n\n";

        $c++;
    }

    echo "</table>\n\n";
    echo html_frame_end();
}

if(!is_numeric($aClean['iAppId']) && !is_numeric($aClean['iVersionId']))
{
    util_show_error_page("Something went wrong with the application or version id");
    exit;
}

if ($aClean['sSub'])
{
    if(($aClean['sSub'] == 'delete' ) && ($aClean['iBuglinkId']))
    {
        if(($_SESSION['current']->hasPriv("admin") ||
            $_SESSION['current']->isMaintainer($oVersion->iVersionId) ||
            $_SESSION['current']->isSuperMaintainer($oVersion->iAppId)))
        {
            $oBuglink = new bug($aClean['iBuglinkId']);
            $oBuglink->delete();
            redirect(apidb_fullurl("appview.php?iVersionId=".$aClean['iVersionId']));
            exit;
        }
 
    }
    if(($aClean['sSub'] == 'unqueue' ) && ($aClean['iBuglinkId']))
    {
        if(($_SESSION['current']->hasPriv("admin") ||
            $_SESSION['current']->isMaintainer($oVersion->iVersionId) ||
            $_SESSION['current']->isSuperMaintainer($oVersion->iAppId)))
        {
            $oBuglink = new bug($aClean['iBuglinkId']);
            $oBuglink->unqueue();
            redirect(apidb_fullurl("appview.php?iVersionId=".$aClean['iVersionId']));
            exit;
        }
 
    }
    if(($aClean['sSub'] == 'Submit a new bug link.' ) && ($aClean['iBuglinkId']))
    {
        $oBuglink = new bug();
        $oBuglink->create($aClean['iVersionId'],$aClean['iBuglinkId']);
        redirect(apidb_fullurl("appview.php?iVersionId=".$aClean['iVersionId']));
        exit;
    }
    if($aClean['sSub'] == 'StartMonitoring')
    {
        $oMonitor = new Monitor();
        $oMonitor->create($_SESSION['current']->iUserId,$aClean['iAppId'],$aClean['iVersionId']);
        redirect(apidb_fullurl("appview.php?iVersionId=".$aClean['iVersionId']));
        exit;
    }
    if($aClean['sSub'] == 'StopMonitoring')
    {
        $oMonitor = new Monitor();
        $oMonitor->find($_SESSION['current']->iUserId,$aClean['iAppId'],$aClean['iVersionId']);
        if($oMonitor->iMonitorId)
        {
            $oMonitor->delete();
        }
        redirect(apidb_fullurl("appview.php?iVersionId=".$aClean['iVersionId']));
        exit;
    }

}

/**
 * We want to see an application family (=no version).
 */
if($aClean['iAppId'])
{
    $oApp = new Application($aClean['iAppId']);
    $oApp->display();
} else if($aClean['iVersionId']) // We want to see a particular version.
{
    $oVersion = new Version($aClean['iVersionId']);
    $oVersion->display();
} else
{
    // Oops! Called with no params, bad llamah!
    util_show_error_page('Page Called with No Params!');
    exit;
}

apidb_footer();
?>
