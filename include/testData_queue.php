<?php

class testData_queue
{
    var $oTestData;
    var $oDistribution;

    function testData_queue($iTestId = null, $oRow = null)
    {
        $this->oTestData = new testData($iTestId, $oRow);
        $this->oDistribution = new distribution($this->oTestData->iDistributionId);
    }

    function create()
    {
        if(!$this->oTestData->iDistributionId)
        {
            $this->oDistribution->create();
            $this->oTestData->iDistributionId = $this->oDistribution->iDistributionId;
        }

        return $this->oTestData->create();
    }

    function purge()
    {
        $bSuccess = $this->oTestData->purge();

        /* We delete the distribution if it has not been approved and is not associated
                with any other testData.  Otherwise we would have to have a distribution
                queue for admins to clean up unused, queued entries */
                $this->oDistribution = new distribution($this->oDistribution->iDistributionId);
        if(!sizeof($this->oDistribution->aTestingIds) &&
           $this->oDistribution->canEdit())
            $this->oDistribution->purge();

        return $bSuccess;
    }

    function delete()
    {
        $bSuccess = $this->oTestData->delete();

        /* We delete the distribution if it has not been approved and is not associated
           with any other testData.  Otherwise we would have to have a distribution
           queue for admins to clean up unused, queued entries */
        $this->oDistribution = new distribution($this->oDistribution->iDistributionId);
        if(!sizeof($this->oDistribution->aTestingIds) &&
           $this->oDistribution->canEdit())
            $this->oDistribution->delete();

        return $bSuccess;
    }

    function reQueue()
    {
        $this->oTestData->reQueue();

        $this->oDistribution->reQueue();
    }

    function unQueue()
    {
        $this->oTestData->unQueue();

        /* Avoid a misguiding message about the distribution being unqueued */
        if($this->oDistribution->objectGetState() != 'accepted')
            $this->oDistribution->unQueue();
    }

    function reject()
    {
        $this->oTestData->reject();
    }

    function update()
    {
        $this->oTestData->update();

        /* If the distribution was already un-queued the form for editing it would
           not have been displayed and getOutputEditorValues() wouldn't have
           retrieved a valid sName for the distribution. If sName isn't valid
           we shouldn't update the distribution */
        if($this->oDistribution->sName)
            $this->oDistribution->update();
    }

    function outputEditor()
    {
        $this->oTestData->outputEditor();
        
        echo "<table width='90%' border=0 cellpadding=2 cellspacing=0>\n";
        echo '<tr><td>';

        //a new test report, not yet queued
        if(!$this->oTestData->iTestingId)
        {
            echo '<b>Add new operating system:</b> use this form to add your operating system if it ';
            echo 'is not in the dropdown list above.';    
        }

        //queued test results with a queued distribution
        if($this->oDistribution->iDistributionId &&
            $this->oDistribution->objectGetState() != 'accepted' 
            && $this->canEdit()) 
        {
            echo '<div class="alert alert-danger" role="alert">';
            echo '<b>New operating system added:</b> You may correct this submission ';
            echo 'by selecting an operating system from the list above ';
            echo 'or editing the information in the textboxes below. ';
            echo '</div>';
        }  
        /* If the testData is already associated with a distribution and the
           distribution is un-queued, there is no need to display the
           distribution form. */
        if(!$this->oTestData->iDistributionId || 
            $this->oDistribution->objectGetState() != 'accepted')
            echo $this->oDistribution->outputEditor();
        
        echo '</tr></td></table>';     
    }

    function getOutputEditorValues($aClean)
    {
        $this->oTestData->getOutputEditorValues($aClean);
        $this->oDistribution->getOutputEditorValues($aClean);
    }

    function checkOutputEditorInput($aClean)
    {
        return $this->oTestData->checkOutputEditorInput($aClean);
    }

    function objectGetState()
    {
        return $this->oTestData->objectGetState();
    }

    function canEdit()
    {
        return $this->oTestData->canEdit();
    }

    function mustBeQueued()
    {
        return $this->oTestData->mustBeQueued();
    }

    function objectDisplayAddItemHelp()
    {
        $this->oTestData->objectDisplayAddItemHelp();
    }

    public static function objectGetDefaultSort()
    {
        return testData::objectGetDefaultSort();
    }

    public function objectGetFilterInfo()
    {
        return testData::objectGetFilterInfo();
    }

    function objectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = "testingId", $bAscending = true, $oFilters = null)
    {
        return $this->oTestData->objectGetEntries($sState, $iRows, $iStart, $sOrderBy, $bAscending, $oFilters);
    }

    function objectGetEntriesCount($sState, $oFilters = null)
    {
        return testData::objectGetEntriesCount($sState, $oFilters);
    }

    function objectGetHeader()
    {
        return $this->oTestData->objectGetHeader();
    }

    function objectGetTableRow()
    {
        return $this->oTestData->objectGetTableRow();
    }

    function objectDisplayQueueProcessingHelp()
    {
        $oTest = new testData();
        $oTest->objectDisplayQueueProcessingHelp();
    }

    function objectShowPreview()
    {
        if(!$this->oTestData->iTestingId)
            return FALSE;
        else
            return $this->oTestData->objectShowPreview();
    }

    function display()
    {
        return $this->oTestData->display();
    }

    function objectMakeUrl()
    {
        return $this->oTestData->objectMakeUrl();
    }

    function objectMakeLink()
    {
        return $this->oTestData->objectMakeLink();
    }

    function allowAnonymousSubmissions()
    {
        return testData::allowAnonymousSubmissions();
    }

    function objectAllowPurgingRejected()
    {
        return $this->oTestData->objectAllowPurgingRejected();
    }

    public function objectGetSubmitTime()
    {
        return $this->oTestData->objectGetSubmitTime();
    }

    function objectGetItemsPerPage($sState = 'accepted')
    {
        return testData::objectGetItemsPerPage($sState);
    }

    function objectGetId()
    {
        return $this->oTestData->objectGetId();
    }

    function objectGetSubmitterId()
    {
        return $this->oTestData->objectGetSubmitterId();
    }

    function objectGetChildren($bIncludeDeleted = false)
    {
        return $this->oTestData->objectGetChildren($bIncludeDeleted);
    }

    function objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction)
    {
        return $this->oTestData->objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction);
    }

    function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        return $this->oTestData->objectGetMail($sAction, $bMailSubmitter, $bParentAction);
    }
}

?>
