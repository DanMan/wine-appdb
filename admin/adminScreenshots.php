<?php
/************************************************************/
/* Page for managing all of the screenshots in the AppDB    */
/* Without having go into each application version to do so */
/************************************************************/

require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/screenshot.php");
require_once(BASE."include/application.php");

// deny access if not admin
if(!$_SESSION['current']->hasPriv("admin"))
    util_show_error_page_and_exit("Insufficient privileges.");

/*
 * We issued a delete command.
 */ 
if(isset($aClean['sCmd']))
{
    // process screenshot deletion
    if($aClean['sCmd'] == "delete" && is_numeric($aClean['iImageId']))
    {
        $oScreenshot = new Screenshot($aClean['iImageId']);
        $oScreenshot->delete();
        $oScreenshot->free();
    } 
    util_redirect_and_exit($_SERVER['PHP_SELF'].
             "?iItemsPerPage=".$aClean['iItemsPerPage'].
             "&amp;iPage=".$aClean['iPage']);
}


apidb_header("Screenshots");
// regenerate all screenshots
if(isset($aClean['sRegenerate']))
{
    $sQuery = "SELECT id FROM appData WHERE type = 'screenshot'";
    $hResult = query_parameters($sQuery);
    while($oRow = query_fetch_object($hResult))
    {
        echo "REGENERATING IMAGE ".$oRow->id."<br>";
        $screenshot = new Screenshot($oRow->id);
        $screenshot->generate();
        $screenshot->free();
        set_time_limit(60);
    }
}
echo "<center>";
echo "<a href=\"".$_SERVER['PHP_SELF'].
     "?bRegenerate=true\">Regenerate all screenshots ! ".
      "(use only if you know what you are doing)</a><br>";
echo "</center>";

/* display a range of 10 pages */
$pageRange = 10;

$ItemsPerPage = isset($aClean['iItemsPerPage']) ? $aClean['iItemsPerPage'] : 6;
$currentPage = isset($aClean['iPage']) ? $aClean['iPage'] : 1;

$ItemsPerPage = min($ItemsPerPage,100);
$realTotalPages = screenshot::objectGetEntriesCount('accepted');
$totalPages = 1;
if($realTotalPages >= $ItemsPerPage)
   $totalPages = ceil($realTotalPages/$ItemsPerPage);
$currentPage = min($currentPage,$totalPages);
$offset = (($currentPage-1) * $ItemsPerPage);


/* display page selection links */
echo "<center>";
echo "<b>Page $currentPage of $totalPages</b><br>";
display_page_range($currentPage, $pageRange, $totalPages,
                  $_SERVER['PHP_SELF']."?iItemsPerPage=".$ItemsPerPage);
echo "<br>";
echo "<br>";

/* display the option to choose how many screenshots per-page to display */
echo '<form method="get" name="message" action="'.$_SERVER['PHP_SELF'].'">';
echo '<b>Number of Screenshots per page:</b>';
echo "&nbsp;<select name='iItemsPerPage'>";

$ItemsPerPageArray = array(6, 9, 12, 15, 18, 21, 24);
foreach($ItemsPerPageArray as $i => $value)
{
    if($ItemsPerPageArray[$i] == $ItemsPerPage)
        echo "<option value='$ItemsPerPageArray[$i]' SELECTED>$ItemsPerPageArray[$i]";
    else
        echo "<option value='$ItemsPerPageArray[$i]'>$ItemsPerPageArray[$i]";
}
echo "</select>";

echo "<input type=hidden name=page value=$currentPage>";
echo "&nbsp;<input type=submit value='Refresh'>";
echo "</form>";

echo "</center>";

/* query for all of the Screenshots in assending order */
$Ids = query_parameters("SELECT * from appData 
                    WHERE type = 'screenshot'
                    AND state = 'accepted'
                    ORDER BY id ASC LIMIT ?, ?", $offset, $ItemsPerPage);
$c = 1;
echo "<div align=center><table><tr>\n";
while ($oRow = query_fetch_object($Ids))
{
    // display thumbnail
    $oVersion = new Version($oRow->versionId);
    $oApp = new Application($oVersion->iAppId);
    $oScreenshot = new Screenshot($oRow->id);
    $img = $oScreenshot->get_thumbnail_img();
    echo "<td align=center>\n";
    echo $img;
    echo "<div align=center>". substr($oRow->description,0,20). "\n";

    echo "<br>[".$oApp->objectMakeLink()."]";    

    echo "<br>[".$oVersion->objectMakeLink()."]";
    
    //show admin delete link
    if($_SESSION['current']->isLoggedIn() && 
      ($_SESSION['current']->hasPriv("admin") || 
       $_SESSION['current']->isMaintainer($aClean['iVersionId'])))
    {
        echo "<br>[<a href='".$_SERVER['PHP_SELF'];
        echo "?sCmd=delete&amp;iImageId=$oRow->id";
        echo "&amp;iPage=".$currentPage."&amp;iItemsPerPage=".$ItemsPerPage."'>";
        echo "Delete Image</a>]";
    }
    echo "</div></td>\n";
   // end row if counter of 3
   if ($c % 3 == 0) echo "</tr><tr>\n";
   $c++;

}
echo "</tr></table></div><br>\n";

/* display page selection links */
echo "<center>";
display_page_range($currentPage, $pageRange, $totalPages,
                   $_SERVER['PHP_SELF']."?iItemsPerPage=".$ItemsPerPage);
echo "</center>";

apidb_footer();

?>
