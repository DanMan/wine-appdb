<?php
/*******************************/
/* code to submit a maintainer */
/*******************************/

/*
 * application environment
 */ 
include("path.php");
require(BASE."include/incl.php");
require(BASE."include/category.php");
require(BASE."include/application.php");

$aClean = array(); //array of filtered user input

$aClean['maintainReason'] = makeSafe($_REQUEST['maintainReason']);
$aClean['appId'] = makeSafe($_POST['appId']);
$aClean['versionId'] = makeSafe(strip_tags($_POST['versionId']));
$aClean['superMaintainer'] = makeSafe($_POST['superMaintainer']);


/**
 * Check the input of a submitted form. And output with a list
 * of errors. (<ul></ul>)
 */
function checkAppMaintainerInput( $maintainReason )
{
    $errors = "";

    if ( empty( $maintainReason ) )
    {
        $errors .= "<li>Please enter why you would like to be an application maintainer.</li>\n";
    }

    if ( empty($errors) )
    {
        return "";
    }
    else
    {
        return $errors;
    }
}


if(!$_SESSION['current']->isLoggedIn())
{
    errorpage("You need to be logged in to apply to be a maintainer.");
    exit;
}


/* if we have a versionId to check against see if */
/* the user is already a maintainer */
if(!$aClean['superMaintainer'] && $_SESSION['current']->isMaintainer($aClean['versionId']))
{
    echo "You are already a maintainer of this app!";
    exit;
}

/* if this user is a super maintainer they maintain all of the versionIds of this appId */
if($_SESSION['current']->isSuperMaintainer($aClean['appId']))
{
    echo "You are already a supermaintainer of the whole application family!";
    exit;
}

if( $aClean['maintainReason'] )
{
    // check the input for empty/invalid fields
    $errors = checkAppMaintainerInput($aClean['maintainReason']);
    if(!empty($errors))
    {
        errorpage("We found the following errors:","<ul>$errors</ul><br />Please go back and correct them.");
        exit;
    }

    // header
    if($aClean['superMaintainer'])
        apidb_header("Submit SuperMaintainer Request");    
    else
        apidb_header("Submit Maintainer Request");    

    // add to queue
    $query = "INSERT INTO appMaintainerQueue VALUES (null, '".
            $aClean['appId']."', '".
            $aClean['versionId']."', '".
            addslashes($_SESSION['current']->iUserId)."', '".
            $aClean['maintainReason']."', '".
            $aClean['superMaintainer']."',".
            "NOW()".");";

    if (query_appdb($query))
    {
        echo "<p>Your maintainer request has been submitted for review. You should hear back\n";
        echo "soon about the status of your submission</p>\n";
    }
} else
{
    // header
    if($aClean['versionId'])
    {
        $oVersion = new Version($aClean['versionId']);
        $oApp = new Application($oVersion->iAppId);
        apidb_header("Request to become an application maintainer of ".$oApp->sName." ".$oVersion->sName);
    }
    else
    {
        $oApp = new Application($aClean['appId']);
        apidb_header("Request to become an application super maintainer of ".$oApp->sName);
    }

    // show add to queue form
	
    echo '<form name="newApp" action="maintainersubmit.php" method="post" enctype="multipart/form-data">',"\n";

    echo "<p>This page is for submitting a request to become an application maintainer.\n";
    echo "An application maintainer is someone who runs the application \n";
    echo "regularly and who is willing to be active in reporting regressions with newer \n";
    echo "versions of Wine and to help other users run this application under Wine.</p>";
    echo "<p>Being an application maintainer comes with new rights and new responsibilities; please be sure to read the <a href=\"".BASE."/help/?topic=maintainer_guidelines\">maintainer's guidelines</a> before to proceed.</p> ";
    echo "<p>We ask that all maintainers explain why they want to be an application maintainer,\n";
    echo "why they think they will do a good job and a little about their experience\n";
    echo "with Wine.  This is both to give you time to\n";
    echo "think about whether you really want to be an application maintainer and also for the\n";
    echo "appdb admins to identify people that are best suited for the job.  Your request\n";
    echo "may be denied if there are already a handful of maintainers for this application or if you\n";
    echo "don't have the experience with Wine that is necessary to help other users out.</p>\n";

    /* Special message for super maintainer applications */
    if($aClean['superMaintainer'])
    {
        echo "<p>Super maintainers are just like normal maintainers but they can modify EVERY version of\n";
        echo "this application (and the application itself).  We don't expect you to run every version but at least to help keep\n";
        echo "the forums clean of stale and out-of-date information.</p>\n";
    }
    echo "<br /><br />";

    if($aClean['superMaintainer'])
        echo html_frame_start("New Super Maintainer Form",400,"",0);
    else
        echo html_frame_start("New Maintainer Form",400,"",0);

    echo "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";
    echo "<tr valign=top><td class=color0>";
    echo '<b>Application</b></td><td>'.$oApp->sName;
    echo '</td></tr>',"\n";
    if($aClean['versionId'])
    {
        echo "<tr valign=top><td class=color0>";
        echo '<b>Version</b></td><td>'.$oVersion->sName;
        echo '</td></tr>',"\n";
    }
    echo "<input type=hidden name='appId' value={$aClean['appId']}>";
    echo "<input type=hidden name='versionId' value={$aClean['versionId']}>";
    echo "<input type=hidden name='superMaintainer' value={$aClean['superMaintainer>']}";

    if($aClean['superMaintainer'])
        echo '<tr valign=top><td class=color0><b>Why you want to and should be an application super maintainer</b></td><td><textarea name="maintainReason" rows=15 cols=70></textarea></td></tr>',"\n";
    else
        echo '<tr valign=top><td class=color0><b>Why you want to and should be an application maintainer</b></td><td><textarea name="maintainReason" rows=15 cols=70></textarea></td></tr>',"\n";

    echo '<tr valign=top><td class=color3 align=center colspan=2> <input type=submit value=" Submit Maintainer Request " class=button> </td></tr>',"\n";
    echo '</table>',"\n";

    echo html_frame_end();

	echo "</form>";
}

apidb_footer();

?>
