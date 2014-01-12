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
    var $iDistributionId;
    var $sTestedDate;
    var $sInstalls;
    var $sRuns;
    var $sTestedRating;
    var $sComments;
    var $sSubmitTime;
    var $iSubmitterId;
    private $sState;

     // constructor, fetches the data.
    function testData($iTestingId = null, $oRow = null)
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
            $this->sInstalls = $oRow->installs;
            $this->sRuns = $oRow->runs;
            $this->sTestedRating = $oRow->testedRating;
            $this->sComments = $oRow->comments;
            $this->sSubmitTime = $oRow->submitTime;
            $this->iSubmitterId = $oRow->submitterId;
            $this->sState = $oRow->state;
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
                                    "whatNotTested, testedDate, distributionId, testedRelease,".
                                    "installs, runs, testedRating, comments,".
                                    "submitTime, submitterId, state)".
                                    "VALUES('?', '?', '?', '?', '?', '?', '?',".
                                    "'?', '?', '?', '?',".
                                    "?, '?', '?')",
                                    $this->iVersionId, $this->shWhatWorks,
                                    $this->shWhatDoesnt,
                                    $this->shWhatNotTested, $this->sTestedDate,
                                    $this->iDistributionId,
                                    $this->sTestedRelease, $this->sInstalls,
                                    $this->sRuns,
                                    $this->sTestedRating, $this->sComments,
                                    "NOW()",
                                    $_SESSION['current']->iUserId,
                                    $this->sState);

        if($hResult)
        {
            $this->iTestingId = query_appdb_insert_id();
            $this->testData($this->iTestingId);
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

        if($this->shWhatNotTested != $oOldTest->shWhatNotTested)
        {
            $sWhatChanged .= "What was not tested was changed from\n'".
                    $oOldTest->shWhatNotTested."'\nto\n'$this->shWhatNotTested'.\n";
        }

        if($this->sComments != $oOldTest->sComments)
        {
            $sWhatChanged .= "Extra comments was changed from\n'".
                    $oOldTest->sComments."'\nto\n'$this->sComments'.\n";
        }

        if($this->iDistributionId != $oOldTest->iDistributionId)
        {
            $oNewDist = new distribution($this->iDistributionId);
            $oOldDist = new distribution($oOldTest->iDistributionId);
            $sWhatChanged .= "Distribution was changed from $oOldDist->sName ".
                    "to $oNewDist->sName.\n";
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
                                        installs        = '?',
                                        runs            = '?',
                                        testedRating    = '?',
                                        comments        = '?',
                                        state           = '?'
                                    WHERE testingId = '?'",
                            $this->iVersionId,
                            $this->shWhatWorks,
                            $this->shWhatDoesnt,
                            $this->shWhatNotTested,
                            $this->sTestedDate,
                            $this->iDistributionId,
                            $this->sTestedRelease,
                            $this->sInstalls,
                            $this->sRuns,
                            $this->sTestedRating,
                            $this->sComments,
                            $this->sState,
                            $this->iTestingId))
        {
            if($bUpdateRatingInfo && $this->sState == 'accepted')
            {
                if($this->iVersionId != $oOldTest->iVersionId)
                {
                    $oNewVersion = new version($this->iVersionId);
                    $oNewVersion->updateRatingInfo();
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
        echo '<p><b>What works</b><br>',"\n";
        echo $this->shWhatWorks,"\n";
        echo '<p><br><b>What does not</b><br>',"\n";
        echo $this->shWhatDoesnt,"\n";
        echo '<p><br><b>What was not tested</b><br>',"\n";
        echo $this->shWhatNotTested,"\n";
        echo '<p><br><b>Additional Comments</b><br><pre>',"\n";
        echo $this->sComments,"\n";
        echo '</pre>',"\n";
    }

    function CreateTestTable()
    {
        echo '<div class="info_container">',"\n";
        echo '<div class="title_class">Test Results</div>',"\n";
        echo '<div class="info_contents">',"\n";

        // create the table
        $oTable = new Table();
        $oTable->SetClass("historyTable");
        $oTable->SetBorder(1);
        $oTable->SetWidth("100%");

        // setup the table header
        $oTableRowHeader = new TableRow();
        $oTableRowHeader->SetClass("historyHeader");
        $oTableRowHeader->AddTextCell("");
        $oTableRowHeader->AddTextCell("Distribution");
        $oTableRowHeader->AddTextCell("Test date");
        $oTableRowHeader->AddTextCell("Wine version");
        $oTableRowHeader->AddTextCell("Installs?");
        $oTableRowHeader->AddTextCell("Runs?");
        $oTableRowHeader->AddTextCell("Rating");
        $oTableRowHeader->AddTextCell("Submitter");
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
        } else /* make all non-current rows clickable so clicking on them selects the test as current */
        {
            $sTRClass = $bgcolor;

            $oInactiveColor = new color();
            $oInactiveColor->SetColorByName($this->sTestedRating);

            $oHighlightColor = GetHighlightColorFromInactiveColor($oInactiveColor);

            $oTableRowHighlight = new TableRowHighlight($oHighlightColor, $oInactiveColor);

            $sUrl = $sLink.$this->iTestingId;

            if($bShowAll)
                $sUrl .= '&bShowAll=true';

            $oTableRowClick = new TableRowClick($sUrl);
            $oTableRowClick->SetHighlight($oTableRowHighlight);

            // add the table element indicating that the user can show the row by clicking on it
            $oTableCell = new TableCell("Show");
            $oTableCell->SetCellLink($sUrl);
            $oTableCell->SetAlign("center");
        }

        $oTableRow->AddCell($oTableCell);
        $oTableRow->SetClass($sTRClass);

        $oTableRow->AddTextCell($oDistribution->objectMakeLink());
        $oTableRow->AddTextCell(date("M d Y", mysqldatetime_to_unixtimestamp($this->sTestedDate)));
        $oTableRow->AddTextCell($this->sTestedRelease.'&nbsp;');
        $oTableRow->AddTextCell($this->sInstalls.'&nbsp;');
        $oTableRow->AddTextCell($this->sRuns.'&nbsp;');
        $oTableRow->AddTextCell($this->sTestedRating.'&nbsp;');
        $oTableRow->AddTextCell($oSubmitter->objectMakeLink().'&nbsp;');
        if ($this->iTestingId && $_SESSION['current']->hasAppVersionModifyPermission($oVersion))
        {
            $oObject = new objectManager('testData');
            if($oApp->canEdit())
                $shChangeParentLink = '<a href="'.$oObject->makeUrl('showChangeParent', $this->iTestingId, 'Move test report to another version').'&amp;sReturnTo='.urlencode($_SERVER['REQUEST_URI']).'">Move</a>'."\n";
            else
                $shChangeParentLink = '';

            $oTableRow->AddTextCell('<a href="'.$oObject->makeUrl('edit', $this->iTestingId,
                                    'Edit Test Results').'&amp;sReturnTo='.urlencode($_SERVER['REQUEST_URI']).'">'.
                                    'Edit</a> &nbsp; '."\n".
                                    $shChangeParentLink.
                                    '<a href="'.$oObject->makeUrl('delete', $this->iTestingId, 'Delete+Test+Results').
                                    '&amp;sReturnTo='.urlencode($_SERVER['REQUEST_URI']).'">Delete</a></td>'."\n");
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

        echo $oTable->GetString();

        echo '<br>',"\n"; // put a space after the test results table and the button

        echo '<form method=get action="objectManager.php">'."\n";

        if($rowsUsed >= $iDisplayLimit && $bShowAll)
        {
            $sShowButtonText = "Limit to $iDisplayLimit tests";
        } else
        {
            $sShowButtonText = "Show all tests";
            echo '<input type="hidden" name="bShowAll" value="true">';
        }

        $oManager = new objectManager("version", null, $this->iVersionId);

        echo $oManager->makeUrlFormData();

        echo "\t".'<input class="button" type=submit value="'.$sShowButtonText.'">'."\n";

        echo '</form>'."\n";

        echo '</div>',"\n"; // end of the 'info_contents' div
        echo '</div>',"\n"; // end of the 'info_container' div
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
       1 - Wine version
       The $sDate parameter can be used to calculate the rating at a given point in time */
    public static function getRatingInfoForVersionId($iVersionId, $sDate = 'NOW()')
    {
        $sQuery = "SELECT testedRating,testedDate,testedRelease,versions.id as versionId
                FROM testResults, ?.versions WHERE
                versions.value = testResults.testedRelease
                AND
                versions.product_id = '?'
                AND versionId = '?'
                AND
                state = '?'
                AND
                TO_DAYS(testedDate) > (TO_DAYS(?) - ?)
                    ORDER BY versions.id DESC,testedDate DESC";

        $hResult = query_parameters($sQuery, BUGZILLA_DB, BUGZILLA_PRODUCT_ID, $iVersionId, 'accepted', $sDate, TESTDATA_AGED_THRESHOLD);

        $aEntries = array();

        if($hResult)
        {
            $iPrevRelease = 0;
            $iIndex = -1;
            for($i = 0; $oRow = mysql_fetch_object($hResult); $i++)
            {
                if($iPrevRelease != $oRow->versionId)
                {
                    $iIndex++;
                    $iPrevRelease = $oRow->versionId;
                }

                if(!$aEntries[$iIndex])
                {
                    $aEntries[$iIndex] = array();
                    $aEntries[$iIndex][0] = 0;
                    $aEntries[$iIndex][1] = 0;
                    $aEntries[$iIndex][2] = $oRow->testedRelease;
                }

                $aEntries[$iIndex][0] += testData::RatingToNumber($oRow->testedRating);
                $aEntries[$iIndex][1]++;
            }
        }

        $sRelease = '';

        if(sizeof($aEntries))
        {
            $fRating = 0.0;

            for($i = 0; $i < sizeof($aEntries); $i++)
            {
                /* Discard the rating if it's the only one for that Wine version
                   and its score is lower than previous averages */
                if(($aEntries[$i][1] < 2) && sizeof($aEntries) > ($i+1) && ($aEntries[$i][0] < ($aEntries[$i+1][0] / $aEntries[$i+1][1])))
                    continue;

                $fRating = $aEntries[$i][0] / $aEntries[$i][1];
                $sRelease = $aEntries[$i][2];
                break;
            }

            $sRating = testData::NumberToRating(round($fRating, 0));
        }

        if(!$sRelease)
        {
            $iNewestId = testData::getNewestTestIdFromVersionId($iVersionId);
            $oTestData = new testData($iNewestId);
            return array($oTestData->sTestedRating, $oTestData->sTestedRelease);
        }
        return array($sRating,$sRelease);
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

        HtmlAreaLoaderScript(array("Test1", "Test2", "Test3"));

        $sName = version::fullName($this->iVersionId);

        echo html_frame_start("Test Form - $sName", "90%", "", 0);
        echo "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";

        // What works
        echo '<tr valign=top><td class="color0"><b>What works</b></td>',"\n";
        echo '<td class="color0"><p><textarea cols="80" rows="20" id="Test1" name="shWhatWorks">';
        echo $this->shWhatWorks.'</textarea></p></td></tr>',"\n";
        // What Does not work
        echo '<tr valign=top><td class=color1><b>What does not work</b></td>',"\n";
        echo '<td class="color0"><p><textarea cols="80" rows="20" id="Test2" name="shWhatDoesnt">';
        echo $this->shWhatDoesnt.'</textarea></p></td></tr>',"\n";
        // What was not tested
        echo '<tr valign=top><td class=color0><b>What was not tested</b></td>',"\n";
        echo '<td class="color0"><p><textarea cols="80" rows="20" id="Test3" name="shWhatNotTested">';
        echo $this->shWhatNotTested.'</textarea></p></td></tr>',"\n";
        // Date Tested
        echo '<tr valign=top><td class="color1"><b>Date tested </b></td>',"\n";
        echo '<td class="color0"><input type=text name="sTestedDate" value="'.$this->sTestedDate.'" size="20"></td></tr>',"\n";
        echo '<tr valign=top><td class="color1"></td><td class="color0"><p/>YYYY-MM-DD HH:MM:SS</td></tr>',"\n";

        // Distribution
        $oDistribution = new distribution($this->iDistributionId);
        $sDistributionHelp = "";
        if(!$this->iDistributionId || $oDistribution->objectGetState() != 'accepted')
        {
            if(!$this->iDistributionId)
            {
                $sDistributionHelp = "If yours is not on the list, ".
                                     "please add it using the form below.";
            } else
            {
                $sDistributionHelp = "The user added a new distribution; ";
                                     "review it in the form below or replace ".
                                     "it with one from the list.";
            }
            $sDistributionHelp .= "<br>\n";
        }

        echo '<tr valign=top><td class="color0"><b>Distribution</b></td class="color0">',"\n";

        echo '<td class=color0>',"\n";
        echo $sDistributionHelp;
        distribution::make_distribution_list("iDistributionId", $this->iDistributionId);
        echo '</td></tr>',"\n";

        // Version List
        echo '<tr><td class=color1><b>Tested release</b></td><td class=color0>',"\n";
        echo make_bugzilla_version_list("sTestedRelease", $this->sTestedRelease);
        // Give the user some information about our available versions
        echo "<span>Version not listed?  Your Wine is too old, <a href=\"//winehq.org/download\">upgrade!</a></span>";
        echo '</td></tr>',"\n";

        // Installs
        echo '<tr><td class=color0><b>Installs?</b></td><td class=color0>',"\n";
        testData::make_Installs_list("sInstalls", $this->sInstalls);
        echo '&nbsp; Installing is an important part of testing under Wine. Select N/A if there is no installer.</td></tr>',"\n";
        // Runs
        echo '<tr><td class=color1><b>Runs?</b></td><td class=color0>',"\n";
        testData::make_Runs_list("sRuns", $this->sRuns);
        echo '</td></tr>',"\n";
        // Rating
        echo '<tr><td class="color0"><b>Rating</b></td><td class="color0">',"\n";
        echo make_maintainer_rating_list("sTestedRating", $this->sTestedRating);
        echo '<a href="'.BASE.'/help/?sTopic=maintainer_ratings" target="_blank">Rating definitions</a></td></tr>',"\n";
        // extra comments
        echo '<tr valign=top><td class="color1"><b>Extra comments</b></td>',"\n";
        echo '<td class="color0"><textarea name="sComments" id="extra_comments" rows=10 cols=65>';
        echo $this->sComments.'</textarea></td></tr>',"\n";

        echo '<input type="hidden" name="iVersionId" value="'.$this->iVersionId.'" >';
        echo '<input type="hidden" name="iTestingId" value="'.$this->iTestingId.'" >';
        echo '<input type="hidden" name="iTestDataId" value="'.$this->iTestingId.'" >';

        // Display confirmation box for changing the Wine version
        $oOldTest = new testData($this->iTestingId);
        if($this->iTestingId && $oOldTest->sTestedRelease != $this->sTestedRelease)
        {
            if(getInput('bConfirmTestedVersionChange', $aClean) != 'true')
            {
                echo '<tr><td class="color1">&nbsp;</td><td class="color0">';
                echo 'You have changed the Wine version of the report.  Are you sure you want to do this?  Please submit a new test report for every Wine version you test; this is useful for tracking Wine\'s progress.<br>';
                echo '<input type="checkbox" name="bConfirmTestedVersionChange" value="true"> ';
                echo 'Yes, I want to change the Wine version';
                echo '</td></tr>';
            } else
            {
                echo '<input type="hidden" name="bConfirmTestedVersionChange" value="true">';
            }
        }

        echo "</table>\n";

        echo html_frame_end();
    }

    /* $aValues can be $aValues or any array with the values from outputEditor() */
    function CheckOutputEditorInput($aValues, $sDistribution="")
    {
        $errors = "";
        if (empty($aValues['shWhatWorks']))
            $errors .= "<li>Please enter what worked.</li>\n";

        /* The 'what doesn't work' field can be empty if the rating is Platinum,
           because then an app should run flawlessly */
        if (!getInput('shWhatDoesnt', $aValues) &&
            getInput('sTestedRating', $aValues) != PLATINUM_RATING)
            $errors .= "<li>Please enter what did not work.</li>\n";

        if (empty($aValues['shWhatNotTested']))
            $errors .= "<li>Please enter what was not tested.</li>\n";

        if (empty($aValues['sTestedDate']))
            $errors .= "<li>Please enter the date and time when you tested.</li>\n";

        if (empty($aValues['sTestedRelease']))
            $errors .= "<li>Please enter the version of Wine that you tested with.</li>\n";

        // Ask for confirmation if changing the tested Wine versions, becase we want users
        // to submit new reports instead of updating existing ones when testing new Wines
        $oOldTest = new testData($this->iTestingId);
        if($this->iTestingId && $oOldTest->sTestedRelease != getInput('sTestedRelease', $aValues) &&
           getInput('bConfirmTestedVersionChange', $aValues) != 'true')
        {
            $errors .= '<li>Are you sure you want to change the Wine version of the report? Please submit a new '.
                        'test report for every Wine version you test; this is useful for tracking Wine\'s progress. '.
                        'Tick the box above the submit button if you want to proceed</li>';
        }

        // No Distribution entered, and nothing in the list is selected
        if (empty($aValues['sDistribution']) && !$aValues['iDistributionId'])
            $errors .= "<li>Please enter a distribution.</li>\n";

        if (empty($aValues['sInstalls']))
            $errors .= "<li>Please enter whether this application installs or not.</li>\n";

        if (empty($aValues['sRuns']))
            $errors .= "<li>Please enter whether this application runs or not.</li>\n";

        if (empty($aValues['sTestedRating']))
            $errors .= "<li>Please enter a rating based on how well this application runs.</li>\n";

        // Basic checking of rating logic to ensure that the users test results
        // are consistent
        if (($aValues['sRuns'] != "Yes") && ($aValues['sTestedRating'] != GARBAGE_RATING))
            $errors .= "<li>Applications that do not run should be rated &#8216;Garbage&#8217;.</li>\n";

        if (($aValues['sInstalls'] == "No" || $aValues['sInstalls'] == 'No, but has workaround') && ($aValues['sTestedRating'] == PLATINUM_RATING))
            $errors .= "<li>An application can only get a Platinum rating if it installs and runs &#8216;out of the box&#8217;.</li>\n";

        // Basic checking of logic.  Runs? can obviously only be 'Not Installable'
        // if the application does not install
        if (($aValues['sInstalls'] != "No") && ($aValues['sRuns'] == "Not installable"))
            $errors .= "<li>You can only set Runs? to &#8216;Not installable&#8217; if Installs? is set &#8216;No&#8217;</li>\n";
	    
	if (($aValues['sInstalls'] == "No") && ($aValues['sRuns'] != "Not installable"))
            $errors .= "<li>Runs? must be set to &#8216;Not installable&#8217; if there is no way to install the app</li>\n";

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
        $this->shWhatNotTested = $aValues['shWhatNotTested'];
        $this->sTestedDate = $aValues['sTestedDate'];
        $this->iDistributionId = $aValues['iDistributionId'];
        $this->sTestedRelease = $aValues['sTestedRelease'];
        $this->sInstalls = $aValues['sInstalls'];
        $this->sRuns = $aValues['sRuns'];
        $this->sTestedRating = $aValues['sTestedRating'];
        $this->sComments = $aValues['sComments'];
    }

    function make_Installs_list($sVarname, $sSelectedValue)
    {
        echo "<select name='$sVarname'>\n";
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
        echo "<select name='$sVarname'>\n";
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

        if(!mysql_num_rows($hResult))
            return $aRet;

        while(($oRow = mysql_fetch_object($hResult)))
            $aRet[] = new testData(0, $oRow);

        return $aRet;
    }

    /* List test data submitted by a given user.  Ignore test results for queued applications/versions */
    public static function listSubmittedBy($iUserId, $bQueued = true)
    {
        $hResult = query_parameters("SELECT testResults.versionId, testResults.testedDate, testResults.testedRelease, testResults.testedRating, testResults.submitTime, testResults.testingId, appFamily.appName, appVersion.versionName from testResults, appFamily, appVersion WHERE testResults.versionId = appVersion.versionId AND appVersion.appId = appFamily.appId  AND testResults.submitterId = '?' AND testResults.state = '?' ORDER BY testResults.testingId", $iUserId, $bQueued ? 'queued' : 'accepted');

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
        $oTableRow->AddCell(new TableCell($this->sTestedRelease));
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

        return ((mktime() - mysqltimestamp_to_unixtimestamp($this->sSubmitTime)) > (60 * 60 * 24  * TESTDATA_AGED_THRESHOLD));
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
