<?php
require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/tableve.php");
require_once(BASE."include/application.php");
require_once(BASE."include/version.php");

if(!is_numeric($aClean['iAppId']) OR !is_numeric($aClean['iVersionId']))
    util_show_error_page_and_exit("Wrong ID");

/* Check for admin privs */
if(!$_SESSION['current']->hasPriv("admin") && !$_SESSION['current']->isMaintainer($aClean['iVersionId']) && !$_SESSION['current']->isSuperMaintainer($aClean['iAppId']))
    util_show_error_page_and_exit("Insufficient Privileges!");

/* process the changes the user entered into the web form */
if(!empty($aClean['sSubmit']))
{
    process_app_version_changes(true);
    downloadurl::processForm($aClean);
    url::processForm($aClean);
    $oVersion = new version($aClean['iVersionId']);
    util_redirect_and_exit($oVersion->objectMakeUrl());
} else /* or display the webform for making changes */
{

    $oVersion = new Version($aClean['iVersionId']);

    apidb_header("Edit Application Version");

    echo "<div class='default_container'>\n";

    echo "<form method=post action='editAppVersion.php'>\n";

    if($_SESSION['current']->hasPriv("admin"))
        $oVersion->outputEditor(true, true); /* false = not allowing the user to modify the parent application */
    else
        $oVersion->outputEditor(false, true); /* false = not allowing the user to modify the parent application */
        
    echo '<table border=0 cellpadding=2 cellspacing=0 width="100%">',"\n";
    echo '<tr><td colspan=2 align=center class=color2><input type="submit" name="sSubmit" value="Update Database"></td></tr>',"\n";
    echo html_table_end();

    echo "</form>";

    echo "<br><br>\n";

    /* URL editor */
    echo url::outputEditor("editAppVersion.php", $oVersion);

    /* Display some text about download url usage */
    echo "A place where this version can be downloaded for free.  Other downloads, ";
    echo "such as updates, should be added in the regular URL form\n";

    /* Download URL editor */
    echo downloadurl::outputEditor($oVersion, "editAppVersion.php");

    echo html_back_link(1,$oVersion->objectMakeUrl());

    echo "</div>\n";

    apidb_footer();
}

