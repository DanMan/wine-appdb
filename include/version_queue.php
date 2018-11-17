<?php

class version_queue
{
    var $oTestDataQueue;
    var $oVersion;
    var $oDownloadUrl;

    function __construct($iVersionId = null, $oRow = null)
    {
        $this->oVersion = new version($iVersionId, $oRow);
        $iTestingId = null;
        $iDownloadUrlId = null;

        if(!$iVersionId)
            $iVersionId = $this->oVersion->iVersionId;

        if($iVersionId)
        {
            $iTestingId = testData::getNewestTestIdFromVersionId($iVersionId, "pending");
            /* This illustrates the importance of converting downloadurl completely
               to the objectManager model.  If we don't get  a match searching for
               a queued entry, try finding a rejected one. */
            if(($hResult = appData::getData($iVersionId, "downloadurl",
                                           TRUE, TRUE, FALSE)) ||
               $hResult = appData::getData($iVersionId, "downloadurl",
                                           TRUE, TRUE, TRUE))
            {
                if($oRow = query_fetch_object($hResult))
                    $iDownloadUrlId = $oRow->id;
            }
        }

        $this->oTestDataQueue = new testData_queue($iTestingId);
        $this->oDownloadUrl = new downloadurl($iDownloadUrlId);

        if(!$this->oDownloadUrl->objectGetId() && $iVersionId)
            $this->oDownloadUrl->objectSetParent($iVersionId);
    }

    function create()
    {
        global $aClean;
        if(!$this->oVersion->create())
            return FALSE;

        $this->oTestDataQueue->oTestData->iVersionId = $this->oVersion->iVersionId;
        $this->oTestDataQueue->create();
        $this->oDownloadUrl->objectSetParent($this->oVersion->objectGetId());
        $this->oDownloadUrl->create();

        return TRUE;
    }

    function reQueue()
    {
        $this->oDownloadUrl->reQueue();
    }

    function reject()
    {
        $oApp = new application($this->oVersion->iAppId);

        if($oApp->objectGetState() == 'accepted')
            $this->oVersion->reject();

        if($this->oDownloadUrl->iId)
            $this->oDownloadUrl->reject();
    }

    function update()
    {
        $this->oVersion->update();

        /* A downloadurl is optional and can thus be added later */
        if($this->oDownloadUrl->objectGetId())
            $this->oDownloadUrl->update();
        else
            $this->oDownloadUrl->create();

        $this->oTestDataQueue->update();
    }

    function purge()
    {
        $bSuccess = TRUE;

        if(!$this->oVersion->purge())
            $bSuccess = FALSE;

        if(!$this->oTestDataQueue->purge())
            $bSuccess = FALSE;

        if($this->oDownloadUrl->iId && !$this->oDownloadUrl->purge())
            $bSuccess = FALSE;

        return $bSuccess;
    }

    function delete()
    {
        $bSuccess = TRUE;

        if(!$this->oVersion->delete())
            $bSuccess = FALSE;

        if(!$this->oTestDataQueue->delete())
            $bSuccess = FALSE;

        if($this->oDownloadUrl->iId && !$this->oDownloadUrl->delete())
            $bSuccess = FALSE;

        return $bSuccess;
    }

    function unQueue()
    {
        $this->oVersion->unQueue();
        $this->oTestDataQueue->unQueue();
        $this->oDownloadUrl->unQueue();
    }

    function objectGetSubmitterId()
    {
        return $this->oVersion->objectGetSubmitterId();
    }

    function objectGetChildren($bIncludeDeleted = false)
    {
        return $this->oVersion->objectGetChildren($bIncludeDeleted);
    }

    function objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction)
    {
        return $this->oVersion->objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction);
    }

    function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        return $this->oVersion->objectGetMail($sAction, $bMailSubmitter, $bParentAction);
    }

    function outputEditor()
    {
        global $aClean;

        echo "<div class='default_container'>\n";

        /* Display duplicate list if this is a an existing version */
        if($this->oVersion->iVersionId)
            $this->displayMoveTestTable();

        $this->oVersion->outputEditor();

        /* Allow the user to apply as maintainer if this is a new version.
        If it is a new application as well, radio boxes will be displayed
        by the application class instead. */
        if(!$this->oVersion->iVersionId && $this->oVersion->iAppId &&
           !$_SESSION['current']->isSuperMaintainer($this->oVersion->iAppId))
        {
            echo html_frame_start("Become a Maintainer or Monitor Changes", "90%");
            echo "<div style='padding:5px;' class='color0'>\n";
            $oTable = new Table();
            if($this->oVersion->iMaintainerRequest == MAINTAINER_REQUEST)
                $sRequestMaintainerChecked = 'checked="checked" ';
            else
                $sRequestMaintainerChecked = '';
            if($this->oVersion->iMaintainerRequest == MONITOR_REQUEST)
                $sRequestMonitorChecked = 'checked="checked"' ;
            else
                $sRequestMonitorChecked = '';

            $oTableRow = new TableRow();
            $oTableRow->AddTextCell('&nbsp;');
            $oTableRow->AddTextCell("<input type=\"radio\" $sRequestMaintainerChecked".
                                     "name=\"iMaintainerRequest\" value=\"".MAINTAINER_REQUEST."\"> ".
                                     "Request being a maintainer for this version, allowing you to edit it later");
            $oTable->AddRow($oTableRow);
            $oTableRow = new TableRow();
            $oTableRow->AddTextCell('&nbsp;');
            $oTableRow->AddTextCell("<input type=\"radio\" $sRequestMonitorChecked".
                                     "name=\"iMaintainerRequest\" value=\"".MONITOR_REQUEST."\"> ".
                                     "Monitor changes to this version, also after it has been accepted");
            $oTable->AddRow($oTableRow);
            $oTableRow = new TableRow();
            $oTableRow->AddTextCell('&nbsp;');
            $oTableRow->AddTextCell('<input type="radio" name="iMaintainerRequest" value="0"> '.
                                    'None of the above');
            $oTable->AddRow($oTableRow);

            echo $oTable->GetString();
            echo "</div\n";
            echo html_frame_end();
        }

        echo $this->oDownloadUrl->outputEditorSingle($this->oVersion->iVersionId,
                $aClean);
        $this->oTestDataQueue->outputEditor();

        echo "</div>\n";
    }

    function getOutputEditorValues($aClean)
    {
        $this->oVersion->iAppId = $aClean['iAppId'];
        $this->oVersion->getOutputEditorValues($aClean);
        $this->oDownloadUrl->getOutputEditorValues($aClean);
        $this->oTestDataQueue->getOutputEditorValues($aClean);
    }

    function checkOutputEditorInput($aClean)
    {
        $sErrors = $this->oVersion->checkOutputEditorInput($aClean);
        $sErrors .= $this->oTestDataQueue->checkOutputEditorInput($aClean);
        return $sErrors;
    }

    function objectGetState()
    {
        return $this->oVersion->objectGetState();
    }

    function canEdit()
    {
        return $this->oVersion->canEdit();
    }

    function mustBeQueued()
    {
        return $this->oVersion->mustBeQueued();
    }

    function objectDisplayAddItemHelp()
    {
        /* $this->oVersion->displayAddItemHelp(); */
    }

    function objectGetItemsPerPage($sState = 'accepted')
    {
        return $this->oVersion->objectGetItemsPerPage($sState);
    }

    function objectGetEntriesCount($sState, $oFilters = null)
    {
        return $this->oVersion->objectGetEntriesCount($sState, $oFilters);
    }

    public static function objectGetDefaultSort()
    {
        return version::objectGetDefaultSort();
    }

    function objectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = "versionId", $bAscending = true, $oFilters = null)
    {
        return $this->oVersion->objectGetEntries($sState, $iRows, $iStart,
                                                 $sOrderBy, $bAscending,
                                                 $oFilters);
    }
 
    function objectGetHeader()
    {
        return $this->oVersion->objectGetHeader();
    }

    public function objectGetFilterInfo()
    {
        $oFilter = new FilterInterface();

        /* The following filters are only useful for admins */
        if(!$_SESSION['current']->hasPriv('admin'))
            return null;

        $oFilter->AddFilterInfo('onlyWithoutMaintainers', 'Only show versions without maintainers', array(FILTER_OPTION_BOOL), FILTER_VALUES_OPTION_BOOL, array('false','true'));

        $oFilter->AddFilterInfo('onlyMyMaintainedEntries', 'Only show versions for apps you maintain', array(FILTER_OPTION_BOOL), FILTER_VALUES_OPTION_BOOL, array('false','true'));

        return $oFilter;
    }

    function objectGetTableRow()
    {
        return $this->oVersion->objectGetTableRow();
    }

    public function objectShowPreview()
    {
        return $this->oVersion->objectShowPreview();
    }

    function display()
    {
        /* Cache the test result object as it's not in the DB */
        $this->oVersion->aTestResults[] = $this->oTestDataQueue->oTestData;
        $this->oVersion->display();
    }

    function objectMakeUrl()
    {
        return $this->oVersion->objectMakeUrl();
    }

    function objectMakeLink()
    {
        return $this->oVersion->objectMakeLink();
    }

    function displayMoveTestTable()
    {
        $oApp = new application($this->oVersion->iAppId);

        echo html_frame_start("Move test to version","90%","",0);
        echo "<table width=\"100%\" border=\"0\" cellpadding=\"3\" ".
                "cellspacing=\"1\">\n\n";

        echo html_tr(array(
                array("Version", 'width="80"'),
                "Description",
                array("Rating", 'width="80"'),
                array("Wine version", 'width="80"'),
                array("Comments", 'width="40"'),
                array("Move test results", 'width="80"')
                          ),
                "color4");

        $i = 0;
        foreach($oApp->aVersionsIds as $iVersionId)
        {
            $oVersion = new Version($iVersionId);
            if ($oVersion->objectGetState() == 'accepted')
            {
                //display row
                echo html_tr(array(
                        $oVersion->objectMakeLink(),
                        util_trim_description($oVersion->sDescription),
                        array($oVersion->sTestedRating, 'align="center"'),
                        array($oVersion->sTestedRelease, 'align="center"'),
                        array(Comment::get_comment_count_for_versionid(
                            $oVersion->iVersionId), 'align="center"'),
                        html_ahref("Move here",
                          "objectManager.php?sClass=version_queue&amp;bIsQueue=true&amp;".
                          "sAction=moveChildren&amp;iId=".
                          $this->oVersion->iVersionId."&amp;iNewId=".
                          $oVersion->iVersionId."&amp;sTitle=Version+Queue"),
                                  ),
                            ($i % 2) ? "color0" : "color1");

                $i++;
            }
        }
        echo "</table>\n";
        echo html_frame_end("&nbsp;");
    }

    function objectDisplayQueueProcessingHelp()
    {
        $this->oVersion->objectDisplayQueueProcessingHelp();
    }

    function objectMoveChildren($iNewId)
    {
        return $this->oVersion->objectMoveChildren($iNewId);
    }

    function allowAnonymousSubmissions()
    {
        return version::allowAnonymousSubmissions();
    }

    function objectAllowPurgingRejected()
    {
        return $this->oVersion->objectAllowPurgingRejected();
    }

    public function objectGetSubmitTime()
    {
        return $this->oVersion->objectGetSubmitTime();
    }

    function objectGetId()
    {
        return $this->oVersion->objectGetId();
    }
}

?>
