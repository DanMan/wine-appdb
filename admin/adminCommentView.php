<?php
/************************************************************/
/* Page for managing all of the comments in the apidb       */
/* Without having go into each application version to do so */
/************************************************************/

require("path.php");
require(BASE."include/incl.php");

apidb_header("Comments");

/* display a range of 10 pages */
$pageRange = 10;

$ItemsPerPage = isset($aClean['iItemsPerPage']) ? $aClean['iItemsPerPage'] : 10;
$currentPage = isset($aClean['iPage']) ? $aClean['iPage'] : 1;

$totalPages = ceil(getNumberOfComments()/$ItemsPerPage);

if($ItemsPerPage > 100) $ItemsPerPage = 100;


/* display page selection links */
echo "<center>";
echo "<b>Page $currentPage of $totalPages</b><br>";
display_page_range($currentPage, $pageRange, $totalPages, $_SERVER['PHP_SELF']."?iItemsPerPage=".$ItemsPerPage);
echo "<br>";
echo "<br>";

/* display the option to choose how many comments per-page to display */
echo "<form method=\"get\" name=\"sMessage\" action=\"".$_SERVER['PHP_SELF']."\">";
echo "<b>Number of comments per page:</b>";
echo "&nbsp;<select name='iItemsPerPage'>";

$ItemsPerPageArray = array(10, 20, 50, 100, 500);
foreach($ItemsPerPageArray as $i => $value)
{
    if($ItemsPerPageArray[$i] == $ItemsPerPage)
        echo "<option value='$ItemsPerPageArray[$i]' SELECTED>$ItemsPerPageArray[$i]";
    else
        echo "<option value='$ItemsPerPageArray[$i]'>$ItemsPerPageArray[$i]";
}
echo "</select>";

echo "<input type=hidden name=iPage value=$currentPage>";
echo "&nbsp;<input type=submit value='Refresh'>";
echo "</form>";

echo "</center>";

/* query for all of the commentId's, ordering by their time in reverse order */
$offset = (($currentPage-1) * $ItemsPerPage);
$commentIds = query_parameters("SELECT commentId from appComments ORDER BY ".
                           "appComments.time ASC LIMIT ?, ?", $offset, $ItemsPerPage);
while ($oRow = query_fetch_object($commentIds))
{
    $sQuery = "SELECT from_unixtime(unix_timestamp(time), \"%W %M %D %Y, %k:%i\") as time, ".
        "commentId, parentId, versionId, userid, subject, body ".
        "FROM appComments WHERE commentId = '?'";
    $hResult = query_parameters($sQuery, $oRow->commentId);
    /* call view_app_comment to display the comment */
    $oComment_row = query_fetch_object($hResult);
    Comment::view_app_comment($oComment_row, true);
}

/* display page selection links */

echo "<center>";
display_page_range($currentPage, $pageRange, $totalPages, $_SERVER['PHP_SELF']."?iItemsPerPage=".$ItemsPerPage);
echo "</center>";

apidb_footer();
?>
