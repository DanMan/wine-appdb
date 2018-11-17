<?php

/**
 * Functions related to application data
 */

require_once(BASE."include/util.php");

class appData
{
    var $iId;
    var $iAppId;
    var $iVersionId;
    var $iSubmitterId;
    var $sSubmitTime;
    var $sDescription;
    var $bQueued;

    function __construct($iId = null, $oRow = null, $oObject = null)
    {
        if(!$iId && !$oRow)
            return;

        /* Since all objects stored in the appData table have a number of common
           members, we can import such an object into an appData one without
           making an SQL query */
        if($oObject && $iId)
        {
            $this->iSubmitterId = $oObject->iSubmitterId;
            $this->sDescription = $oObject->sDescription;
            $this->iAppId = $oObject->iAppId;
            $this->iVersionId = $oObject->iVersionId;
            $this->sSubmitTime = $oObject->sSubmitTime;
            $this->iId = $iId;
            $this->bQueued = $oObject->bQueued;
            return;
        }

        if(!$oRow)
        {
            $hResult = query_parameters("SELECT * FROM appData WHERE id = '?'", $iId);
            $oRow = query_fetch_object($hResult);
        }

        if($oRow)
        {
            $this->iSubmitterId = $oRow->submitterId;
            $this->iAppId = $oRow->appId;
            $this->iVersionId = $oRow->versionId;
            $this->sSubmitTime = $oRow->submitTime;
            $this->iId = $iId;
            $this->bQueued = ($oRow->sState == 'accepted') ? false : true;
            $this->sDescription = $oRow->description;
        }
    }

    function purge()
    {
        return $this->delete();
    }

    function delete()
    {
        if(!$this->canEdit())
            return FALSE;

        $sQuery = "DELETE FROM appData WHERE id = '?'";

        $hResult = query_parameters($sQuery, $this->iId);

        if(!$hResult)
            return FALSE;

        return $hResult;
    }

    function reQueue()
    {
        if(!$this->canEdit())
            return FALSE;

        $sQuery = "UPDATE appData SET state = '?' WHERE id = '?'";
        $hResult = query_parameters($sQuery, 'queued', $this->iId);

        if(!$hResult)
            return FALSE;
        else
            return TRUE;
    }

    function reject()
    {
        if(!$this->canEdit())
            return FALSE;

        $sQuery = "UPDATE appData SET state = '?' WHERE id = '?'";
        $hResult = query_parameters($sQuery, 'rejected', $this->iId);

        if(!$hResult)
            return FALSE;
        else
            return TRUE;
    }

    function update()
    {
        if(!$this->canEdit())
            return FALSE;

        $sQuery = "UPDATE appData SET versionId = '?', appId = '?', description = '?'
                        WHERE id = '?'";
        $hResult = query_parameters($sQuery, $this->iVersionId, $this->iAppId,
                                    $this->sDescription, $this->iId);
 
        if(!$hResult)
            return FALSE;

        return TRUE;
    }

    public function objectGetParent($sClass = '')
    {
        if($this->iVersionId)
            return new version($this->iVersionId);
        else
            return new application($this->iAppId);
    }

    public function objectSetParent($iNewId, $sClass = '')
    {
        if($this->iVersionId)
            $this->iVersionId = $iNewId;
        else
            $this->iAppId = $iNewId;
    }

    function listSubmittedBy($iUserId, $bQueued = true)
    {
        $sExtra = '';
        if($bQueued) // downloadurls are only queued together with versions
            $sExtra = " AND appData.type != 'downloadurl' ";

        $hResult = query_parameters("SELECT * FROM appData WHERE
                appData.submitterId = '?'
                AND
                appData.state = '?' $sExtra
                    ORDER BY appData.id",
                        $iUserId, $bQueued ? 'queued' : 'accepted');

        if(!$hResult || !query_num_rows($hResult))
            return false;

        $oTable = new table();
        $oTable->setWidth("100%");
        $oTable->setAlign("center");

        $oTableRow = new tableRow();

        $oTableRow->addTextCell("Version");
        $oTableRow->addTextCell("Type");
        $oTableRow->addTextCell("Description");
        $oTableRow->addTextCell("Submission Date");

        if($bQueued)
            $oTableRow->addTextCell("Action");

        $oTableRow->setClass("color4");
        $oTable->addRow($oTableRow);

        for($i = 1; $oRow = query_fetch_object($hResult); $i++)
        {
            $oTableRow = new tableRow();
            if($oRow->versionId)
            {
                $oVersion = new version($oRow->versionId);
                $sLink = "<a href=\"".$oVersion->objectMakeUrl()."\">".
                         $oVersion->fullName($oVersion->iVersionId)."</a>";
            } else
            {
                $oApp = new application($this->appId);
                $sLink = $oApp->objectMakeLink();
            }

            $oTableRow->addTextCell($sLink);
            $oTableRow->addTextCell($oRow->type);
            $oTableRow->addTextCell($oRow->description);
            $oTableRow->addTextCell(print_date(mysqldatetime_to_unixtimestamp($oRow->submitTime)));

            if($bQueued)
            {
                $oM = new objectManager($oRow->type);
                $oM->setReturnTo(array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : "");
                $shDeleteLink = '<a href="'.$oM->makeUrl("delete", $oRow->id, "Delete entry").'">delete</a>';
                $shEditLink = '<a href="'.$oM->makeUrl("edit", $oRow->id, "Edit entry").'">edit</a>';
                $oTableRow->addTextCell("[ $shEditLink ] &nbsp; [ $shDeleteLink ]");
            }

            $oTableRow->setClass(($i % 2) ? "color0" : "color1");
            $oTable->addRow($oTableRow);
        }

        return $oTable->getString();

    }

    /* Get appData for a given version/application, optionally filter by type */
    public static function getData($iId, $sType, $bIsVersion = TRUE, $bQueued = FALSE, $bRejected = FALSE)
    {
        $iAppId = 0;
        $iVersionId = 0;

        if($bIsVersion)
            $iVersionId = $iId;
        else
            $iAppId = $iId;

        $sState = objectManager::getStateString($bQueued, $bRejected);

        $hResult = query_parameters("SELECT * FROM appData WHERE appId = '?' AND
                                     versionId = '?' AND TYPE = '?' AND state = '?'",
                                    $iAppId, $iVersionId, $sType, $sState);

        if(!$hResult || !query_num_rows($hResult))
            return FALSE;

        return $hResult;
    }

    public static function objectGetEntriesCount($sState, $sType = null, $oFilters = null)
    {
        /* Not implemented for appData */
        if($sState == 'rejected')
            return FALSE;

        $sSelectType = "";

        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyWithoutMaintainers' => 'false', 'onlyMyMaintainedEntries' => 'false');
        $sWhereFilter = '';
        $sVersionWhereFilter = '';
        $bOnlyMyMaintainedEntries = false;

        $oAppData = new appData();

        if(getInput('onlyMyMaintainedVersionEntries', $aOptions) == 'true'
           || ($sState != 'accepted' && !$oAppData->canEdit()))
        {
            $bOnlyMyMaintainedEntries = true;
        }

        /* This combination doesn't make sense */
        if(getInput('onlyWithoutVersionMaintainers', $aOptions) == 'true'
           && getInput('onlyMyMaintainedVersionEntries', $aOptions) == 'true')
        {
            return false;
        }

        if(getInput('onlyWithoutVersionMaintainers', $aOptions) == 'true')
        {
            $sVersionWhereFilter .= " AND appVersion.hasMaintainer = 'false' AND appVersion.versionId = appData.versionId";
        }

        if(getInput('appCategory', $aOptions))
        {
            $oCategory =  new category($aOptions['appCategory']);
            $sWhereFilter .= ' AND ' . $oCategory->getSqlQueryPart();
        }

        if($bOnlyMyMaintainedEntries)
        {
           $sQuery = "SELECT COUNT(DISTINCT appData.id) as count FROM appData,
           appMaintainers, appVersion, appFamily WHERE
                appFamily.appId = appVersion.appId
                AND
                appMaintainers.state = 'accepted'
                AND
                appMaintainers.userId = '?'
                AND
                (
                    (
                        appMaintainers.appId = appFamily.appId
                        OR
                        appMaintainers.appId = appVersion.appId
                    )
                    AND
                    appMaintainers.superMaintainer = '1'
                    AND
                    (
                        appData.appId = appMaintainers.appId
                        OR
                        (
                            appData.versionId = appVersion.versionId
                            AND
                            appVersion.appId = appMaintainers.appId
                        )
                    )
                    OR
                    (
                        appMaintainers.superMaintainer = '0'
                        AND
                        appMaintainers.versionId = appVersion.versionId
                        AND
                        appMaintainers.versionId = appData.versionId
                    )
                )
                AND
                appVersion.state = 'accepted'
                AND
                appFamily.state = 'accepted'$sVersionWhereFilter$sWhereFilter";

            if($sState != 'all')
                $sQuery .= " AND appData.state = '$sState'";

            if($sType)
            {
                $sQuery .= " AND type = '?'";
                $hResult = query_parameters($sQuery, $_SESSION['current']->iUserId, 
                                            $sType);
            } else
            {
                $hResult = query_parameters($sQuery, $_SESSION['current']->iUserId);
            }
        } else
        {
            if($sState != 'all')
                $sAppDataQueued = " AND appData.state = '$sState'";
            else
                $sAppDataQueued = '';

            if($sType)
                $sSelectType = " AND type = '?'";

            $sQuery = "(SELECT COUNT(DISTINCT appData.id) as count FROM appData,
                appFamily WHERE
                    (
                        appData.appId = appFamily.appId
                        AND
                        appData.versionId = '0'
                    )
                    AND
                    appFamily.state = 'accepted'$sAppDataQueued$sSelectType$sWhereFilter) UNION
                    (
                    SELECT COUNT(DISTINCT appData.id) as count FROM appData,
                appFamily, appVersion WHERE
                    appFamily.appId = appVersion.appId
                    AND
                    (
                        appData.versionId = appVersion.versionId
                    )
                    AND
                    appVersion.state = 'accepted'
                    AND
                    appFamily.state = 'accepted'$sVersionWhereFilter$sAppDataQueued$sSelectType$sWhereFilter)";

            if($sType)
                $hResult = query_parameters($sQuery, $sType, $sType);
            else
                $hResult = query_parameters($sQuery);
        }

        if(!$hResult)
            return FALSE;

        for($iCount = 0; $oRow = query_fetch_object($hResult);)
            $iCount += $oRow->count;

        return $iCount;
    }

    public static function objectGetHeader($sType)
    {
        $oTableRow = new TableRow();
        $oTableRow->AddTextCell("Submission date");
        $oTableRow->AddTextCell("Submitter");
        $oTableRow->AddTextCell("Application");
        $oTableRow->AddTextCell("Version");
        $oTableRow->AddTextCell('Has maintainer');
        return $oTableRow;
    }

    public static function objectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = '', $bAscending = true, $sType = null, $oFilters = null)
    {
        /* Not implemented for appData */
        if($sState == 'rejected')
            return FALSE;

        $sSelectType = "";
        $sLimit = "";

        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyWithoutMaintainers' => 'false', 'onlyMyMaintainedEntries' => 'false');
        $sWhereFilter = '';
        $sVersionWhereFilter = '';
        $bOnlyMyMaintainedEntries = false;

        $oAppData = new appData();

        if(getInput('onlyMyMaintainedVersionEntries', $aOptions) == 'true'
           || ($sState != 'accepted' && !$oAppData->canEdit()))
        {
            $bOnlyMyMaintainedEntries = true;
        }

        /* This combination doesn't make sense */
        if(getInput('onlyWithoutVersionMaintainers', $aOptions) == 'true'
           && getInput('onlyMyMaintainedVersionEntries', $aOptions) == 'true')
        {
            return false;
        }

        if(getInput('onlyWithoutVersionMaintainers', $aOptions) == 'true')
        {
            $sVersionWhereFilter .= " AND appVersion.hasMaintainer = 'false' AND appVersion.versionId = appData.versionId";
        }

        if(getInput('appCategory', $aOptions))
        {
            $oCategory =  new category($aOptions['appCategory']);
            $sWhereFilter .= ' AND ' . $oCategory->getSqlQueryPart();
        }

        if($bOnlyMyMaintainedEntries)
        {
            $sQuery = "SELECT DISTINCT appData.* FROM appData, appMaintainers,
                appVersion, appFamily WHERE
                appFamily.appId = appVersion.appId
                AND
                appMaintainers.state = 'accepted'
                AND
                appMaintainers.userId = '?'
                AND
                (
                    (
                        (
                            appMaintainers.appId = appFamily.appId
                            OR
                            appMaintainers.appId = appVersion.appId
                        )
                        AND
                        appMaintainers.superMaintainer = '1'
                        AND
                        (
                            appData.appId = appMaintainers.appId
                            OR
                            (
                                appData.versionId = appVersion.versionId
                                AND
                                appVersion.appId = appMaintainers.appId
                            )
                        )
                    )
                    OR
                    (
                        appMaintainers.superMaintainer = '0'
                        AND
                        appMaintainers.versionId = appVersion.versionId
                        AND
                        appMaintainers.versionId = appData.versionId
                    )
                )
                AND
                appVersion.state = 'accepted'
                AND
                appFamily.state = 'accepted'
                AND
                appData.state = '?'
                AND
                appData.type = '?'
                $sWhereFilter
                $sVersionWhereFilter
                ORDER BY appFamily.appName";
            if(!$iRows && !$iStart)
            {
                $hResult = query_parameters($sQuery, $_SESSION['current']->iUserId,
                                            $sState, $sType);
            } else
            {
                if(!$iRows)
                    $iRows = appData::objectGetEntriesCount($sState, $sType, $oFilters);
                $sQuery .= " LIMIT ?,?";
                $hResult = query_parameters($sQuery, $_SESSION['current']->iUserId,
                                            $sState, $sType,
                                            $iStart, $iRows);
            }
        } else
        {
            if($iStart || $iRows)
                $sLimit = " LIMIT ?,?";

            $sQuery = 
                   "(
                      SELECT DISTINCT appData.* FROM appData,
                          appFamily WHERE
                      (
                        appData.appId = appFamily.appId
                        AND
                        appData.versionId = '0'
                      )
                      AND
                      appFamily.state = 'accepted'
                      AND
                      appData.state = '?'
                      AND
                      appData.type = '?' $sWhereFilter ORDER BY appFamily.appName $sLimit
                    )
                    UNION
                    (
                      SELECT DISTINCT appData.* FROM appData,
                        appFamily, appVersion WHERE
                        appFamily.appId = appVersion.appId
                      AND
                      (
                          appData.versionId = appVersion.versionId
                      )
                      AND
                      appVersion.state = 'accepted'
                      AND
                      appFamily.state = 'accepted'
                      AND
                      appData.state = '?'
                      AND
                      appData.type = '?' $sVersionWhereFilter$sWhereFilter ORDER BY appData.submitTime, appFamily.appName $sLimit
                    )";
            if(!$iRows && !$iStart)
            {
                $hResult = query_parameters($sQuery, $sState, $sType,
                                            $sState, $sType);
            } else
            {
                if(!$iRows)
                    $iRows = appData::objectGetEntriesCount($sState, $sType, $oFilters);
                $hResult = query_parameters($sQuery, $sState, $sType,
                                            $iStart, $iRows,
                                            $sState, $sType,
                                            $iStart, $iRows);
            }
        }

        if(!$hResult)
            return FALSE;

        return $hResult;
    }

    function canEdit()
    {
        if($_SESSION['current']->hasPriv("admin"))
            return TRUE;
        if($this)
        {
            if($this->bQueued && $this->iSubmitterId == $_SESSION['current']->iUserId)
                return true;

            if($this->iVersionId)
            {
                $oVersion = new version($this->iVersionId);
                if($oVersion->canEdit())
                    return TRUE;
                else
                    return FALSE;
            } else if($this->iAppId)
            {
                $oApp = new application($this->iAppId);
                if($oApp->canEdit())
                    return TRUE;
                else
                    return FALSE;
            } else
                return FALSE;
        }
    }

    function mustBeQueued()
    {
        if($_SESSION['current']->hasPriv("admin"))
            return FALSE;
        if($this)
        {
            if($this->iVersionId)
            {
                $oVersion = new version($this->iVersionId);
                if($oVersion->canEdit() && $oVersion->objectGetState() == 'accepted')
                    return FALSE;
                else
                    return TRUE;
            } else if($this->iAppId)
            {
                $oApp = new application($this->iAppId);
                if($oApp->canEdit() && $oApp->objectGetState() == 'accepted')
                    return FALSE;
                else
                    return TRUE;
            } else
                return TRUE;
        }
    }

    function objectGetTableRow()
    {
        $oVersion = new Version($this->iVersionId);

        if(!$this->iAppId)
            $this->iAppId = $oVersion->iAppId;

        $oApp = new Application($this->iAppId);
        $oUser = new User($this->iSubmitterId);

        $bMaintained = $this->iVersionId ? $oVersion->hasMaintainer() : $oApp->hasMaintainer();

        $oTableRow = new TableRow();
        $oTableRow->AddTextCell(print_date(mysqldatetime_to_unixtimestamp($this->sSubmitTime)));
        $oTableRow->AddTextCell($oUser->objectMakeLink());
        $oTableRow->AddTextCell($oApp->objectMakeLink());
        $oTableRow->AddTextCell($this->iVersionId ? $oVersion->objectMakeLink() : "N/A");
        $oTableRow->AddTextCell($bMaintained ? 'YES' : 'No');

        // create the object manager specific row
        $oOMTableRow = new OMTableRow($oTableRow);

        return $oOMTableRow;
    }

    function objectDisplayQueueProcessingHelp()
    {
        $sHelp = "<p>This is a list of application data submitted by users. ".
                 "Please inspect the data carefully before accepting or rejecting it.</p>";
        echo $sHelp;
    }

    /* Output the part of an appData editor which is the same for all data types */
    function outputEditorGeneric()
    {
        $oVersion = new version($this->iVersionId);
        if($oVersion->iVersionId)
        {
            $this->iAppId = $oVersion->iAppId;
            $sVersionName = $oVersion->objectMakeLink();
        }
        else
            $sVersionName = "N/A";

        $oApp = new Application($this->iAppId);

        // view application details
        echo html_frame_start("New Application Data Form",600,"",0);
        echo "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";

        // app name
        echo '<tr valign=top><td class=color0><b>App Name</b></td>',"\n";
        echo "<td>".$oApp->objectMakeLink()."</td></tr>\n";

        // version
        echo '<tr valign=top><td class=color0><b>App Version</b></td>',"\n";
        echo "<td>$sVersionName</td></tr>\n";

        //dataDescription
        echo '<tr valign=top><td class=color0><b>Description</b></td>',"\n";
        echo '<td><textarea name="sDescription" rows=10 cols=35>'.stripslashes($this->sDescription).'</textarea></td></tr>',"\n";
    }

    function getDefaultReply()
    {
        $sReplyText = "Enter a personalized reason for acceptance or rejection of the".
                      " submitted application data here";
        return $sReplyText;
    }

    function objectGetId()
    {
        return $this->iId;
    }
}

?>
