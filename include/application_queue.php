<?php

/**
 * Class for submitting/processing applications
 */

class application_queue
{
    var $oVersionQueue;
    var $oApp;
    var $oVendor;

    function __construct($iAppId = null, $oRow = null)
    {
        $this->oApp = new application($iAppId, $oRow);

        $iVersionId = null;
        $iVendorId = null;

        /* If this is an existing application then there must be a version
           accompanying it */
        if($this->oApp->iAppId)
        {
            /* Normal users do not get a aVersionsIds property, so we have to fetch
               the versionId manually.  Normal users only have access to rejected
               applications, unless they submitted them */
            if($_SESSION['current']->hasPriv("admin"))
            {
                $iVersionId = $this->oApp->aVersionsIds[0];
            } else if($this->oApp->objectGetState() == 'rejected' ||
                      ($this->oApp->objectGetState() == 'queued' &&
                       $this->oApp->objectGetSubmitterId() == $_SESSION['current']->iUserId))
            {
                $sQuery = "SELECT versionId FROM appVersion WHERE appId = '?' LIMIT 1";
                $hResult = query_parameters($sQuery, $this->oApp->iAppId);
                if($hResult)
                {
                    if($oRow = query_fetch_object($hResult))
                        $iVersionId = $oRow->versionId;
                }
            }
            $iVendorId = $this->oApp->iVendorId;
        }

        $this->oVendor = new vendor($iVendorId);

        $this->oVersionQueue = new version_queue($iVersionId);
    }

    function create()
    {
        $bSuccess = TRUE;

        /* Create a new vendor if an existing one was not selected, and
           assign the application to it */
        if(!$this->oApp->iVendorId)
        {
            $this->oVendor->create();
            $this->oApp->iVendorId = $this->oVendor->iVendorId;
        }

        if(!$this->oApp->create())
            $bSuccess = FALSE;

        /* Assign the version to the new application */
        $this->oVersionQueue->oVersion->iAppId = $this->oApp->iAppId;

        if(!$this->oVersionQueue->create())
            $bSuccess = FALSE;

        return $bSuccess;
    }

    function update()
    {
        $bSuccess = TRUE;

        /* If the vendor was already un-queued then the edit vendor form would not
           have been displayed, and so the vendor name will not be set.  Thus only
           update the vendor if the name is set */
        if($this->oVendor->sName)
            $this->oVendor->update();

        if(!$this->oApp->update())
            $bSuccess = FALSE;

        if(!$this->oVersionQueue->update())
            $bSuccess = FALSE;

        return $bSuccess;
    }

    function unQueue()
    {
        /* The vendor is not necessarily queued, as it could have existed on
           beforehand */
        if($this->oVendor->objectGetState() != 'accepted')
            $this->oVendor->unQueue();

        $this->oApp->unQueue();
        $this->oVersionQueue->unQueue();

        /* Has anyone submitted new versions while the app was queued?
           If so we need to change their state from pending to queued */
        $aOtherVersions = $this->oApp->objectGetChildrenClassSpecific('version');
        foreach($aOtherVersions as $oVersion)
        {
            if($oVersion->objectGetState() == 'pending')
            {
                $oVersion->objectSetState('queued');
                $oVersion->update();
            }
        }
    }

    function reQueue()
    {
        $this->oApp->reQueue();
        $this->oVersionQueue->reQueue();
    }

    function reject()
    {
        $this->oVersionQueue->reject();
        $this->oApp->reject();
    }

    function purge()
    {
        $bSuccess = TRUE;

        if(!$this->oApp->purge())
            $bSuccess = FALSE;

        /* When deleting a duplicate app in the application queue, the version is moved
                to another app and so when application_queue::delete() is called there is
                no version child to delete, so check if the versionId is valid */
                if($this->oVersionQueue->oVersion->iVersionId)
        {
            if(!$this->oVersionQueue->purge())
                $bSuccess = FALSE;
        }

        return $bSuccess;
    }

    function delete()
    {
        $bSuccess = TRUE;

        if(!$this->oApp->delete())
            $bSuccess = FALSE;

        /* When deleting a duplicate app in the application queue, the version is moved
           to another app and so when application_queue::delete() is called there is
           no version child to delete, so check if the versionId is valid */
        if($this->oVersionQueue->oVersion->iVersionId)
        {
            if(!$this->oVersionQueue->delete())
                $bSuccess = FALSE;
        }

        return $bSuccess;
    }

    function objectGetChildren($bIncludeDeleted = false)
    {
        return $this->oApp->objectGetChildren($bIncludeDeleted);
    }

    function objectGetSubmitterId()
    {
        return $this->oApp->objectGetSubmitterId();
    }

    function objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction)
    {
        return $this->oApp->objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction);
    }

    function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        return $this->oApp->objectGetMail($sAction, $bMailSubmitter, $bParentAction);
    }

    public function objectShowPreview()
    {
        if($this->oApp->sName)
            return TRUE;

        return FALSE;
    }

    function outputEditor($aClean = array())
    {
        /* We ask the user for the application name first so as to avoid duplicate
           submissons; a list of potential duplicates is displayed on the next page */
        if(!$this->oApp->sName)
        {
            echo "<div style='margin:auto; width: 500px; border:1px solid; background-color:#eee; padding:2px; '>\n";
            echo "<div style='font-weight:bold; padding:3px;'>\n";
            echo "Application name:\n";
            echo "</div>\n";
            echo "<div style='padding:5px;'>\n";
            echo "<center><input type=\"text\" name=\"sAppName\" style='width:485px;'></center>\n";
            echo "</div>\n";
            echo "<input type=\"hidden\" name=\"sSub\" value=\"view\">\n";
            echo "<input type=\"hidden\" name=\"sAppType\" value=\"application\">\n";
            echo "</div>\n";
        } else
        {
            /* Show potential duplicates */
            echo html_frame_start("Potential duplicate applications in the ".
                    "database","90%","",0);
            $this->displayDuplicates();
            echo html_frame_end("&nbsp;");

            $this->oApp->outputEditor();

            /* Display the new vendor form for new applications or if we
               are processing an application and the vendor is queued */
            if(!$this->oApp->iAppId || $this->oVendor->objectGetState() != 'accepted')
            {
                echo html_frame_start("New Developer", "90%");
                $this->oVendor->outputEditor();
                echo html_frame_end();
            }

            $this->oVersionQueue->oVersion->outputEditor();

            global $aClean;

            echo $this->oVersionQueue->oDownloadUrl->outputEditorSingle(
                    $this->oVersionQueue->oVersion->iVersionId, $aClean);

            $this->oVersionQueue->oTestDataQueue->outputEditor();

            /* Allow the user to choose whether to preview the application view
               or the version view.  Application view is default */
            echo html_frame_start("Select What to Preview");
            $sPreviewVersion = $aClean['bPreviewVersion'] ? $aClean['bPreviewVersion'] : "";

            $shPreviewApp = '';
            $shPreviewVersion = '';

            if($sPreviewVersion == "true")
                $shPreviewVersion = ' checked="checked"';
            else
                $shPreviewApp = ' checked="checked"';

            echo "<input type=\"radio\" name=\"bPreviewVersion\"$shPreviewApp value=\"false\"> Preview application<br>\n";
            echo "<input type=\"radio\" name=\"bPreviewVersion\"$shPreviewVersion value=\"true\"> Preview version\n";
            echo html_frame_end();
        }
    }

    function getOutputEditorValues($aClean)
    {
        $this->oApp->getOutputEditorValues($aClean);
        $this->oVersionQueue->getOutputEditorValues($aClean);
        $this->oVendor->getOutputEditorValues($aClean);
    }

    function checkOutputEditorInput($aClean)
    {
        /* We want outputEditor() to be called again so we can display the main
           app form.  No erros are displayed since we only return TRUE */
        if($this->oApp->sName && !$aClean['bMainAppForm'])
            return TRUE;

        $sErrors = $this->oApp->checkOutputEditorInput($aClean);
        $sErrors .= $this->oVersionQueue->checkOutputEditorInput($aClean);
        return $sErrors;
    }

    function objectGetState()
    {
        return $this->oApp->objectGetState();
    }

    function canEdit()
    {
        return $this->oApp->canEdit();
    }

    function mustBeQueued()
    {
        return $this->oApp->mustBeQueued();
    }

    function displayDuplicates()
    {
        echo "<b>Like matches</b><br />\n";
        $this->displayDuplicateTable(searchForApplication($this->oApp->sName, $this->oApp->objectGetId()));
        echo "<br />\n";
        echo "<b>Partial matches</b><br />\n";
        $this->displayDuplicateTable(searchForApplicationPartial($this->oApp->sName, $this->oApp->objectGetId()));
        echo '<br /><br />';
        if($this->oApp->iAppId && $this->oApp->canEdit())
        {
            echo "<a href=\"objectManager.php?sClass=application&amp;".
                 "bIsQueue=true&amp;sAction=showMoveChildren&amp;iId=".
                 $this->oApp->iAppId.
                 "\">Merge with another application</a>";
        }
    }

    function displayDuplicateTable($hResult)
    {
        /* Exit if the MySQL handle is invalid */
        if($hResult === FALSE)
            return FALSE;

        /* There's no point in displaying an empty table */
        if($hResult === null || (query_num_rows($hResult) == 0))
        {
            echo "No matches.<br>\n";
            return;
        }

        $aHeader =  array(
                "Application name",
                "Description",
                "No. versions"
                          );

        /* We can only move data if the current application already exists, and
           we have admin privileges */
        if($this->oApp->iAppId && $_SESSION['current']->hasPriv("admin"))
        {
            $bCanMove = TRUE;
            $aHeader[] = array("Move data", 'width="80"');
        } else
        {
            $bCanMove = FALSE;
        }

        echo "<table cellpadding='5px'>";
        echo html_tr($aHeader, "color4");

        for($i = 0; $oRow = query_fetch_object($hResult); $i++)
        {
            $oApp = new application($oRow->appId);
            $aCells = array(
                    $oApp->objectMakeLink(),
                    util_trim_description($oApp->sDescription),
                    sizeof($oApp->aVersionsIds)
                                   );

            if($bCanMove)
            {
                $aCells[] = "<a href=\"objectManager.php?sClass=application_queue&amp;".
                        "bIsQueue=true&amp;sAction=moveChildren&amp;iId=".
                        $this->oApp->iAppId."&amp;iNewId=".$oApp->iAppId.
                        "\">Move data</a>";
            }
            echo html_tr($aCells, ($i % 2) ? "color0" : "color1");
        }
        echo "</table>";
    }

    function objectGetCustomVars($sAction)
    {
        switch($sAction)
        {
            case "preview":
                return array("bPreviewVersion");

            default:
                return 0;
        }
    }

    function display($aClean = array())
    {
        /* Cache the version object if it is not in the database */
        if(!$this->oVersionQueue->objectGetId())
            $this->oApp->aVersions = array($this->oVersionQueue->oVersion);

        $sPreviewVersion = $aClean['bPreviewVersion'] ? $aClean['bPreviewVersion'] : "";

        if($sPreviewVersion == "true")
        {
            $this->oVersionQueue->oVersion->oApp = $this->oApp;
            $this->oVersionQueue->display();
        } else
        {
            $this->oApp->display();
        }
    }

    function objectMakeUrl()
    {
        return $this->oApp->objectMakeUrl();
    }

    function objectMakeLink()
    {
        return $this->oApp->objectMakeLink();
    }

    function objectGetItemsPerPage($sState = 'accepted')
    {
        return $this->oApp->objectGetItemsPerPage($sState);
    }

    function objectGetEntriesCount($sState)
    {
        return $this->oApp->objectGetEntriesCount($sState);
    }

    public static function objectGetDefaultSort()
    {
        return application::objectGetDefaultSort();
    }

    function objectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = "appId", $bAscending = TRUE)
    {
        return $this->oApp->objectGetEntries($sState, $iRows, $iStart,
                                             $sOrderBy, $bAscending);
    }

    public static function objectGetSortableFields()
    {
        return application::objectGetSortableFields();
    }

    function objectGetHeader($sState)
    {
        return $this->oApp->objectGetHeader($sState);
    }

    function objectGetTableRow()
    {
        return $this->oApp->objectGetTableRow();
    }

    function objectMoveChildren($iNewId)
    {
        return $this->oApp->objectMoveChildren($iNewId);
    }

    function objectDisplayQueueProcessingHelp()
    {
        return $this->oApp->objectDisplayQueueProcessingHelp();
    }

    function objectDisplayAddItemHelp()
    {
        $this->oApp->objectDisplayAddItemHelp();
    }

    function allowAnonymousSubmissions()
    {
        return application::allowAnonymousSubmissions();
    }

    function objectAllowPurgingRejected()
    {
        return $this->oApp->objectAllowPurgingRejected();
    }

    public function objectGetSubmitTime()
    {
        return $this->oApp->objectGetSubmitTime();
    }

    function objectGetId()
    {
        return $this->oApp->objectGetId();
    }
}


