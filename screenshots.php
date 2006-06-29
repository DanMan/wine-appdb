<?php
/*******************************************************************/
/* this script expects appId and optionally versionId as arguments */
/* OR                                                              */
/* cmd and imageId                                                 */
/*******************************************************************/

/*
 * application environment
 */ 
include("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/screenshot.php");
require(BASE."include/application.php");
require(BASE."include/mail.php");

$aClean = array(); //array of filtered user input

$aClean['cmd'] = makeSafe($_REQUEST['cmd']);
$aClean['versionId'] = makeSafe($_REQUEST['versionId']);
$aClean['screenshot_desc'] = makeSafe($_REQUEST['screenshot_desc']);
$aClean['imageId'] = makeSafe($_REQUEST['imageId']);
$aClean['appId'] = makeSafe($_REQUEST['appId']);

/*
 * We issued a command.
 */ 
if($aClean['cmd'])
{
    // process screenshot upload
    if($aClean['cmd'] == "screenshot_upload")
    {   
        if($_FILES['imagefile']['size']>600000)
        {
            addmsg("Your screenshot was not accepted because it is too big. Please try to keep your screenshots under 600KB by saving games/video screenshots to jpeg and normal applications to png you might be able to achieve very good results with less bytes", "red");
        } else
        {
            $oScreenshot = new Screenshot();
            $oScreenshot->create($aClean['versionId'], $aClean['screenshot_desc'], $_FILES['imagefile']);
            $oScreenshot->free();
        }
    } elseif($aClean['cmd'] == "delete" && is_numeric($aClean['imageId'])) // process screenshot deletion
    {
        $oScreenshot = new Screenshot($aClean['imageId']);
        $oScreenshot->delete();
        $oScreenshot->free();
    } 
    redirect(apidb_fullurl("screenshots.php?appId=".$aClean['appId']."&versionId=".$aClean['versionId']));
}


/*
 * We didn't issued any command.
 */ 
$hResult = get_screenshots($aClean['appId'], $aClean['versionId']);   
apidb_header("Screenshots");
$oApp = new Application($aClean['appId']);
$oVersion = new Version($aClean['versionId']);

if($hResult && mysql_num_rows($hResult))
{
    echo html_frame_start("Screenshot Gallery for ".$oApp->sName." ".$oVersion->sName,500);

    // display thumbnails
    $c = 1;
    echo "<div align=center><table><tr>\n";
    while($oRow = mysql_fetch_object($hResult))
    {
        if(!$aClean['versionId'] && $oRow->versionId != $currentVersionId)
        {
            if($currentVersionId)
            {
                echo "</tr></table></div>\n";
                echo html_frame_end();
                $c=1;
            }
            $currentVersionId=$oRow->versionId;
            echo html_frame_start("Version ".Version::lookup_name($currentVersionId));
            echo "<div align=center><table><tr>\n";
        }
        $img = get_thumbnail($oRow->id);
        // display image
        echo "<td>\n";
        echo $img;
        echo "<div align=center>". substr($oRow->description,0,20). "\n";
        
        //show admin delete link
        if($_SESSION['current']->isLoggedIn() && ($_SESSION['current']->hasPriv("admin") || 
               $_SESSION['current']->isMaintainer($aClean['versionId'])))
        {
            echo "<br />[<a href='screenshots.php?cmd=delete&imageId=$oRow->id&appId=".$aClean['appId']."&versionId=".$aClean['versionId']."'>Delete Image</a>]";
        }

        echo "</div></td>\n";

        // end row if counter of 3
        if ($c % 3 == 0) echo "</tr><tr>\n";

        $c++;
    }
    echo "</tr></table></div><br />\n";

    echo html_frame_end("Click thumbnail to view image in new window.");
} else {
 echo "<p align=\"center\">There are currently no screenshots for the selected version of this application.";
 echo "<br />Please consider submitting a screenshot for the selected version yourself.</p>";
}

if($aClean['versionId'])
{
    //image upload box
    echo '<form enctype="multipart/form-data" action="screenshots.php" name="imageForm" method="post">',"\n";
    echo html_frame_start("Upload Screenshot","400","",0);
    echo '<table border=0 cellpadding=6 cellspacing=0 width="100%">',"\n";
      
    echo '<tr><td class=color1>Image</td><td class=color0><input name="imagefile" type="file" size="24"></td></tr>',"\n";
    echo '<tr><td class="color1">Description</td><td class="color0"><input type="text" name="screenshot_desc" maxlength="20" size="24"></td></tr>',"\n";
       
    echo '<tr><td colspan=2 align=center class=color3><input type="submit" value="Send File"></td></tr>',"\n";
    echo '</table>',"\n";
    echo html_frame_end();
    echo '<input type="hidden" name="MAX_FILE_SIZE" value="4000000" />',"\n";
    echo '<input type="hidden" name="cmd" value="screenshot_upload" />',"\n";
    echo '<input type="hidden" name="versionId" value="'.$aClean['versionId'].'"></form />',"\n";
}
echo html_back_link(1);
apidb_footer();
?>
