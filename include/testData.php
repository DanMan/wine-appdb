<?php
/*****************************************/
/* this class represents Test results    */
/*****************************************/
require_once(BASE."include/distribution.php");
require_once(BASE."include/util.php");
// Class for handling Test History.

class testData{
    var $iTestingId;
    var $iVersionId;
    var $shWhatWorks;
    var $shWhatDoesnt;
    var $shWhatNotTested;
    var $sTestedRelease;
    var $iStaging;
    var $iDistributionId;
    var $sTestedDate;
    var $sInstalls;
    var $sRuns;
    var $sUsedWorkaround;
    var $shWorkarounds;
    var $sTestedRating;
    var $shComments;
    var $sSubmitTime;
    var $iSubmitterId;
    var $sGpuMfr;
    var $sGraphicsDriver;
    var $sHasProblems;
    private $sState;

     // constructor, fetches the data.
    function __construct($iTestingId = null, $oRow = null)
    {
        // we are working on an existing test
        if(!$iTestingId && !$oRow)
            return;

        // We fetch the data related to this test.
        if(!$oRow)
        {
            $sQuery = "SELECT *
                        FROM testResults
                        WHERE testingId = '?'";
            if($hResult = query_parameters($sQuery, $iTestingId))
                $oRow = query_fetch_object($hResult);
        }

        if($oRow)
        {
            $this->iTestingId = $oRow->testingId;
            $this->iVersionId = $oRow->versionId;
            $this->shWhatWorks = $oRow->whatWorks;
            $this->shWhatDoesnt = $oRow->whatDoesnt;
            $this->shWhatNotTested = $oRow->whatNotTested;
            $this->sTestedDate = $oRow->testedDate;
            $this->iDistributionId = $oRow->distributionId;
            $this->sTestedRelease = $oRow->testedRelease;
            $this->iStaging = $oRow->staging;
            $this->sInstalls = $oRow->installs;
            $this->sRuns = $oRow->runs;
            $this->sUsedWorkaround = $oRow->usedWorkaround;
            $this->shWorkarounds = $oRow->workarounds;
            $this->sTestedRating = $oRow->testedRating;
            $this->shComments = $oRow->comments;
            $this->sSubmitTime = $oRow->submitTime;
            $this->iSubmitterId = $oRow->submitterId;
            $this->sState = $oRow->state;
            $this->sGpuMfr = $oRow->gpuMfr;
            $this->sGraphicsDriver = $oRow->graphicsDriver;
            $this->sHasProblems = $oRow->hasProblems;
        }
    }

    // Creates a new Test Results.
    function create()
    {
        $oVersion = new version($this->iVersionId);
        if($oVersion->objectGetState() != 'accepted')
            $this->sState = 'pending';
        else
            $this->sState = $this->mustBeQueued() ? 'queued' : 'accepted';

        $hResult = query_parameters("INSERT INTO testResults (versionId, whatWorks, whatDoesnt,".
                                        "whatNotTested, testedDate, distributionId,".
                                        "testedRelease, staging, installs, runs,".
                                        "usedWorkaround, workarounds,".
                                        "testedRating, comments,".
                                        "submitTime, submitterId, state,".
                                        "gpuMfr, graphicsDriver, hasProblems)".
                                        "VALUES('?', '?', '?', '?', '?', '?', '?',".
                                        "'?', '?', '?', '?', '?',".
                                        "'?', '?', '?', '?', '?', '?', '?', '?')",
                                    $this->iVersionId, 
                                    $this->shWhatWorks,
                                    $this->shWhatDoesnt,
                                    $this->shWhatNotTested, 
                                    $this->sTestedDate,
                                    $this->iDistributionId,
                                    $this->sTestedRelease, 
                                    $this->iStaging, 
                                    $this->sInstalls,
                                    $this->sRuns,
                                    $this->sUsedWorkaround,
                                    $this->shWorkarounds,
                                    $this->sTestedRating, 
                                    $this->shComments,
                                    date("Y-m-d H:i:s"),
                                    $_SESSION['current']->iUserId,
                                    $this->sState,
                                    $this->sGpuMfr,
                                    $this->sGraphicsDriver,
                                    $this->sHasProblems);

        if($hResult)
        {
            $this->iTestingId = query_appdb_insert_id();
            testData::__construct($this->iTestingId);
            $this->SendNotificationMail();

            if($this->sState == 'accepted')
                $oVersion->updateRatingInfo();
            return true;
        }
        else
        {
            addmsg("Error while creating test results.", "red");
            return false;
        }
    }

    // Update Test Results.
    function update($bSilent=false)
    {
        // is the current user allowed to update this test result? 
        $oVersion = new Version($this->iVersionId);
        if(!$_SESSION['current']->hasPriv("admin") && 
           !$_SESSION['current']->hasAppVersionModifyPermission($oVersion) &&
           !(($_SESSION['current']->iUserId == $this->iSubmitterId) && $this->sState != 'accepted'))
        {
            return;
        }

        $oOldTest = new testData($this->iTestingId);
        /* Nothing changed */
        if($this == $oOldTest)
            return TRUE;

        /* Provide some feedback as to what was changed.  Not all fields are
           interesting */
        $sWhatChanged = "";
        if($this->shWhatWorks != $oOldTest->shWhatWorks)
        {
            $sWhatChanged .= "What works was changed from\n'$oOldTest->shWhatWorks'\n".
                    "to\n'$this->shWhatWorks'.\n";
        }

        if($this->shWhatDoesnt != $oOldTest->shWhatDoesnt)
        {
            $sWhatChanged .= "What does not work was changed from\n'"
                    .$oOldTest->shWhatDoesnt."'\n to\n'$this->shWhatDoesnt'.\n";
        }
        
        if($this->sUsedWorkaround != $oOldTest->sUsedWorkaround)
        {
            $sWhatChanged .= "Workarounds was changed from\n'"
                    .$oOldTest->sUsedWorkaround."'\n to\n'$this->sUsedWorkaround'.\n";
        }
        
        if($this->shWorkarounds != $oOldTest->shWorkarounds)
        {
            $sWhatChanged .= "Workarounds detail was changed from\n'"
                    .$oOldTest->shWorkarounds."'\n to\n'$this->shWorkarounds'.\n";
        }

        if($this->shWhatNotTested != $oOldTest->shWhatNotTested)
        {
            $sWhatChanged .= "What was not tested was changed from\n'".
                    $oOldTest->shWhatNotTested."'\nto\n'$this->shWhatNotTested'.\n";
        }

        if($this->shComments != $oOldTest->shComments)
        {
            $sWhatChanged .= "Extra comments was changed from\n'".
                    $oOldTest->shComments."'\nto\n'$this->shComments'.\n";
        }

        if($this->iDistributionId != $oOldTest->iDistributionId)
        {
            $oNewDist = new distribution($this->iDistributionId);
            $oOldDist = new distribution($oOldTest->iDistributionId);
            $sWhatChanged .= "Operating system was changed from $oOldDist->sName ".
                    "to $oNewDist->sName.\n";
            if(sizeof($oOldDist->aTestingIds)<=1)
            {
                $oOldDist->delete();
                addmsg("Rejected operating system has been deleted.", "red");
            }
        }

        if($this->sInstalls != $oOldTest->sInstalls)
        {
            $sWhatChanged .= "Installs? was changed from $oOldTest->sInstalls to ".
                    "$this->sInstalls.\n";
        }

        if($this->sRuns != $oOldTest->sRuns)
        {
            $sWhatChanged .= "Runs? was changed from $oOldTest->sRuns to ".
                    "$this->sRuns.\n";
        }

        $bUpdateRatingInfo = false;
        if($this->sTestedRating != $oOldTest->sTestedRating)
        {
            $bUpdateRatingInfo = true;
            $sWhatChanged .= "Rating was changed from $oOldTest->sTestedRating ".
                    "to $this->sTestedRating.\n";
        }

        if($this->sTestedRelease != $oOldTest->sTestedRelease)
        {
            $bUpdateRatingInfo = true;
            $sWhatChanged .= "Tested release was changed from ".
                    $oOldTest->sTestedRelease." to $this->sTestedRelease.\n";
        }
        
        if($this->iStaging != $oOldTest->iStaging)
        {
            $bUpdateRatingInfo = true;
            $sWhatChanged .= "Staging checkbox was changed from ".
                    $oOldTest->iStaging." to $this->iStaging.\n";
        }

        if($this->iVersionId != $oOldTest->iVersionId)
        {
            $sWhatChanged .= 'Moved from '.version::fullName($oOldTest->iVersionId).' to '.version::fullName($this->iVersionId)."\n";
            $oNewVersion = new version($this->iVersionId);
            if($oNewVersion->objectGetState() == 'accepted' && $this->sState == 'pending')
                $this->sState = 'queued';

                $bUpdateRatingInfo = true;
        }

        if(query_parameters("UPDATE testResults SET 
                                        versionId       = '?',
                                        whatWorks       = '?',
                                        whatDoesnt      = '?',
                                        whatNotTested   = '?',
                                        testedDate      = '?',
                                        distributionId  = '?',
                                        testedRelease   = '?',
                                        staging         = '?',
                                        installs        = '?',
                                        runs            = '?',
                                        usedWorkaround  = '?',
                                        workarounds     = '?',
                                        testedRating    = '?',
                                        comments        = '?',
                                        state           = '?', 
                                        gpuMfr          = '?', 
                                        graphicsDriver  = '?',
                                        hasProblems     = '?' 
                                    WHERE testingId = '?'",
                            $this->iVersionId,
                            $this->shWhatWorks,
                            $this->shWhatDoesnt,
                            $this->shWhatNotTested,
                            $this->sTestedDate,
                            $this->iDistributionId,
                            $this->sTestedRelease,
                            $this->iStaging,
                            $this->sInstalls,
                            $this->sRuns,
                            $this->sUsedWorkaround,
                            $this->shWorkarounds,
                            $this->sTestedRating,
                            $this->shComments,
                            $this->sState,
                            $this->sGpuMfr,
                            $this->sGraphicsDriver,
                            $this->sHasProblems,
                            $this->iTestingId))
        {
            if($bUpdateRatingInfo && $this->sState == 'accepted')
            {
                if($this->iVersionId != $oOldTest->iVersionId)
                {
                    $oNewVersion = new version($this->iVersionId);
                    $oNewVersion->updateRatingInfo();
                    $oOldVersion = new version($oOldTest->iVersionId);
                    $oOldVersion->updateRatingInfo();
                }
                $oVersion->updateRatingInfo();
            }

            if(!$bSilent)
                $this->SendNotificationMail("edit", $sWhatChanged);
            return true;
        }
        else
        {
            addmsg("Error while updating test results", "red");
            return false;
        }
    }

    // Purge test results from the database
    function purge()
    {
        // is the current user allowed to delete this test result? 
        $oVersion = new Version($this->iVersionId);
        if(!$_SESSION['current']->hasPriv("admin") && 
           !$_SESSION['current']->hasAppVersionModifyPermission($oVersion) &&
           !(($_SESSION['current']->iUserId == $this->iSubmitterId) && !($this->sState == 'accepted')))
        {
            return false;
        }

        // now we delete the data
        $sQuery = "DELETE FROM testResults
                WHERE testingId = '?' 
                LIMIT 1";
        if(!($hResult = query_parameters($sQuery, $this->iTestingId)))
            return false;

        return true;
    }

    // Delete test results.
    function delete()
    {
        // is the current user allowed to delete this test result? 
        $oVersion = new Version($this->iVersionId);
        if(!$_SESSION['current']->hasPriv("admin") && 
           !$_SESSION['current']->hasAppVersionModifyPermission($oVersion) &&
           !(($_SESSION['current']->iUserId == $this->iSubmitterId) && $this->sState != 'accepted'))
        {
            return false;
        }

        // now we flag the data as deleted
        $sQuery = "UPDATE testResults SET state = 'deleted'
                   WHERE testingId = '?' 
                   LIMIT 1";
        if(!($hResult = query_parameters($sQuery, $this->iTestingId)))
        {
            addmsg("Error removing the deleted test data!", "red");
            return false;
        }

        if($this->sState == 'accepted')
            $oVersion->updateRatingInfo();

        return true;
    }


    // Move Test Data out of the queue.
    function unQueue()
    {
        // is the current user allowed to delete this test data? 
        $oVersion = new Version($this->iVersionId);
        if(!$_SESSION['current']->hasPriv("admin") &&
           !$_SESSION['current']->hasAppVersionModifyPermission($oVersion))
        {
            return false;
        }

        // If we are not in the queue, we can't move the test data out of the queue.
        if($this->sState == 'accepted')
            return false;

        if(query_parameters("UPDATE testResults SET state = '?' WHERE testingId = '?'",
                            'accepted', $this->iTestingId))
        {
            $this->sState = 'accepted';
            // we send an e-mail to interested people
            $this->mailSubmitter("add");
            $this->SendNotificationMail();
        } else
        {
          return false;
        }

        $oVersion->updateRatingInfo();

        return true;
    }

    function Reject()
    {
        // is the current user allowed to delete this test data? 
        $oVersion = new Version($this->iVersionId);
        if(!$_SESSION['current']->hasPriv("admin") &&
           !$_SESSION['current']->hasAppVersionModifyPermission($oVersion))
        {
            return;
        }

        // If we are not in the queue, we can't move the version out of the queue.
        if($this->sState != 'queued')
            return false;

        if(query_parameters("UPDATE testResults SET state = '?' WHERE testingId = '?'", 
                            'rejected', $this->iTestingId))
        {
            $this->sState = 'rejected';
            // we send an e-mail to interested people
            $this->mailSubmitter("reject");
            $this->SendNotificationMail("reject");
        }
    }

    function ReQueue()
    {
        // is the current user allowed to requeue this data 
        $oVersion = new Version($this->iVersionId);
        if(!$_SESSION['current']->hasPriv("admin") &&
           !$_SESSION['current']->hasAppVersionModifyPermission($oVersion) &&
           !$_SESSION['current']->iUserId == $this->iSubmitterId)
        {
            return;
        }

        if(query_parameters("UPDATE testResults SET state = '?' WHERE testingId = '?'",
                            'queued', $this->iTestingId))
        {
            $this->sState = 'queued';
            // we send an e-mail to interested people
            $this->SendNotificationMail();
        }
    }

    function objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction)
    {
        $oOptions = new mailOptions();

        if($sAction == "delete" && $bParentAction)
            $oOptions->bMailOnce = TRUE;

        return $oOptions;
    }

    function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        $oSubmitter = new User($this->iSubmitterId);
        $sName = version::fullName($this->iVersionId);

        $sMsg = null;
        $sSubject = null;

        if($bMailSubmitter)
        {
            switch($sAction)
            {
                case "delete":
                    $sSubject =  "Submitted test data deleted";
                    if($bParentAction)
                    {
                        $sMsg = "All test data you submitted for '$sName' has ".
                                "been deleted because '$sName' was deleted.";
                    } else
                    {
                        $sMsg  = "The test report you submitted for '$sName' has ".
                                "been deleted.";
                    }
                break;
            }
            $aMailTo = nulL;
        } else
        {
            switch($sAction)
            {
                case "delete":
                    if(!$bParentAction)
                    {
                        $sSubject = "Test Results deleted for $sName by ".
                                    $_SESSION['current']->sRealname;
                        $sMsg = "";
                    }
                break;
            }
            $aMailTo = User::get_notify_email_address_list(null, $this->iVersionId);
        }
        return array($sSubject, $sMsg, $aMailTo);
    }

    function mailSubmitter($sAction="add")
    {
        global $aClean;

        if($this->iSubmitterId)
        {
            $oSubmitter = new User($this->iSubmitterId);

            /* Get the full app/version name to display */
            $sName = version::fullName($this->iVersionId);

            $oVersion = new version($this->iVersionId);

            switch($sAction)
            {
            case "add":
                $sSubject =  "Submitted test data accepted";
                $sMsg  = "The test data you submitted for '$sName' has been ".
                        "accepted by ".$_SESSION['current']->sRealname.".\n";
                $sMsg .= $oVersion->objectMakeUrl()."&amp;iTestingId=".$this->iTestingId."\n";
                $sMsg .= "Administrators response:\n";
            break;
            case "reject":
                $sSubject =  "Submitted test data rejected";
                $sMsg  = "The test data you submitted for '$sName' has ".
                        "been rejected by ".$_SESSION['current']->sRealname.".";
                $sMsg .= $this->objectMakeUrl()."\n";
                $sMsg .= "Reason given:\n";
            break;
            }
            $sMsg .= $aClean['sReplyText']."\n";
            $sMsg .= "We appreciate your help in making the Application ".
                    "Database better for all users.";

            mail_appdb($oSubmitter->sEmail, $sSubject ,$sMsg);
        }
    }

 
    function SendNotificationMail($sAction="add",$sMsg=null)
    {
        global $aClean;

        $oVersion = new Version($this->iVersionId);
        $oApp = new Application($oVersion->iAppId);
        $sBacklink = $oVersion->objectMakeUrl()."&amp;iTestingId=".$this->iTestingId."\n";

        switch($sAction)
        {
            case "add":
                if($this->sState == 'accepted')
                {
                    $sSubject = "Test Results added to version ".$oVersion->sName." of ".$oApp->sName." by ".$_SESSION['current']->sRealname;
                    $sMsg  .= $sBacklink;
                    if($this->iSubmitterId)
                    {
                        $oSubmitter = new User($this->iSubmitterId);
                        $sMsg .= "This Test data has been submitted by ".$oSubmitter->sRealname.".";
                        $sMsg .= "\n";
                    }
                    if($aClean['sReplyText'])
                    {
                        $sMsg .= "Appdb admin reply text:\n";
                        $sMsg .= $aClean['sReplyText']."\n"; // append the reply text, if there is any 
                    }
                    addmsg("The test data was successfully added into the database.", "green");
                } else // test data queued.
                {
                    $sSubject = "Test Results submitted for version ".$oVersion->sName." of ".$oApp->sName." by ".$_SESSION['current']->sRealname;
                    $sMsg  .= $sBacklink;
                    $sMsg .= "This test data has been queued.";
                    $sMsg .= "\n";
                    addmsg("The test data you submitted will be added to the database after being reviewed.", "green");
                }
            break;
            case "edit":
                $sSubject = "Test Results modified for version ".$oVersion->sName." of ".$oApp->sName." by ".$_SESSION['current']->sRealname;
                $sMsg  .= $sBacklink;
            break;
            case "reject":
                $sSubject = "Test Results rejected for version ".$oVersion->sName." of ".$oApp->sName." by ".$_SESSION['current']->sRealname;
                $sMsg  .= $sBacklink;
                 // if replyText is set we should report the reason the data was rejected 
                if($aClean['sReplyText'])
                {
                    $sMsg .= "Reason given:\n";
                    $sMsg .= $aClean['sReplyText']."\n"; // append the reply text, if there is any 
                }
                addmsg("test data rejected.", "green");
            break;
        }
        $sEmail = User::get_notify_email_address_list(null, $this->iVersionId);
        if($sEmail)
            mail_appdb($sEmail, $sSubject ,$sMsg);
    }
 
    function ShowTestResult()
    {
        return "<p><b>What works</b></p>\n<p>{$this->shWhatWorks}</p>\n".
               "<p><b>What does not</b></p>\n<p>{$this->shWhatDoesnt}</p>\n".
               "<p><b>Workarounds</b></p>\n<p>{$this->shWorkarounds}</p>\n".               
               "<p><b>What was not tested</b></p>\n<p>{$this->shWhatNotTested}</p>\n".
               "<p><b>Hardware tested</b></p>\n<p><i>Graphics: </i><ul><li>GPU: {$this->sGpuMfr}</li><li>Driver: {$this->sGraphicsDriver}</li></ul></p>".
               "<p><b>Additional Comments</b></p>\n<p>{$this->shComments}</p>\n";
    }

    function CreateTestTable()
    {
        // create the table
        $oTable = new Table();
        $oTable->SetClass("whq-table whq-table-full");

        // setup the table header
        $oTableRowHeader = new TableRow();
        $oTableRowHeader->SetClass("historyHeader");
        $oTableRowHeader->AddTextCell("");
        $oTableRowHeader->AddTextCell("Operating system");
        $oTableRowHeader->AddTextCell("Test date");
        $oTableRowHeader->AddTextCell("Wine version");
        $oTableRowHeader->AddTextCell("Installs?");
        $oTableRowHeader->AddTextCell("Runs?");
        $oTableRowHeader->AddTextCell("Used<br>Workaround?");
        $oTableRowHeader->AddTextCell("Rating");
        $oTableRowHeader->AddTextCell("Submitter");
        $oTableRowHeader->AddTextCell("");
        $oTable->SetHeader($oTableRowHeader);
        return $oTable;
    }

    /* Creates and returns a table row for a test result table */
    function CreateTestTableRow($iCurrentId, $sLink, $bShowAll = false)
    {
        $oVersion = new Version($this->iVersionId);
        $oApp  = new Application($oVersion->iAppId);
        $oSubmitter = new User($this->iSubmitterId);
        $oDistribution = new distribution($this->iDistributionId);
        $bgcolor = $this->sTestedRating;

        // initialize the array ech time we loop
        $oTableRowClick = null;

        $oTableRow = new TableRow();

        /* if the test we are displaying is this test then */
        /* mark it as the current test */
        if ($this->iTestingId == $iCurrentId)
        {
            $sTRClass = $bgcolor;
            $oTableCell = new TableCell("<b>Current</b>");
            $oTableCell->SetAlign("center");
        }
        else
        {
            /* make all non-current rows clickable so clicking on them selects the test as current */
            $sUrl = $sLink.$this->iTestingId;
            if($bShowAll)
                $sUrl .= '&bShowAll=true';
            $oTableRowClick = new TableRowClick($sUrl);

            // add the table element indicating that the user can show the row by clicking on it
            $oTableCell = new TableCell("Show");
            $oTableCell->SetCellLink($sUrl);
            $oTableCell->SetAlign("center");
        }

        $oTableRow->AddCell($oTableCell);
        $oTableRow->AddTextCell($oDistribution->objectMakeLink());
        $oTableRow->AddTextCell(date("M d Y", mysqldatetime_to_unixtimestamp($this->sTestedDate)));
        $oTableRow->AddTextCell(($this->sTestedRelease).($this->iStaging != 0 ? '-staging':''));
        $oTableRow->AddTextCell($this->sInstalls.'&nbsp;');
        $oTableRow->AddTextCell($this->sRuns.'&nbsp;');
        $oTableRow->AddTextCell($this->sUsedWorkaround);
        $oTableCell = new TableCell($this->sTestedRating);
        $oTableCell->SetClass($bgcolor);
        $oTableRow->AddCell($oTableCell);
        $oTableRow->AddTextCell($oSubmitter->objectMakeLink().'&nbsp;');
        if ($this->iTestingId && $_SESSION['current']->hasAppVersionModifyPermission($oVersion))
        {
            $oObject = new objectManager('testData');
            if($oApp->canEdit())
                $shChangeParentLink = '<a href="'.$oObject->makeUrl('showChangeParent', $this->iTestingId, 'Move test report to another version').'&amp;sReturnTo='.urlencode($_SERVER['REQUEST_URI']).'" class="btn btn-default button-xs">Move</a>'."\n";
            else
                $shChangeParentLink = '';

            $oTableRow->AddTextCell('<a href="'.$oObject->makeUrl('edit', $this->iTestingId,
                                    'Edit Test Results').'&amp;sReturnTo='.urlencode($_SERVER['REQUEST_URI']).'" class="btn btn-default button-xs">'.
                                    'Edit</a> '."\n".
                                    $shChangeParentLink.
                                    '<a href="'.$oObject->makeUrl('delete', $this->iTestingId, 'Delete+Test+Results').
                                    '&amp;sReturnTo='.urlencode($_SERVER['REQUEST_URI']).'" class="btn btn-default button-xs">Delete</a></td>'."\n");
        }

        // if this is a clickable row, set the appropriate property
        if($oTableRowClick)
            $oTableRow->SetRowClick($oTableRowClick);

        return $oTableRow;
    }

    // Show the Test results for a application version
    function ShowVersionsTestingTable($sLink, $iDisplayLimit)
    {
        global $aClean;

        /* escape input parameters */
        $sLink = query_escape_string($sLink);
        $iDisplayLimit = query_escape_string($iDisplayLimit);

        $bShowAll = (getInput('bShowAll', $aClean) == 'true') ? true : false;

        $sQuery = "SELECT * 
                   FROM testResults, ?.versions
                   WHERE versionId = '?'
                   AND
                   versions.value = testResults.testedRelease
                   AND
                   versions.product_id = '?'
                   AND
                   state = '?'
                   ORDER BY versions.id DESC,testedDate DESC";
	
        if(!$bShowAll)
            $sQuery.=" LIMIT 0,".$iDisplayLimit;

        $hResult = query_parameters($sQuery, BUGZILLA_DB, $this->iVersionId, BUGZILLA_PRODUCT_ID, 'accepted');
        if(!$hResult)
            return;

        $rowsUsed = query_num_rows($hResult);

        if($rowsUsed == 0)
             return;

        $oTable = $this->CreateTestTable();

        $iIndex = 0;
        while($oRow = query_fetch_object($hResult))
        {
            $oTest = new testData($oRow->testingId);
            $oTableRow = $oTest->CreateTestTableRow($this->iTestingId, $sLink, $bShowAll);
            // add the row to the table
            $oTable->AddRow($oTableRow);

            $iIndex++;
        }

        $output = "";
        $output .= $oTable->GetString();
        $output .= '<form method="get" action="objectManager.php">'."\n";

        if($rowsUsed >= $iDisplayLimit && $bShowAll)
        {
            $sShowButtonText = "Limit to <b>$iDisplayLimit</b> tests";
        }
        else
        {
            $sShowButtonText = "Show all tests";
            $output .= '<input type="hidden" name="bShowAll" value="true">';
        }

        $oManager = new objectManager("version", null, $this->iVersionId);
        $output .= $oManager->makeUrlFormData();
        $output .= '<button type="submit" class="btn btn-default">'.$sShowButtonText."</button>\n";
        $output .= "</form>\n";
        return $output;
    }

    /* Convert a given rating string to a numeric scale */
    public function ratingToNumber($sRating)
    {
        switch($sRating)
        {
            case GARBAGE_RATING:
                return 0;
            case BRONZE_RATING:
                return 1;
            case SILVER_RATING:
                return 2;
            case GOLD_RATING:
                return 3;
            case PLATINUM_RATING:
                return 4;
        }
    }

    /* Convert a numeric rating scale to a rating name */
    public function numberToRating($iNumber)
    {
        switch($iNumber)
        {
            case 0:
                return GARBAGE_RATING;
            case 1:
                return BRONZE_RATING;
            case 2:
                return SILVER_RATING;
            case 3:
                return GOLD_RATING;
            case 4:
                return PLATINUM_RATING;
        }
    }

    /* Gets rating info for the selected version: an array with the elements
       0 - Rating
       1 - Wine version (including staging info)*/
    public static function getRatingInfoForVersionId($iVersionId)
    {
        $iNewestId = testData::getNewestTestIdFromVersionId($iVersionId);
        $oTestData = new testData($iNewestId);
        return array($oTestData->sTestedRating, ($oTestData->sTestedRelease) . ($oTestData->iStaging != 0 ? '-staging':''));
    }

    /* retrieve the latest test result for a given version id */
    public static function getNewestTestIdFromVersionId($iVersionId, $sState = 'accepted')
    {
        $sQuery = "SELECT testingId FROM testResults, ?.versions WHERE
                versions.value = testResults.testedRelease
                AND
                versions.product_id = '?'
                AND
                versionId = '?'
                AND
                state = '?'
                     ORDER BY versions.id DESC,testedDate DESC limit 1";

        $hResult = query_parameters($sQuery, BUGZILLA_DB, BUGZILLA_PRODUCT_ID, $iVersionId, $sState);

        if(!$hResult)
            return 0;

        if(!$oRow = query_fetch_object($hResult))
            return 0;

        return $oRow->testingId;
    }

    // show the fields for editing
    function outputEditor()
    {
        global $aClean;

        /* Fill in some values */
        if(!$this->iVersionId)
            $this->iVersionId = $aClean['iVersionId'];
        if(!$this->sTestedDate)
            $this->sTestedDate = date('Y-m-d H:i:s');

        $sName = version::fullName($this->iVersionId);

        echo html_frame_start("Test Form - $sName", "90%", "", 0);
        
        echo '<table class="whq-table whq-table-striped table-bordered" width="100%" border=0 cellpadding=2 cellspacing=0>';
        
        // Installs
        echo '<tr><td><b>Installs?</b></td><td>',"\n";
        testData::make_Installs_list("sInstalls", $this->sInstalls);
        echo '&nbsp; Installing is an important part of testing under Wine. Select N/A if there is no installer.</td></tr>',"\n";
       
        // Runs
        echo '<tr><td><b>Runs?</b></td><td>',"\n";
        testData::make_Runs_list("sRuns", $this->sRuns);
        echo '</td></tr>',"\n";

        // What works
        echo '<tr valign=top><td><b>What works</b></td>',"\n";
        echo '<td><p><textarea cols="80" rows="20" id="Test1" name="shWhatWorks" class="wysiwyg">';
        echo $this->shWhatWorks.'</textarea></p></td></tr>',"\n";
        
        // What Does not work
        echo '<tr valign=top><td><b>What does not work</b></td>',"\n";
        echo '<td>';
        echo 'Were there any problems that do not exist in Windows? <br>';
        if(isset($this->sHasProblems))        
        {
            if($this->sHasProblems == "Yes")
            {
                echo '<label class="radio-inline"><input type="radio" data-toggle="radioshow" data-target="#whatdoesnt" data-showdiv=true name="sHasProblems" value="Yes" checked>Yes</label>';
                echo '<label class="radio-inline"><input type="radio" data-toggle="radioshow" data-target="#whatdoesnt" data-showdiv=false name="sHasProblems" value="No">No</label>';
            }else{
                echo '<label class="radio-inline"><input type="radio"data-toggle="radioshow" data-target="#whatdoesnt" data-showdiv=true name="sHasProblems" value="Yes">Yes</label>';
                echo '<label class="radio-inline"><input type="radio" data-toggle="radioshow" data-target="#whatdoesnt" data-showdiv=false name="sHasProblems" value="No" checked>No</label>';
            } 
        } else {
             echo '<label class="radio-inline"><input type="radio" data-toggle="radioshow" data-target="#whatdoesnt" data-showdiv=true name="sHasProblems" value="Yes">Yes</label>';
             echo '<label class="radio-inline"><input type="radio" data-toggle="radioshow" data-target="#whatdoesnt" data-showdiv=false name="sHasProblems" value="No">No</label>';
        };
        echo '<div id="whatdoesnt" style="display:none">';
        echo '<i>Describe the problem(s) in the box below.</i><br>';
        echo '<p><textarea cols="80" rows="20" id="Test2" name="shWhatDoesnt" class="wysiwyg">';
        echo $this->shWhatDoesnt.'</textarea></p></td></tr>',"\n";
        echo '</div>';
        
        // Workarounds
        echo '<tr valign=top><td><b>Workarounds</b></td>',"\n";
        echo '<td>';
        echo 'Were any workarounds used for problems that do not exist in Windows? <br>';
        if(isset($this->sUsedWorkaround))        
        {
            if($this->sUsedWorkaround == "Yes")
            {
                echo '<label class="radio-inline"><input type="radio" data-toggle="radioshow" data-target="#workarounds" data-showdiv=true  name="sUsedWorkaround" value="Yes" checked>Yes</label>';
                echo '<label class="radio-inline"><input type="radio" data-toggle="radioshow" data-target="#workarounds" data-showdiv=false name="sUsedWorkaround" value="No">No</label>';
            }else{
                echo '<label class="radio-inline"><input type="radio" data-toggle="radioshow" data-target="#workarounds" data-showdiv=true  name="sUsedWorkaround" value="Yes">Yes</label>';
                echo '<label class="radio-inline"><input type="radio" data-toggle="radioshow" data-target="#workarounds" data-showdiv=false  name="sUsedWorkaround" value="No" checked>No</label>';
            } 
        } else {
             echo '<label class="radio-inline"><input type="radio" data-toggle="radioshow" data-target="#workarounds" data-showdiv=true name="sUsedWorkaround" value="Yes">Yes</label>';
             echo '<label class="radio-inline"><input type="radio" data-toggle="radioshow" data-target="#workarounds" data-showdiv=false name="sUsedWorkaround" value="No">No</label><br>';
        };
        
        echo '<div id="workarounds" style="display:none">';
        echo '<i>Describe the workaround(s) in the box below.</i><br>';
        echo '<textarea cols="80" rows="20" id="Test4" name="shWorkarounds" class="wysiwyg">';
        echo $this->shWorkarounds.'</textarea></p></td></tr>',"\n";
        echo '</div>';
        
        // What was not tested
        echo '<tr valign=top><td><b>What was not tested</b></td>',"\n";
        echo '<td><p><textarea cols="80" rows="20" id="Test3" name="shWhatNotTested" class="wysiwyg">';
        echo $this->shWhatNotTested.'</textarea></p></td></tr>',"\n";
        
        // Date Tested
        echo '<tr valign=top><td><b>Date tested </b></td>',"\n";
        echo '<td><input type=text name="sTestedDate" value="'.$this->sTestedDate.'" size="20"><br>';
        echo 'YYYY-MM-DD HH:MM:SS</td></tr>',"\n";

        // Version List
        echo '<tr><td><b>Tested release</b></td><td>',"\n";
        echo make_bugzilla_version_list("sTestedRelease", $this->sTestedRelease);
        // Give the user some information about our available versions
        echo "<span>Version not listed?  Your Wine is too old, <a href=\"//winehq.org/download\">upgrade!</a></span><br>";
        // Checkbox for Wine-staging
        echo '<label class="btn btn-secondary">';
        if($this->iStaging != 0)
            echo '<input type="checkbox" name="iStaging" value="1" checked>';  
        else
            echo '<input type="checkbox" name="iStaging" value="1">';
        echo '  Wine-staging';
        echo '</label>';
        echo '</td></tr>',"\n";
       
        // Rating
        echo '<tr><td><b>Rating</b></td><td>',"\n";
        echo make_maintainer_rating_list("sTestedRating", $this->sTestedRating);
        echo '<a href="https://wiki.winehq.org/AppDB_Maintainer_Rating_Definitions" target="_blank">Rating definitions</a></td></tr>',"\n";

        // extra comments
        echo '<tr valign=top><td><b>Extra comments</b></td>',"\n";
        echo '<td><textarea name="shComments" id="extra_comments" rows=20 cols=80 class="wysiwyg">';
        echo $this->shComments.'</textarea></td></tr>',"\n";

        /* Graphics hardware/driver
            this section could be expanded to include info on other hardware (e.g., CPU, audio) */
        echo '<tr><td><b>Hardware</b></td>',"\n";
        echo '<td>';
        echo 'Graphics: ';
        echo  testData::make_gpuMfr_list("sGpuMfr", $this->sGpuMfr);
        echo testData::make_graphicsDriver_list('sGraphicsDriver', $this->sGraphicsDriver); 
        echo '</td></tr>';
        
        // Distribution
        $oDistribution = new distribution($this->iDistributionId);
        $sDistributionHelp = "";
        if(!$this->iDistributionId || $oDistribution->objectGetState() != 'accepted')
        {
            if(!$this->iDistributionId)
            {
                $sDistributionHelp = "If yours is not on the list, ".
                                     "please add it using the form below.<br>";
            } else
            {
                $sDistributionHelp = '<p class="bg-danger"><span class = "text-danger fa fa-exclamation-triangle" style="font-size:125%"></span> The user submitted a new operating system; please review it in the form below.</p>';
            }
        }

        echo '<tr valign=top><td><b>Operating system</b></td>',"\n";

        echo '<td>',"\n";
        echo $sDistributionHelp;
        distribution::make_distribution_list("iTestDistributionId", $this->iDistributionId);
        echo '</td></tr>',"\n";

        // Display confirmation box for changing the Wine version
        $oOldTest = new testData($this->iTestingId);
        if($this->iTestingId && $oOldTest->sTestedRelease != $this->sTestedRelease
            || $this-iTestingId && $oOldTest->iStaging != $this->iStaging)
        {
            if(getInput('bConfirmTestedVersionChange', $aClean) != 'true')
            {
                echo '<tr><td>&nbsp;</td><td>';
                echo 'You have changed the Wine version of the report.  Are you sure you want to do this?  Please submit a new test report for every Wine version you test; this is useful for tracking Wine\'s progress.<br>';
                echo '<input type="checkbox" name="bConfirmTestedVersionChange" value="true"> ';
                echo 'Yes, I want to change the Wine version';
                echo '</td></tr>';
            } else
            {
                echo '<input type="hidden" name="bConfirmTestedVersionChange" value="true">';
            }
        }

        echo '<input type="hidden" name="iVersionId" value="'.$this->iVersionId.'" >';
        echo '<input type="hidden" name="iTestingId" value="'.$this->iTestingId.'" >';
        echo '<input type="hidden" name="iTestDataId" value="'.$this->iTestingId.'" >';
        
        echo "</table>\n";

        echo html_frame_end();
    }

    /* $aValues can be $aValues or any array with the values from outputEditor() */
    function CheckOutputEditorInput($aValues, $sDistribution="")
    {
        $errors = "";
        if (empty($aValues['shWhatWorks']) && ($aValues['sRuns'] == "Yes" || $aValues['sInstalls'] == "Yes" || $aValues['sInstalls'] == "No, but has workaround"))
            $errors .= "<li>Please enter what worked.</li>\n";  

        if (empty($aValues['sHasProblems']))
            $errors .= "<li>Please enter whether the application had any problems not present in Windows.</li>\n";

        if (empty($aValues['shWhatNotTested']))
            $errors .= "<li>Please enter what was not tested.</li>\n";
            
        if (empty($aValues['sUsedWorkaround']))
            $errors .= "<li>Please enter whether workarounds were used or not.</li>\n";

        if (empty($aValues['sTestedDate']))
            $errors .= "<li>Please enter the date and time when you tested.</li>\n";

        if (empty($aValues['sTestedRelease']))
            $errors .= "<li>Please enter the version of Wine that you tested with.</li>\n";
            
        if (empty($aValues['sGpuMfr']))
            $errors .= "<li>Please select your GPU manufacturer from the list. If you don't know, select Unknown.</li>\n";
            
         if (empty($aValues['sGraphicsDriver']))
            $errors .= "<li>Please indicate whether your graphics driver is open source or proprietary. If you don't know, select unknown.</li>\n";   

        // Ask for confirmation if changing the tested Wine versions, becase we want users
        // to submit new reports instead of updating existing ones when testing new Wines
        $oOldTest = new testData($this->iTestingId);
        if (
            $this->iTestingId
            && (getInput('bConfirmTestedVersionChange', $aValues) != 'true')
            && (
                $oOldTest->sTestedRelease != getInput('sTestedRelease', $aValues)
                || $oOldTest->iStaging != intval(getInput('iStaging', $aValues))
               )
           )
        {
            $errors .= '<li>Are you sure you want to change the Wine version of the report? Please submit a new '.
                        'test report for every Wine version you test; this is useful for tracking Wine\'s progress. '.
                        'Tick the box above the submit button if you want to proceed</li>';
        }

        // No Distribution entered, and nothing in the list is selected
        if (empty($aValues['sDistribution']) && !$aValues['iTestDistributionId'])
            $errors .= "<li>Please enter an operating system.</li>\n";

        if (empty($aValues['sInstalls']))
            $errors .= "<li>Please enter whether this application installs or not.</li>\n";

        if (empty($aValues['sRuns']))
            $errors .= "<li>Please enter whether this application runs or not.</li>\n";
            
        if ($aValues['sHasProblems'] == "Yes" && empty($aValues['shWhatDoesnt']))
            $errors .= "<li>Please describe the problem(s) encountered.</li>\n";
            
        if ($aValues['sUsedWorkaround'] == "Yes" && empty($aValues['shWorkarounds']))
            $errors .= "<li>Please describe the workaround(s) used for this application.</li>\n";
                       
        if (empty($aValues['sTestedRating']))
            $errors .= "<li>Please enter a rating based on how well this application runs.</li>\n";

        // Basic checking of rating logic to ensure that the users test results
        // are consistent
        if (($aValues['sInstalls'] == "No") && ($aValues['sTestedRating'] != GARBAGE_RATING))
            $errors .= "<li>Applications that do not install should be rated &#8216;Garbage&#8217;.</li>\n";
        
        if (($aValues['sRuns'] == "No") && ($aValues['sTestedRating'] != GARBAGE_RATING))
            $errors .= "<li>Applications that do not run should be rated &#8216;Garbage&#8217;.</li>\n";
            
        if (($aValues['sHasProblems'] == "Yes" || $aValues['sUsedWorkaround'] == "Yes") && $aValues['sTestedRating'] == PLATINUM_RATING)
            $errors .= "<li>An application can only get a Platinum rating if it installs, runs, and everything works &#8216;out of the box&#8217; (no problems and no workarounds used).</li>\n";

        if (($aValues['sHasProblems'] == "Yes" && $aValues['sUsedWorkaround'] == 'No') &&  ($aValues['sTestedRating'] == PLATINUM_RATING || $aValues['sTestedRating'] == GOLD_RATING))
            $errors .= "<li>An application cannot be rated higher than Silver if it had problems without workarounds. </li>\n";

        if ($aValues['sUsedWorkaround'] == "No" && $aValues['sTestedRating'] == GOLD_RATING)
            $errors .= "<li>If the rating is Gold you must answer &#8217Yes&#8217 to the Workarounds question and describe the workarounds used.</li>\n";

        // Basic checking of logic.  Runs? can obviously only be 'Not Installable'
        // if the application does not install
        if (($aValues['sInstalls'] != "No") && ($aValues['sRuns'] == "Not installable"))
            $errors .= "<li>You can only set Runs? to &#8216;Not installable&#8217; if Installs? is set &#8216;No&#8217;</li>\n";
	    
        if (($aValues['sInstalls'] == "No") && ($aValues['sRuns'] != "Not installable"))
            $errors .= "<li>Runs? must be set to &#8216;Not installable&#8217; if Installs? is set to &#8216;No&#8217;.</li>\n";
            
        if ($aValues['sInstalls'] == "No, but has workaround" && ($aValues['sUsedWorkaround'] == "No" || empty($aValues[shWorkarounds])))
            $errors .= "<li>If Installs? is set to &#8216;No, but has workaround&#8216;, Workarounds must be set to &#8216;Yes&#8216; and details provided in the text area.</li>\n";           

        if (($aValues['sInstalls'] == "No" || $aValues['sInstalls'] == "No, but has workaround") && $aValues['sHasProblems'] == "No")
            $errors .= "<li>If Installs? is set to &#8216;No&#8216; or &#8216;No, but has workaround&#8216;, you must answer Yes to the question about problems and provide details in the What does not work text area.</li>\n";
            
        if ($aValues['sRuns'] == "No" && $aValues['sHasProblems'] == "No" )
            $errors .="<li>If Runs? is set to &#8216;No&#8216; you must answer Yes to the question about problems and provide details in the What does not work text area.</li>\n";
            
        if ($aValues['sHasProblems'] == "No" && $aValues['sUsedWorkaround'] == 'Yes')
            $errors .= "<li>Workarounds should not have been used if there were no problems that are not also present in Windows.</li>";
                        
        if ($aValues['sHasProblems'] == "No" && !empty($aValues[shWhatDoesnt]))
            $errors .="<li>Leave the What does not work text field blank if there were no problems that are not also present in Windows.</li>";
            
        if ($aValues['sUsedWorkaround'] == "No" && !empty($aValues[shWorkarounds]))
            $errors .="<li>Leave the Workarounds text field blank if no workarounds were used.</li>";

        return $errors;

    }

    /* retrieves values from $aValues that were output by outputEditor() */
    /* $aValues can be $_REQUEST or any array with the values from outputEditor() */
    function GetOutputEditorValues($aValues)
    {
        if($aValues['iTestingId'])
            $this->iTestingId = $aValues['iTestingId'];

        if($aValues['iVersionId'])
            $this->iVersionId = $aValues['iVersionId'];

        $this->shWhatWorks = $aValues['shWhatWorks'];
        $this->shWhatDoesnt = $aValues['shWhatDoesnt'];
        $this->shWorkarounds = $aValues['shWorkarounds'];
        $this->shWhatNotTested = $aValues['shWhatNotTested'];
        $this->sTestedDate = $aValues['sTestedDate'];
        
        //Favor distribution dropdown list selections over textboxes.
        if($aValues['iTestDistributionId'])
            $this->iDistributionId = $aValues['iTestDistributionId'];
        else
            $this->iDistributionId = $aValues['iDistributionId'];
            
        $this->sTestedRelease = $aValues['sTestedRelease'];
        $this->iStaging = intval($aValues['iStaging']);
        $this->sInstalls = $aValues['sInstalls'];
        $this->sRuns = $aValues['sRuns'];
        $this->sUsedWorkaround = $aValues['sUsedWorkaround'];
        $this->sTestedRating = $aValues['sTestedRating'];
        $this->shComments = $aValues['shComments'];
        $this->sGpuMfr = $aValues['sGpuMfr'];
        $this->sGraphicsDriver = $aValues['sGraphicsDriver'];
        $this->sHasProblems = $aValues['sHasProblems'];
    }

    function make_Installs_list($sVarname, $sSelectedValue)
    {
        echo "<select name='$sVarname' class='form-control form-control-inline'>\n";
        echo "<option value=\"\">Choose ...</option>\n";
        $aRating = array('Yes', 'No', 'No, but has workaround', 'N/A');
        $iMax = count($aRating);

        for($i=0; $i < $iMax; $i++)
        {
            if($aRating[$i] == $sSelectedValue)
                echo "<option value='".$aRating[$i]."' selected>".$aRating[$i]."\n";
            else
                echo "<option value='".$aRating[$i]."'>".$aRating[$i]."\n";
        }
        echo "</select>\n";
    }

    function make_Runs_list($sVarname, $sSelectedValue)
    {
        echo "<select name='$sVarname' class='form-control form-control-inline'>\n";
        echo "<option value=\"\">Choose ...</option>\n";
        $aRating = array("Yes", "No", "Not installable");
        $iMax = count($aRating);

        for($i=0; $i < $iMax; $i++)
        {
            if($aRating[$i] == $sSelectedValue)
                echo "<option value='".$aRating[$i]."' selected>".$aRating[$i]."\n";
            else
                echo "<option value='".$aRating[$i]."'>".$aRating[$i]."\n";
        }
        echo "</select>\n";
    }
    
    function make_gpuMfr_list($sVarname, $sSelectedValue)
    {
        echo "<select name='$sVarname'class='form-control form-control-inline'>\n";
        echo "<option value=\"\">GPU manufacturer?</option>\n";
        $aGpuMfr = array('AMD', 'Intel', 'Nvidia', 'Other', 'Unknown');
        $iMax = count($aGpuMfr);
        
        for($i=0; $i < $iMax; $i++)
        {
            if($aGpuMfr[$i] == $sSelectedValue)
                echo "<option value='".$aGpuMfr[$i]."' selected>".$aGpuMfr[$i]."\n";
            else
                echo "<option value='".$aGpuMfr[$i]."'>".$aGpuMfr[$i]."\n";
        }
        echo "</select>\n";
    }
    
    function make_graphicsDriver_list($sVarname, $sSelectedValue)
    
    {
        echo "<select name='$sVarname' class='form-control form-control-inline'>\n";
        echo "<option value=\"\">Open source or proprietary driver?</option>\n";
        $aGraphicsDriver = array('open source', 'proprietary', 'unknown');
        $iMax = count($aGraphicsDriver);
        
        for($i=0; $i < $iMax; $i++)
        {
            if($aGraphicsDriver[$i] == $sSelectedValue)
                echo "<option value='".$aGraphicsDriver[$i]."' selected>".$aGraphicsDriver[$i]."\n";
            else
                echo "<option value='".$aGraphicsDriver[$i]."'>".$aGraphicsDriver[$i]."\n";
        }
        echo "</select>\n";
    }

    public static function getTestResultsForUser($iUserId, $iVersionId)
    {
        $oVersion = new version($iVersionId);
        $hResult = query_parameters("SELECT * FROM testResults WHERE
                                     submitterId = '?'
                                     AND versionId = '?'
                                     AND state = '?'
                                     ORDER BY testingId DESC", $iUserId, $iVersionId, $oVersion->objectGetState());

        if(!$hResult)
            return null;

        $aRet = array();

        if(!query_num_rows($hResult))
            return $aRet;

        while(($oRow = query_fetch_object($hResult)))
            $aRet[] = new testData(0, $oRow);

        return $aRet;
    }

    /* List test data submitted by a given user.  Ignore test results for queued applications/versions */
    public static function listSubmittedBy($iUserId, $bQueued = true)
    {
        $hResult = query_parameters("SELECT testResults.versionId, testResults.testedDate, testResults.testedRelease, testResults.testedRating, testResults.staging, testResults.submitTime, testResults.testingId, appFamily.appName, appVersion.versionName from testResults, appFamily, appVersion WHERE testResults.versionId = appVersion.versionId AND appVersion.appId = appFamily.appId  AND testResults.submitterId = '?' AND testResults.state = '?' ORDER BY testResults.testingId", $iUserId, $bQueued ? 'queued' : 'accepted');

        if(!$hResult || !query_num_rows($hResult))
            return false;

        $oTable = new Table();
        $oTable->SetWidth("100%");
        $oTable->SetAlign("center");

        // setup the table header
        $oTableRow = new TableRow();
        $oTableRow->AddTextCell('Version');
        $oTableRow->AddTextCell('Rating');
        $oTableRow->AddTextCell('Wine version');
        $oTableRow->AddTextCell('Submission date');

        if($bQueued)
            $oTableRow->addTextCell('Action');

        $oTableRow->SetClass('color4');
        $oTable->AddRow($oTableRow);

        for($i = 1; $oRow = query_fetch_object($hResult); $i++)
        {
            $oTableRow = new TableRow();

            $oTableRow->AddTextCell(version::fullNameLink($oRow->versionId));
            $oTableRow->AddTextCell($oRow->testedRating);
            $oTableRow->AddTextCell($oRow->testedRelease);
            $oTableRow->AddTextCell(print_date(mysqldatetime_to_unixtimestamp($oRow->submitTime)));

            if($bQueued)
            {
                $oM = new objectManager('testData_queue');
                $oM->setReturnTo(array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : "");
                $shDeleteLink = '<a href="'.$oM->makeUrl('delete', $oRow->testingId, 'Delete entry').'">delete</a>';
                $shEditLink = '<a href="'.$oM->makeUrl('edit', $oRow->testingId, 'Edit entry').'">edit</a>';
                $oTableRow->addTextCell("[ $shEditLink ] &nbsp; [ $shDeleteLink ]");
            }

            $oTableRow->SetClass($oRow->testedRating);
            $oTable->AddRow($oTableRow);
        }

        return $oTable->GetString();
    }

    // return the number of test data entries for a particular version id
    public static function get_testdata_count_for_versionid($iVersionId)
    {
        $sQuery = "SELECT count(*) as cnt
                   FROM testResults
                   WHERE versionId = '?'
                   AND
                   state = '?';";

        $hResult = query_parameters($sQuery, $iVersionId, 'accepted');

        $oRow = query_fetch_object($hResult);
        return $oRow->cnt;
    }

    public function objectGetFilterInfo()
    {
        $oFilter = new FilterInterface();

        /* The following filters are only useful for admins */
        if(!$_SESSION['current']->hasPriv('admin'))
            return null;

        $oFilter->AddFilterInfo('onlyWithoutMaintainers', 'Only show test data for versions without maintainers', array(FILTER_OPTION_BOOL), FILTER_VALUES_OPTION_BOOL, array('false','true'));

        $oFilter->AddFilterInfo('onlyMyMaintainedEntries', 'Only show test data for versions you maintain', array(FILTER_OPTION_BOOL), FILTER_VALUES_OPTION_BOOL, array('false','true'));

        return $oFilter;
    }

    public static function objectGetEntriesCount($sState, $oFilters = null)
    {
        $sExtraTables = '';
        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyWithoutMaintainers' => 'false', 'onlyMyMaintainedEntries' => 'false');
        $sWhereFilter = '';
        $bOnlyMyMaintainedEntries = false;

        $oTest = new testData();

        if(getInput('onlyMyMaintainedEntries', $aOptions) == 'true'
           || ($sState != 'accepted' && !$oTest->canEdit()))
        {
            $bOnlyMyMaintainedEntries = true;
        }

        /* This combination doesn't make sense */
        if(getInput('onlyWithoutMaintainers', $aOptions) == 'true'
           && getInput('onlyMyMaintainedEntries', $aOptions) == 'true')
        {
            return false;
        }

        if(getInput('onlyWithoutMaintainers', $aOptions) == 'true')
        {
            $sExtraTables = ',appVersion';

            $sWhereFilter .= " AND appVersion.hasMaintainer = 'false' AND appVersion.versionId = testResults.versionId";
        }

        if($bOnlyMyMaintainedEntries)
        {
            if(!$oTest->canEdit() && $sState == 'rejected')
            {
                $sQuery = "SELECT COUNT(testingId) AS count FROM
                        testResults$sExtraTables WHERE
                        testResults.submitterId = '?'
                        AND
                        testResults.state = '?'$sWhereFilter";
            } else
            {
                $sQuery = "SELECT COUNT(testingId) AS count FROM
                        testResults, appVersion, appMaintainers WHERE
                            testResults.versionId = appVersion.versionId
                            AND
                            appMaintainers.userId = '?'
                            AND
                            appMaintainers.state = 'accepted'
                            AND
                            (
                                (
                                    appMaintainers.superMaintainer = '1'
                                    AND
                                    appMaintainers.appId = appVersion.appid
                                )
                                OR
                                (
                                    appMaintainers.superMaintainer = '0'
                                    AND
                                    appMaintainers.versionId = appVersion.versionId
                                )
                            )
                            AND
                            testResults.state = '?'$sWhereFilter";
            }

            $hResult = query_parameters($sQuery, $_SESSION['current']->iUserId,
                                        $sState);
        } else
        {
            $sQuery = "SELECT COUNT(testingId) as count FROM testResults$sExtraTables WHERE
                    testResults.state = '?'$sWhereFilter";
            $hResult = query_parameters($sQuery, $sState);
        }

        if(!$hResult)
            return FALSE;

        if(!$oRow = query_fetch_object($hResult))
            return FALSE;

        return $oRow->count;
    }

    public static function objectGetDefaultSort()
    {
        return 'testingId';
    }

    public static function objectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = "testingId", $bAscending = true, $oFilters = null)
    {
        $sExtraTables = '';
        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyWithoutMaintainers' => 'false', 'onlyMyMaintainedEntries' => 'false');
        $sWhereFilter = '';
        $bOnlyMyMaintainedEntries = false;

        $oTest = new testData();

        if(getInput('onlyMyMaintainedEntries', $aOptions) == 'true'
           || ($sState != 'accepted' && !$oTest->canEdit()))
        {
            $bOnlyMyMaintainedEntries = true;
        }

        /* This combination doesn't make sense */
        if(getInput('onlyWithoutMaintainers', $aOptions) == 'true'
           && getInput('onlyMyMaintainedEntries', $aOptions) == 'true')
        {
            return false;
        }

        if(getInput('onlyWithoutMaintainers', $aOptions) == 'true')
        {
            $sExtraTables = ',appVersion';

            $sWhereFilter .= " AND appVersion.hasMaintainer = 'false' AND appVersion.versionId = testResults.versionId";
        }

        $sLimit = "";

        /* Should we add a limit clause to the query? */
        if($iRows || $iStart)
        {
            $sLimit = " LIMIT ?,?";

            /* Selecting 0 rows makes no sense, so we assume the user wants to select all of them
               after an offset given by iStart */
            if(!$iRows)
                $iRows = testData::objectGetEntriesCount($sState);
        }

        if($bOnlyMyMaintainedEntries)
        {
            if(!$oTest->canEdit() && $sState == 'rejected')
            {
                $sQuery = "SELECT testResults.* FROM testResults$sExtraTables WHERE
                        testResults.submitterId = '?'
                        AND
                        testResults.state = '?'$sWhereFilter ORDER BY ?$sLimit";
            } else
            {
                $sQuery = "SELECT testResults.* FROM testResults, appVersion,
                            appMaintainers WHERE
                            testResults.versionId = appVersion.versionId
                            AND
                            appMaintainers.userId = '?'
                            AND
                            (
                                (
                                    appMaintainers.superMaintainer = '1'
                                    AND
                                    appMaintainers.appId = appVersion.appid
                                )
                                OR
                                (
                                    appMaintainers.superMaintainer = '0'
                                    AND
                                    appMaintainers.versionId = appVersion.versionId
                                )
                            )
                            AND
                            appMaintainers.state = 'accepted'
                            AND
                            testResults.state = '?'$sWhereFilter ORDER BY ?$sLimit";
            }
            if($sLimit)
            {
                $hResult = query_parameters($sQuery, $_SESSION['current']->iUserId,
                                            $sState, $sOrderBy, $iStart, $iRows);
            } else
            {
                $hResult = query_parameters($sQuery, $_SESSION['current']->iUserId,
                                            $sState, $sOrderBy);
            }
        } else
        {
            $sQuery = "SELECT testResults.* FROM testResults$sExtraTables WHERE
                    testResults.state = '?'$sWhereFilter ORDER by ?$sLimit";
            if($sLimit)
                $hResult = query_parameters($sQuery, $sState, $sOrderBy, $iStart, $iRows);
            else
                $hResult = query_parameters($sQuery, $sState, $sOrderBy);
        }

        if(!$hResult)
            return FALSE;

        return $hResult;
    }

    function objectGetHeader()
    {
        $oTableRow = new TableRow();
        $oTableRow->AddTextCell("Submission Date");
        $oTableRow->AddTextCell("Submitter");
        $oTableRow->AddTextCell("Application");
        $oTableRow->AddTextCell("Version");
        $oTableRow->AddTextCell("Release");
        $oTableRow->AddTextCell("Has maintainer");
        $oTableRow->AddTextCell("Rating");
        return $oTableRow;
    }

    function objectGetTableRow()
    {
        $oVersion = new version($this->iVersionId);
        $oApp = new application($oVersion->iAppId);
        $oUser = new user($this->iSubmitterId);

        $bHasMaintainer = $oVersion->bHasMaintainer;

        $oTableRow = new TableRow();
        $oTableRow->AddCell(new TableCell(print_date(mysqldatetime_to_unixtimestamp($this->sSubmitTime))));
        $oTableRow->AddCell(new TableCell($oUser->objectMakeLink()));
        $oTableRow->AddCell(new TableCell($oApp->objectMakeLink()));
        $oTableRow->AddCell(new TableCell($oVersion->objectMakeLink()));
        $oTableRow->AddCell(new TableCell(($this->sTestedRelease) . ($this->iStaging != 0 ? '-staging':'')));
        $oTableRow->AddCell(new TableCell($bHasMaintainer ? "YES" : "no"));
        $oTableRow->AddCell(new TableCell($this->sTestedRating));

        $oTableRow->SetClass($this->sTestedRating);

        $oOMTableRow = new OMTableRow($oTableRow);
        $oOMTableRow->SetHasDeleteLink(true);

        return $oOMTableRow;
    }

    public function objectGetState()
    {
        return $this->sState;
    }

    function canEdit()
    {
        if($_SESSION['current']->hasPriv("admin"))
            return TRUE;
        else if($this->iVersionId)
        {
            if($this->iSubmitterId == $_SESSION['current']->iUserId &&
               $this->sState != 'accepted')
                return TRUE;

            $oVersion = new version($this->iVersionId);
            if($_SESSION['current']->hasAppVersionModifyPermission($oVersion))
                return TRUE;
            else
                return FALSE;
        } else
            return FALSE;
    }

    public function objectDisplayQueueProcessingHelp()
    {
        echo "<p>This is the list of test results waiting to be processed.</p>\n";
        echo "<p>To view and process an entry, use the links under &#8216;Action&#8217;</p>";
    }

    function objectShowPreview()
    {
        return TRUE;
    }

    function display()
    {
        $this->ShowTestResult();
        $iOldSubmitterId = $this->iSubmitterId;

        if(!$this->iSubmitterId)
	    $this->iSubmitterId = $_SESSION['current']->iUserId;

        $oTable = $this->CreateTestTable();

        $oTable->AddRow($this->CreateTestTableRow($this->iTestingId, ""));

        echo $oTable->GetString();

	$this->iSubmitterId = $iOldSubmitterId;
    }


    function objectMakeUrl()
    {
        $oObject = new objectManager("testData", "Edit Test Results", $this->iTestingId);
        return $oObject->makeUrl("edit", $this->iTestingId);
    }

    function objectMakeLink()
    {
        $oObject = new objectManager("testData", "Edit Test Results", $this->iTestingId);
        return '<a href="'.$oObject->makeUrl("edit", $this->iTestingId).'">test report</a>';
    }

    public function isOld()
    {
        /* If no id is defined that means the test report is not in the database, which means it can't be old */
        if(!$this->iTestingId)
            return false;

        return ((time() - mysqltimestamp_to_unixtimestamp($this->sSubmitTime)) > (60 * 60 * 24  * TESTDATA_AGED_THRESHOLD));
    }

    public function objectSetParent($iNewId, $sClass = 'version')
    {
        $this->iVersionId = $iNewId;
    }

    function objectGetParent()
    {
	return new version($this->iVersionId);
    }

    /* Only show children of (grand)parents in the Move Child Objects and Change Parent lists */
    public static function objectRestrictMoveObjectListsToParents()
    {
        return true;
    }

    function objectGetChildren($bIncludeDeleted = false)
    {
        /* We have none */
        return array();
    }

    function objectDisplayAddItemHelp()
    {
        echo "<p>This is the screen for inputing test information so that others ";
        echo "looking at the database will know \n";
        echo "what was working on a particular release of Wine.</p>\n";
        echo "<p><b>Please DO NOT include crash or Wine debug output.\n";
        echo " Instead report the crash as a bug in the Wine bugzilla at \n";
        echo "<a href=\"//bugs.winehq.org\">bugs.winehq.org</a>.\n";
        echo "We ask that you use bugzilla because developers do not monitor the AppDB \n";
        echo "for bugs.</b></p>\n"; 
        echo "<p>Please be as detailed as you can but do not paste large \n";
        echo "chunks of output from the terminal. Type out your report \n";
        echo "clearly and in proper English so that it is easily readable.</p>\n";
    }

    function mustBeQueued()
    {
        if($_SESSION['current']->hasPriv("admin"))
        {
            return FALSE;
        } else if($this->iVersionId)
        {
            // if the user can edit the version and the version isn't queued then
            // they can also submit test results without them being queued
            // this is the case where they maintain the version and the version isn't queued
            $oVersion = new version($this->iVersionId);
            if($oVersion->canEdit() && $oVersion->objectGetState() == 'accepted')
                return FALSE;
            else
                return TRUE;
        } else
        {
            return TRUE;
        }
    }

    function allowAnonymousSubmissions()
    {
        return FALSE;
    }

    function objectAllowPurgingRejected()
    {
        return TRUE;
    }

    public function objectGetSubmitTime()
    {
        return mysqltimestamp_to_unixtimestamp($this->sSubmitTime);
    }

    public static function objectGetItemsPerPage($sState = 'accepted')
    {
        $aItemsPerPage = array(25, 50, 100, 200);
        $iDefaultPerPage = 25;
        return array($aItemsPerPage, $iDefaultPerPage);
    }

    function objectGetId()
    {
        return $this->iTestingId;
    }

    function objectGetSubmitterId()
    {
        return $this->iSubmitterId;
    }
}

?>
