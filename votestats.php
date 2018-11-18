<?php
/**
 * Voting statistics.
 *
 * Optional parameters:
 *  - iTopNumber, the number of applications to be displayed
 *  - iCategoryId, the category identifier of the category whose stats we want to see
 */

// application environment
require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/category.php");

// set default values and check if the value makes sense
if(empty($aClean['iTopNumber']) || $aClean['iTopNumber'] > 200 || $aClean['iTopNumber'] < 0)
    $aClean['iTopNumber'] = 25;
if(empty($aClean['iCategoryId']))
    $aClean['iCategoryId'] = 0;

apidb_header("Vote Stats - Top ".$aClean['iTopNumber']." Applications");

echo "<h1 class=\"whq-app-title\">Vote Stats - Top ".$aClean['iTopNumber']." Applications</h1>\n";

/* display the selection for the top number of apps to view */
echo "<form method=\"post\" name=\"sMessage\" action=\"".$_SERVER['PHP_SELF']."\">";
echo "<b>Number of top apps to display:</b>";
echo "<select name='iTopNumber' class=\"form-control form-control-inline\">";
$topNumberArray = array(25, 50, 100, 200);

foreach ($topNumberArray as $i => $value)
{
    if($topNumberArray[$i] == $aClean['iTopNumber'])
        echo "<option value='$topNumberArray[$i]' SELECTED>$topNumberArray[$i]";
    else
        echo "<option value='$topNumberArray[$i]'>$topNumberArray[$i]";
}
echo "</select>";

/**
 * build an array of categories from the current category back up 
 * the tree to the main category 
 */
$cat_array = Array();
$cat_name_array = Array();

if(!empty($aClean['iCategoryId']))
{
    $currentCatId = $aClean['iCategoryId'];

    do
    {
        $sCatQuery = "SELECT appCategory.catName, appCategory.catParent ".
            "FROM appCategory WHERE appCategory.catId = '?'";
        $hResult = query_parameters($sCatQuery, $currentCatId);

        if($hResult)
        {
            $oRow = query_fetch_object($hResult);
            $catParent = $oRow->catParent;

            array_push($cat_array, "$currentCatId");
            array_push($cat_name_array, "$oRow->catName");
        }

        $currentCatId = $catParent;
    } while($currentCatId != 0);
}

/*******************************************************************/
/* add options for all of the categories that we are recursed into */
echo "<br>\n";
echo "<b>Section:</b>";
echo '<select name="iCategoryId" class="form-control form-control-inline">';

if(empty($aClean['iCategoryId']))
    echo '<option value="0" SELECTED>Any</option>';
else
    echo '<option value="0">Any</option>';

$indent = 1;

/* reverse the arrays because we need the entries in the opposite order */
$cat_array_reversed = array_reverse($cat_array);
$cat_name_array_reversed = array_reverse($cat_name_array);
foreach ($cat_array_reversed as $i => $value)
{
    /* if this is the current category, select this option */
    if($aClean['iCategoryId'] == $cat_array_reversed[$i])
        echo "<option value='$cat_array_reversed[$i]' SELECTED>";
    else
        echo "<option value='$cat_array_reversed[$i]'>";

    echo str_repeat("-", $indent);
    echo stripslashes($cat_name_array_reversed[$i]);
    echo "</option>";
    $indent++;
}


// add to the list all of the sub sections of the current section
$cat = new Category($aClean['iCategoryId']);
$subs = $cat->aSubcatsIds;

if($subs)
{
    while(list($i, $id) = each($subs))
    {
        $oSubcat = new Category($id);
        /* if this is the current category, select this option */
        if($id == $aClean['iCategoryId'])
            echo "<option value=$id SELECTED>";
        else
            echo "<option value=$id>";

        echo str_repeat("-", $indent);
        echo stripslashes($oSubcat->sName);
    }
}
echo "</select>\n";
echo "<br>\n";
echo '<button type="submit" class="btn btn-default"><i class="fa fa-refresh"></i> Refresh</button>';
echo '</form>';

/***************************************************/
/* build a list of the apps in the chosen category */
if(empty($aClean['iCategoryId']))
{
    /* leave out the appFamily.catId = '$aClean['iCategoryId']' */
    $hResult = query_parameters("SELECT appVotes.versionId, versionName, appName,
                       count(userId) as count
                           FROM appVotes, appFamily, appVersion
                           WHERE appVotes.versionId = appVersion.versionId AND
                           appFamily.appId = appVersion.appId AND
                           appVersion.state = 'accepted'
                           GROUP BY appVotes.versionId ORDER BY count DESC LIMIT ?", 
                               $aClean['iTopNumber']);
} else
{
    /* Display all application for a given category (including sub categories)
    SELECT f.appId, f.appName
    FROM appFamily AS f, appCategory AS c
    WHERE f.catId = c.catId
    AND (
    c.catId =29
    OR c.catParent =29)*/

    $hResult = query_parameters("SELECT v.versionId, appVersion.versionName,
              f.appName, count( v.versionId ) AS count
                  FROM appFamily AS f, appCategory AS c, appVotes AS v, appVersion
                  WHERE appVersion.appId = f.appId
                  AND appVersion.versionId = v.versionId
                  AND appVersion.state = 'accepted'
                  AND f.catId = c.catId
                  AND (
                        c.catId = '?'
                        OR c.catParent = '?'
                      )
                  GROUP BY v.versionId
                  ORDER BY count DESC LIMIT ?",
              $aClean['iCategoryId'], $aClean['iCategoryId'], $aClean['iTopNumber']);
}

if($hResult)
{
    echo html_frame_start();
    
    $oTable = new Table();
    $oTable->SetClass("whq-table");

    $oTableRow = new TableRow();
    $oTableRow->AddTextCell("Application Name");
    $oTableRow->AddTextCell("Votes");
    $oTable->SetHeader($oTableRow);

    $c = 1;
    while($oRow = query_fetch_object($hResult))
    {
        $shLink = version::fullNameLink($oRow->versionId);
        $oVersion = new Version($oRow->versionId);
        $oTableRowClick = new TableRowClick($oVersion->objectMakeUrl());
        $oTableRow = new TableRow();
        $oTableRow->SetRowClick($oTableRowClick);
        $oTableCell = new TableCell("$c.".$shLink);
        $oTableCell->SetWidth("90%");
        $oTableRow->AddCell($oTableCell);
        $oTableRow->AddTextCell($oRow->count);
        $oTable->AddRow($oTableRow);
        $c++;
    }

    // output the table
    echo $oTable->GetString();

    echo html_frame_end();

    /* Make sure we tell the user here are no apps, otherwise they might */
    /* think that something went wrong with the server */
    if($c == 1)
    {
        echo '<h2>No apps found in this category, please vote for your favorite apps!</h2>';
    }

    echo '<p><a href="https://wiki.winehq.org/AppDB_Voting_Help">What does this screen mean?</a></p>';
}

apidb_footer();

