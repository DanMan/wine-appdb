<?php
/**
 * Previously used to display an application or a version.
 * It still does that, but only for compatibility with old links
 *
 * Mandatory parameters:
 *  - iAppId, application identifier
 *               OR
 *  - iVersionId, version identifier
 *
*/

// Basic environment
require("path.php");
require(BASE."include/incl.php");

if(isset($aClean['iAppId']) || isset($aClean['iVersionId'])) // Valid args
{
    if( isset($aClean['iAppId']) ) // Application
        $oObject = new Application($aClean['iAppId']);
    else // We want to see a particular version.
        $oObject = new Version($aClean['iVersionId']);

    // header
    apidb_header($oObject->objectGetCustomTitle("view"));

    if(isset($aClean['iVersionId']))
        $oObject->display($aClean);
    else
        $oObject->display();

    apidb_footer();
} else
{
    // Oops! Called with no params, bad llamah!
    util_show_error_page_and_exit('Page Called with No Params!');
}


