<?php

/**
 * A list of the user's queued items
 */

require_once("path.php");
require_once(BASE."include/incl.php");
require_once(BASE."include/appData.php");

$oUser = new User($_SESSION['current']->iUserId);

if(!$oUser->isLoggedIn())
{
    login_form();
    exit;
}

apidb_header("View Your Queued Items");

/* Test data */
if($sResult = testData::listSubmittedBy($oUser->iUserId))
{
    echo html_frame_start("Your Queued Test Results","90%");
    echo $sResult;
    echo html_frame_end("&nbsp;");
}
else
    echo "You have no queued test results.<br><br>\n";

/* Applications */
if($sResult = Application::listSubmittedBy($oUser->iUserId))
{
    echo html_frame_start("Your Queued Applications","90%");
    echo $sResult;
    echo html_frame_end("&nbsp;");
} else
    echo "You have no queued applications.<br><br>\n";

/* Versions */
if($sResult = Version::listSubmittedBy($oUser->iUserId))
{
    echo html_frame_start("Your Queued Versions","90%");
    echo $sResult;
    echo html_frame_end("&nbsp;");
} else
    echo "You have no queued versions.<br><br>\n";

/* Bug links */
if($sResult = bug::listSubmittedBy($oUser->iUserId))
{
    echo html_frame_start("Your Queued Bug Links","90%");
    echo $sResult;
    echo html_frame_end("&nbsp;");
} else
    echo "You have no queued bugs.<br><br>\n";

/* Application data */
if($sResult = appData::listSubmittedBy($oUser->iUserId))
{
    echo html_frame_start("Your Queued Application Data","90%");
    echo $sResult;
    echo html_frame_end("&nbsp;");
} else
    echo "You have no queued application data.<br><br>\n";

apidb_footer();


