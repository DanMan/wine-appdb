<?php
/**************************************************/
/* code to submit, view and resubmit Test Results */
/**************************************************/
 
require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/tableve.php");
require_once(BASE."include/application.php");
require_once(BASE."include/testData.php");
require_once(BASE."include/distribution.php");

//deny access if not logged on
if(!$_SESSION['current']->isLoggedIn())
    util_show_error_page_and_exit("Insufficient privileges to create test results.  Are you sure you are logged in?");


if ($aClean['sSub'])
{
    $oTest = new testData($aClean['iTestingId']);
    if($aClean['iVersionId'])
        $oTest->iVersionId = $aClean['iVersionId'];
    $errors = "";

    // Submit or Resubmit the new test results
    if (($aClean['sSub'] == 'Submit') || ($aClean['sSub'] == 'Resubmit'))
    {
        $errors = $oTest->CheckOutputEditorInput($aClean);
        $oTest->GetOutputEditorValues($aClean); // retrieve the values from the current $aClean
        if(empty($errors))
        {
            if(!$aClean['iDistributionId'])
            {
                if(!empty($aClean['sDistribution']) )
                {
                    $oDistribution = new distribution();
                    $oDistribution->sName = $aClean['sDistribution'];
                    $oDistribution->create();
                    $oTest->iDistributionId = $oDistribution->iDistributionId;
                }
            }
            if($aClean['sSub'] == 'Submit')
            {
                $oTest->create();
            } else if($aClean['sSub'] == 'Resubmit')
            {
                $oTest->update(true);
                $oTest->ReQueue();
            }
            util_redirect_and_exit($_SERVER['PHP_SELF']);
        } else 
        {
            $aClean['sSub'] = 'view';
        }
    }

    // Delete test results
    if ($aClean['sSub'] == 'Delete')
    {
        if($aClean['iTestingId'])
        {
            $oTest = new testData($aClean['iTestingId']);
            $oTest->delete();
        }

        util_redirect_and_exit($_SERVER['PHP_SELF']);
    }

    // is this an old test?
    if($aClean['iTestingId'])
    {
        // make sure the user has permission to view this test result
        $oVersion = new Version($oTest->iVersionId);
        if(!$_SESSION['current']->hasPriv("admin") && 
           !$_SESSION['current']->hasAppVersionModifyPermission($oVersion)&&
           !(($_SESSION['current']->iUserId == $oTest->iSubmitterId) && !($oTest->sQueued == 'false')))
        {
            util_show_error_page_and_exit("Insufficient privileges.");
        } else
        {
            $oVersion = new Version($oTest->iVersionId);
        }
    } else
    { 
        $oTest->iVersionId = $aClean['iVersionId'];
        $oVersion = new Version($aClean['iVersionId']);
        $oTest->sQueued = "new";
    }
    if ($aClean['sSub'] == 'view')
    {
        $sVersionInfo = version::fullName($oVersion->iVersionId);

        switch($oTest->sQueued)
        {
        case "new":
            apidb_header("Submit new test results for ".$sVersionInfo);
            $oTest->sTestedDate = date('Y-m-d H:i:s');
            break;
        case "true":
            apidb_header("Edit new test results for ".$sVersionInfo);
            break;
        case "rejected":
            apidb_header("Resubmit test results for ".$sVersionInfo);
            break;
        case "False":
            apidb_header("Edit test results for ".$sVersionInfo);
            break;
        default:
            util_show_error_page_and_exit('$oTest->sQueued of \''.$oTest->sQueued."'is invalid");
            break;
        }
        echo '<form name="sQform" action="'.$_SERVER['PHP_SELF'].'" method="post" enctype="multipart/form-data">',"\n";


        //help
        $oTest->objectDisplayAddItemHelp();

        if(!empty($errors))
        {
            echo '<font color="red">',"\n";
            echo '<p class="red"> We found the following errors:</p><ul>'.$errors.'</ul>Please correct them.';
            echo '</font><br />',"\n";
            echo '<p></p>',"\n";
        }
   
        // View Test Details
        $oTest->outputEditor($aClean['sDistribution'],true);

        echo '<a href="'.$oVersion->objectMakeUrl().'">Back to Version</a>';

        echo '<tr valign=top><td class=color3 align=center colspan=2>',"\n";


        // Submit Buttons
        switch($oTest->sQueued)
        {
        case "new":
            echo '<input name="sSub" type="submit" value="Submit" class="button" >&nbsp',"\n";
            break;
        case "true":
        case "rejected":
        case "False":
             echo '<input name="sSub" type="submit" value="Resubmit" class="button" >&nbsp',"\n";
             echo '<input name="sSub" type="submit" value="Delete" class="button" >',"\n";
             break;
        }
        echo '</td></tr>',"\n";    
        echo "</form>";

        echo html_frame_end("&nbsp;");
    }
    else 
    {
        // error no sub!
        addmsg("Internal Routine Not Found!!", "red");
        util_redirect_and_exit($_SERVER['PHP_SELF']);
    } 
} 
else // if ($aClean['sSub']) is not defined, display the Testing results queue page 
{
    util_show_error_page_and_exit("No test id defined!");
}
apidb_footer();
?>
