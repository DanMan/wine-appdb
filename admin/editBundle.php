<?php

require("path.php");
require(BASE."include/"."incl.php");

if(!$_SESSION['current']->hasPriv("admin"))
    util_show_error_page_and_exit();

function build_app_list()
{
    $hResult = application::objectGetEntries('accepted');
    
    echo "<select name=iAppId size=5'>\n";
    while($oRow = query_fetch_object($hResult))
    {
        echo "<option value=$oRow->appId>$oRow->appName</option>\n";
    }
    echo "</select>\n";
}

if($aClean['sCmd'])
{
    if($aClean['sCmd'] == "delete")
    {
        $hResult = query_parameters("DELETE FROM appBundle WHERE appId ='?' AND bundleId = '?'",
                                    $aClean['iAppId'], $aClean['iBundleId']);
        if($hResult)
            addmsg("App deleted from bundle", "green");
        else
            addmsg("Failed to delete app from bundle!", "red");
    }
    if($aClean['sCmd'] == "add")
    {
        $oApp = new application($aClean['iAppId']);
        /* Check that the app hasn't already been added */
        $hResult = query_parameters("SELECT * FROM appBundle WHERE bundleId = '?' AND appId = '?'", $aClean['iBundleId'], $aClean['iAppId']);
        if($hResult && query_num_rows($hResult) == 0)
        {
            $hResult = query_parameters("INSERT INTO appBundle (bundleId, appId) VALUES".
                                        "('?', '?')",
                                        $aClean['iBundleId'],
                                        $aClean['iAppId']);
            if($hResult)
                addmsg("{$oApp->sName} added to bundle {$aClean['iBundleId']}", 'green');
        } else
        {
            addmsg("{$oApp->sName} is already in the bundle", 'red');
        }
    }
}


apidb_header("Edit Application Bundle");

$hResult = query_parameters("SELECT bundleId, appBundle.appId, appName FROM appBundle, appFamily ".
                            "WHERE bundleId = '?' AND appFamily.appId = appBundle.appId
                            AND appFamily.state != 'deleted'",
                            $aClean['iBundleId']);

echo html_frame_start("Apps in this Bundle","300",'',0);
echo "<table width='100%' border=0 cellpadding=3 cellspacing=0>\n\n";
	    
echo "<tr class=color4>\n";
echo "    <td><font color=white> Application Name </font></td>\n";
echo "    <td><font color=white> Delete </font></td>\n";
echo "</tr>\n\n";	    

if($hResult && query_num_rows($hResult))
{
    $c = 1;
    while($oRow = query_fetch_object($hResult))
    {
        //set row color
        if ($c % 2 == 1) { $bgcolor = 'color0'; } else { $bgcolor = 'color1'; }
		    
        $delete_link = "[<a href='editBundle.php?sCmd=delete&amp;iBundleId=".$aClean['iBundleId']."&amp;iAppId=$oRow->appId'>delete</a>]";

        echo "<tr class=$bgcolor>\n";
        echo "    <td>$oRow->appName &nbsp;</td>\n";
        echo "    <td>$delete_link &nbsp;</td>\n";
        echo "</tr>\n\n";
		    
        $c++;
    }
} else if($hResult && !query_num_rows($hResult))
{
    /* indicate to the user that there are no apps in this bundle at the moment */
    echo "<tr>\n";
    echo " <td colspan=2 align=center><b>No applications in this bundle</b></td>\n";
    echo "</tr>\n";
}

echo "</table>\n\n";
echo html_frame_end();

echo "<form method=post action=editBundle.php>\n";

echo html_frame_start("Application list","",'',2);
build_app_list();
echo html_frame_end();
    
echo "<input type=\"hidden\" name=\"iBundleId\"  value=\"".$aClean['iBundleId']."\">\n";
echo "<input type=\"hidden\" name=\"sCmd\" value=\"add\">\n";
echo "<input type=\"submit\" value=\"Add\" />\n";
echo "</form>\n";
    
apidb_footer();


