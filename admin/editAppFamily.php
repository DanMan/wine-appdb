<?php
/**********************************/
/* Edit application family        */
/**********************************/

require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/tableve.php");
require_once(BASE."include/application.php");
require_once(BASE."include/category.php");

if(!is_numeric($aClean['iAppId']))
    util_show_error_page_and_exit("Wrong ID");

if(!($_SESSION['current']->hasPriv("admin") || $_SESSION['current']->isSuperMaintainer($aClean['iAppId'])))
    util_show_error_page_and_exit("Insufficient Privileges!");

if(!empty($aClean['sSubmit']))
{
    process_app_version_changes(false);
    url::processForm($aClean);
    util_redirect_and_exit(apidb_fullurl("appview.php?iAppId={$aClean['iAppId']}"));
}
else
// Show the form for editing the Application Family 
{
    $family = new TableVE("edit");


    $oApp = new Application($aClean['iAppId']);
    
    if(!$oApp)
    {
        util_show_error_page_and_exit('Application does not exist');
    }
    
    if($_SESSION['current']->showDebuggingInfos()) { echo "<p align=center><b>appName:</b> $oApp->sName </p>"; }

     apidb_header("Edit Application Family");

    echo "<form method=\"post\" action=\"editAppFamily.php\">\n";

    $oApp->outputEditor("");

    echo '<table border=0 cellpadding=6 cellspacing=0 width="100%">', "\n";
    echo '<tr><td colspan=2 align=center><input type="submit" name=sSubmit value="Update Database"></td></tr>',"\n";
    echo '</table>', "\n";
    echo "</form>";

    echo "<p>";

    // URL editor
    echo url::outputEditor("editAppFamily.php", NULL, $oApp);

    echo html_back_link(1,BASE."appview.php?iAppId=$oApp->iAppId");
}

apidb_footer();
?>
