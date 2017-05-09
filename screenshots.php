<?php
/**
 * Shows a page with several screenshot thumbnails.
 *
 * Mandatory parameters:
 *  - iAppId, application identifier
 *            AND/OR
 *  - iVersionId, version identifier
 * 
 * Optional parameters:
 *  - iImageId, image identifier (for deletion)
 *  - sScreenshotDesc, screenshot description (for insertion)
 *  - sCmd, action to perform ("screenshot_upload", "delete")
 * 
 * TODO:
 *  - replace iImageId with iScreenshotId
 *  - replace sCmd with iAction and replace "delete", "screenshot_upload", etc. with integer constants DELETE, UPLOAD, etc.
 *  - replace require_once with require after checking that it won't break anything
 */

// application environment
require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/screenshot.php");
require_once(BASE."include/application.php");
require_once(BASE."include/version.php");

// we issued a command
if(array_key_exists('sCmd', $aClean) && $aClean['sCmd'])
{
    // process screenshot upload
    if($aClean['sCmd'] == "screenshot_upload")
    {   
	//FIXME: use a defined value here instead of just 600000
        if($_FILES['sImageFile']['size']>600000)
        {
            addmsg("Your screenshot was not accepted because it is too big. Please try to keep your screenshots under 600KB by saving games/video screenshots to jpeg and normal applications to png you might be able to achieve very good results with less bytes", "red");
        } else
        {
            $oScreenshot = new Screenshot();
            $oScreenshot->iVersionId = $aClean['iVersionId'];
            $oScreenshot->sDescription = $aClean['sScreenshotDesc'];
            $oScreenshot->sTestedVersion = $aClean['sTestedVersion'];
            $oScreenshot->hFile = $_FILES['sImageFile'];
            $oScreenshot->create();
            $oScreenshot->free();
        }
    } elseif($aClean['sCmd'] == "delete" && is_numeric($aClean['iImageId'])) // process screenshot deletion
    {
        $oScreenshot = new Screenshot($aClean['iImageId']);
        $oScreenshot->delete();
        $oScreenshot->free();
    } 
    util_redirect_and_exit(apidb_fullurl("screenshots.php?iAppId=".$aClean['iAppId']."&iVersionId=".$aClean['iVersionId']));
}


// we didn't issued any command
$hResult = Screenshot::get_screenshots($aClean['iAppId'], $aClean['iVersionId']);
apidb_header("Screenshots");
$oApp = new Application($aClean['iAppId']);
$oVersion = new Version($aClean['iVersionId']);

if($hResult && query_num_rows($hResult))
{
    echo '&nbsp'; // Add some space between title bars
    echo html_frame_start("Screenshot Gallery for ".$oApp->sName, 500);

    // display thumbnails
    $c = 1;
    
    $iCurrentVersionId = 0;

    // optimization so we don't have to perform as many database queries
    // only update this variable when $iCurrentVersionId changes
    $bUserIsMaintainerOfVersion = false;

    echo "<div align=center><table><tr>\n";
    echo '&nbsp'; // Add some space between title bars

    while($oRow = query_fetch_object($hResult))
    {
        // if the current version changed then update the current version
        // and close the previous html frame if this isn't the
        // first frame
        if($oRow->versionId != $iCurrentVersionId)
        {
            if($iCurrentVersionId)
            {
                echo "</tr></table></div>\n";
                echo html_frame_end();
                $c=1;
            }
            $iCurrentVersionId = $oRow->versionId;
            $bUserIsMaintainerOfVersion = $_SESSION['current']->isMaintainer($iCurrentVersionId);

            echo html_frame_start("Version ".Version::lookup_name($iCurrentVersionId));
            echo "<div align=center><table><tr>\n";
        }
        $oScreenshot = new Screenshot($oRow->id);
        $img = $oScreenshot->get_thumbnail_img();

        // display image
        echo "<td>\n";
        echo $img;
        echo "<div align=center>". substr($oRow->description,0,20). "\n";
        echo '<br />Wine version: ';
        echo $oScreenshot->sTestedVersion ? $oScreenshot->sTestedVersion : 'N/A';
        //show admin delete link
        if($_SESSION['current']->isLoggedIn() &&
            (
              $_SESSION['current']->hasPriv("admin") ||
              $bUserIsMaintainerOfVersion
            )
          )
        {
            $oM = new objectManager("screenshot");
            $oM->setReturnTo("screenshots.php?iAppId=".$oScreenshot->iAppId."&amp;iVersionId=".$oScreenshot->iVersionId);
            echo '<br><a href="'.$oM->makeUrl("delete", $oScreenshot->iScreenshotId, "Delete Screenshot").'" class="btn btn-default"><i class="fa fa-trash-o"></i> Delete</a>';
        }

        echo "</div></td>\n";

        // end row if counter of 3
        if ($c % 3 == 0) echo "</tr><tr>\n";

        $c++;
    }
    echo "</tr></table></div><br>\n";

    echo html_frame_end(); // close the current version we are displaying
    echo html_frame_end(); // close the "Screenshot Gallary..." html frame
} else
{
 echo "<p align=\"center\">There are currently no screenshots for the selected version of this application.";
 echo "<br>Please consider submitting a screenshot for the selected version yourself.</p>";
}

// let's show the screenshot uploading box, but only
// if the user is logged in
if($aClean['iVersionId'] && $_SESSION['current']->isLoggedIn())
{
    echo "<p align=\"center\">";
    echo "<table><tr><th>Please follow these simple rules</th></tr>";
    echo "<tr><td><ul>";
    echo "<li>Do not upload screenshots of error messages, installers, game menus etc.</li>";
    echo "<li>Crop the image so that only the application is shown and not your desktop.</li>";
    echo "<li>Files must be smaller than 600KB.</li>";
    echo "</ul></td></tr></table></p>";

    echo '<form enctype="multipart/form-data" action="screenshots.php" name="sImageForm" method="post">',"\n";
    echo html_frame_start("Upload Screenshot","400","",0);
    echo '<table border=0 cellpadding=6 cellspacing=0 width="100%">',"\n";
      
    echo '<tr><td class=color1>Image</td><td class=color0><input name="sImageFile" type="file" size="24"></td></tr>',"\n";
    echo '<tr><td class="color1">Description</td><td class="color0"><input type="text" name="sScreenshotDesc" maxlength="20" size="24"></td></tr>',"\n";
    echo '<tr><td class="color1">Wine version</td><td class="color0">'.make_bugzilla_version_list('sTestedVersion').'</td></tr>',"\n";
       
    echo '<tr><td colspan=2 align=center class=color3><input type="submit" value="Send File"></td></tr>',"\n";
    echo '</table>',"\n";
    echo html_frame_end();
    echo '<input type="hidden" name="MAX_FILE_SIZE" value="4000000">',"\n";
    echo '<input type="hidden" name="sCmd" value="screenshot_upload">',"\n";
    echo '<input type="hidden" name="iVersionId" value="'.$aClean['iVersionId'].'"></form>',"\n";
} else if(!$_SESSION['current']->isLoggedIn()) // else let the person know that if they log in they can submit screenshots
{
  echo '<div align="center"><a href="'.login_url().'">';
  echo "Log in</a> to submit screenshots</div>\n";
} else
{
    echo html_frame_start("Upload Screenshot", "30%");
    echo 'If you would like to submit screenshots, please select an application version below.<br>';
    echo '<ul>';
    foreach($oApp->getVersions(true) as $oVersion)
        echo '<li><a href="'.BASE.'screenshots.php?iVersionId='.$oVersion->objectGetId().'">'.$oVersion->sName.'</a></li>';

    echo '</ul>';
    echo html_frame_end();
}

echo html_back_link(1);

apidb_footer();
?>
