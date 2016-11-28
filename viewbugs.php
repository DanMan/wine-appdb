<?php
/**
 * Shows all versions that have the same bug link.
 *
 * Mandatory parameters:
 *  - iBugId, bug identifier
 */

// application environment
require("path.php");
require(BASE."include/incl.php");

apidb_header("Applications Affected by Bug #{$aClean['iBugId']}");

echo '<h1 class="whq-app-title">Applications Affected by Bug #'.$aClean['iBugId'].'</h1>',"\n";

echo '<form method=post action="viewbugs.php?iBugId='.$aClean['iBugId'].'">',"\n";
echo '<input type="text" name="iBugId" value="'.$aClean['iBugId'].'" class="form-control form-control-inline" size="10">',"\n";
echo '<button type="submit" class="btn btn-default">Search</button>',"\n";
echo '</form>',"\n";

$hResult = query_parameters("SELECT appFamily.description as appDescription,
                       appFamily.appName as appName,
                       appVersion.*, buglinks.versionId as versionId
                       FROM appFamily, appVersion, buglinks
                       WHERE appFamily.appId = appVersion.appId 
                       and buglinks.versionId = appVersion.versionId
                       AND buglinks.bug_id = '?'
                       ORDER BY versionName", $aClean['iBugId']);

if(query_num_rows($hResult))
{
    echo '<table class="whq-table whq-table-full">',"\n";
    echo '<thead><tr>',"\n";
    echo '    <td width="30%">Application Name</td>',"\n";
    echo '    <td width="50%">Description</td>',"\n";
    echo '    <td>version</td>',"\n";
    echo '    <td>Downloads</td>',"\n";
    echo '</tr></thead><tbody>',"\n";
    while($oRow = query_fetch_object($hResult))
    {
        $oApp = new application($oRow->appId);
        $oVersion = new version($oRow->versionId);
        $sDownloadUrls = "";
        if($hDownloadUrls = appData::getData($oRow->versionId, "downloadurl"))
        {
            while($oDownloadUrl = query_fetch_object($hDownloadUrls))
                $sDownloadUrls .= "<a href=\"$oDownloadUrl->url\">".
                        "$oDownloadUrl->description</a><br>";
        }

        echo '<tr>',"\n";
        echo '    <td>',"\n";
        echo "    ".$oApp->objectMakeLink()."\n";
        echo '    </td>',"\n";
        echo '    <td>'.$oRow->appDescription.'</td>',"\n";
        echo '    <td>',"\n";
        echo "    ".$oVersion->objectMakeLink()."\n";
        echo '    </td>',"\n";
        echo "    <td>$sDownloadUrls</td>\n";
        echo '</tr>',"\n";
    }
    echo '</tbody></table>',"\n";
}
else
{
    echo html_note("No Applications Found for Bug #{$aClean['iBugId']}","","","warning");
}

apidb_footer();

?>
