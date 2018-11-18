<?php
/***************************************/
/* this class represents Distributions */
/***************************************/
require_once(BASE."include/mail.php");
require_once(BASE."include/util.php");

// Test class for handling Distributions.

class distribution {
    var $iDistributionId;
    var $sName;
    var $sUrl;
    var $sSubmitTime;
    var $iSubmitterId;
    private $sState;
    var $aTestingIds;

     // constructor, fetches the data.
    function __construct($iDistributionId = null, $oRow = null)
    {
        $this->aTestingIds = array();
        // we are working on an existing distribution.
        if(!$iDistributionId && !$oRow)
            return;

        // We fetch the data related to this distribution.
        $this->sName = "Unknown";
        if(!$oRow)
        {
            $sQuery = "SELECT *
                        FROM distributions
                        WHERE distributionId = '?'";
            if($hResult = query_parameters($sQuery, $iDistributionId))
                $oRow = query_fetch_object($hResult);
        }

        if($oRow)
        {
            $this->iDistributionId = $oRow->distributionId;
            $this->sName = $oRow->name;
            $this->sUrl = $oRow->url;
            $this->sSubmitTime = $oRow->submitTime;
            $this->iSubmitterId = $oRow->submitterId;
            $this->sState = $oRow->state;
        }

        /*
            * We fetch Test Result Ids. 
            */

        if($_SESSION['current']->hasPriv("admin"))
        {
            $sQuery = "SELECT testingId
                            FROM testResults, ?.versions
                            WHERE
                            versions.value = testResults.testedRelease
                            AND
                            versions.product_id = '?'
                            AND
                            distributionId = '?'
                            ORDER BY testedRating,bugs.versions.id DESC";
        } else /* only let users view test results that aren't queued and for apps that */
                /* aren't queued or versions that aren't queued */
        {
            $sQuery = "SELECT testingId
                            FROM testResults, appFamily, appVersion, ?.versions
                            WHERE testResults.state = 'accepted' AND
                                versions.value = testResults.testedRelease
                                AND
                                versions.product_id = '?'
                                AND
                                testResults.versionId = appVersion.versionId AND
                                appFamily.appId = appVersion.appId AND
                                appFamily.state = 'accepted' AND
                                appVersion.state = 'accepted' AND
                                distributionId = '?'
                            ORDER BY testedRating,bugs.versions.id DESC";
        }

        if($hResult = query_parameters($sQuery, BUGZILLA_DB, BUGZILLA_PRODUCT_ID, $this->iDistributionId))
        {
            while($oRow = query_fetch_object($hResult))
            {
                $this->aTestingIds[] = $oRow->testingId;
            }
        }
    }

    // Creates a new distribution.
    function create()
    {
        //Let's not create a duplicate 
        $sQuery = "SELECT *
                   FROM distributions
                   WHERE name = '?'
                   AND state != 'deleted'";
        $hResult = query_parameters($sQuery, $this->sName);

        if($hResult && $oRow = query_fetch_object($hResult))
        {
            if(query_num_rows($hResult))
            {
                distribution::__construct($oRow->distributionId);

                /* Even though we did not create a new distribution, the caller is provided
                with a valid distribution object.  Thus no special handling is necessary,
                so we return TRUE */
                return TRUE;
            }
        }

        $hResult = query_parameters("INSERT INTO distributions (name, url, submitTime, ".
                                    "submitterId, state) ".
                                    "VALUES ('?', '?', ?, '?', '?')",
                                    $this->sName, $this->sUrl,
                                    "NOW()",
                                    $_SESSION['current']->iUserId,
                                    $this->mustBeQueued() ? 'queued' : 'accepted');
        if($hResult)
        {
            $this->iDistributionId = query_appdb_insert_id();
            distribution::__construct($this->iDistributionId);
            return true;
        }
        else
        {
            addmsg("Error while creating operating system.", "red");
            return false;
        }
    }

    // Update Distribution.
    function update()
    {
        // is the current user allowed to update this Distribution? 
        if(!$_SESSION['current']->hasPriv("admin") &&
            !maintainer::isUserMaintainer($_SESSION['current']) &&
            !($_SESSION['current']->iUserId == $this->iSubmitterId))
        {
            return;
        }
        if(query_parameters("UPDATE distributions SET name = '?', url = '?' WHERE distributionId = '?'",
                            $this->sName, $this->sUrl, $this->iDistributionId))
        {
            return true;
        } else
        {
            addmsg("Error while updating operating system", "red");
            return false;
        }
    }

    // Removes the distribution from the database.
    function purge()
    {
        /* Is the current user allowed to delete this distribution?  We allow
                everyone to delete a queued, empty distribution, because it should be
                deleted along with the last testData associated with it */
                if(!($this->canEdit() || (!sizeof($this->aTestingIds) &&
                $this->sState != 'accepted')))
                return false;

        // if the distribution has test results only enable an admin to delete
        // the distribution
                if(sizeof($this->aTestingIds) && !$_SESSION['current']->hasPriv("admin"))
                return FALSE;

        $bSuccess = TRUE;

        foreach($this->objectGetChildren(true) as $oChild)
        {
            if(!$oChild->purge())
                $bSuccess = FALSE;
        }

        // now delete the Distribution 
                $sQuery = "DELETE FROM distributions
                WHERE distributionId = '?' 
                LIMIT 1";
        if(!($hResult = query_parameters($sQuery, $this->iDistributionId)))
            $bSuccess = FALSE;

        return $bSuccess;
    }

    // Marks distribution as deleted in the database.
    function delete()
    {
        /* admins and maintainers can delete any distribution;
            users can only delete distributions they submitted that are still in their queue */
        if(!$_SESSION['current']->hasPriv("admin") && 
            !maintainer::isUserMaintainer($_SESSION['current']) &&
            !(($_SESSION['current']->iUserId == $this->iSubmitterId) && $this->sState != 'accepted'))
            return false;
            
        $bSuccess = TRUE;

        // now delete the Distribution 
        $sQuery = "UPDATE distributions SET state = 'deleted'
                   WHERE distributionId = '?' 
                   LIMIT 1";
        if(!($hResult = query_parameters($sQuery, $this->iDistributionId)))
            $bSuccess = FALSE;

        return $bSuccess;
    }

    // Move Distribution out of the queue.
    function unQueue()
    {
        /* Check permissions */
        if($this->mustBeQueued())
            return FALSE;

        // If we are not in the queue, we can't move the Distribution out of the queue.
        if($this->sState != 'queued')
            return false;

        if(query_parameters("UPDATE distributions SET state = '?' WHERE distributionId = '?'",
                            'accepted', $this->iDistributionId))
        {
            $this->sState = 'accepted';
            return true;
        } else
        {
            addmsg("Error while unqueueing operating system", "red");
            return false;
        }
    }

    function Reject($bSilent=false)
    {
        // is the current user allowed to reject this Distribution? 
        if(!$_SESSION['current']->hasPriv("admin"))
        {
            return false;
        }

        // If we are not in the queue, we can't move the Distribution out of the queue.
        if($this->sState != 'queued')
            return false;

        return $this->delete();
    }

    function getTestResults($bIncludeDeleted = false)
    {
        if($bIncludeDeleted)
            $sExcludeDeleted = "";
        else
            $sExcludeDeleted = " AND state != 'deleted'";

        $aTests = array();
        $sQuery = "SELECT * FROM testResults WHERE distributionId = '?'$sExcludeDeleted";
        $hResult = query_parameters($sQuery, $this->iDistributionId);

        while($oRow = query_fetch_object($hResult))
            $aTests[] = new testData(null, $oRow);

        return $aTests;
    }

    function objectGetChildren($bIncludeDeleted = false)
    {
        $aChildren = array();

        foreach($this->getTestResults($bIncludeDeleted) as $oTest)
        {
            $aChildren += $oTest->objectGetChildren($bIncludeDeleted);
            $aChildren[] = $oTest;
        }

        return $aChildren;
    }

    function ReQueue()
    {
        // is the current user allowed to requeue this data 
        if(!$_SESSION['current']->hasPriv("admin") &&
           !($_SESSION['current']->iUserId == $this->iSubmitterId))
        {
            return false;
        }

        if(query_parameters("UPDATE testResults SET state = '?' WHERE testingId = '?'",
                            'queued', $this->iTestingId))
        {
            if(query_parameters("UPDATE distribution SET state = '?' WHERE distributionId = '?'",
                                'queued', $this->iDistributionId))
            {
                $this->sState = 'queued';

                // the test data has been resubmitted
                addmsg("The operating system has been resubmitted", "green");
                return true;
            }
        }

        /* something has failed if we fell through to this point without */
        /* returning */
        addmsg("Error requeueing operating system", "red");
        return false;
    }

    function objectGetSubmitterId()
    {
        return $this->iSubmitterId;
    }

    function objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction)
    {
        return new mailOptions();
    }

    function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        $sSubject = '';
        $sMsg = '';
        if($bMailSubmitter)
        {
            switch($sAction)
            {
                case "delete":
                    $sSubject = "Submitted operating system deleted";
                    $sMsg = "The operating system you submitted (".$this->sName.") has been ".
                            "deleted.\n";
                break;
            }
            $aMailTo = null;
        } else
        {
            switch($sAction)
            {
                case "delete":
                    $sSubject = "Operating system ".$this->sName." deleted";
                    $sMsg = "";
                break;
            }
            $aMailTo = User::get_notify_email_address_list(null, null);
        }

        return array($sSubject, $sMsg, $aMailTo);
    }

    function mailSubmitter($sAction="add")
    {
        global $aClean;

        $sSubject = '';
        $sMsg = '';
        if($this->iSubmitterId)
        {
            $oSubmitter = new User($this->iSubmitterId);
            switch($sAction)
            {
            case "add":
               {
                   $sSubject =  "Submitted operating system accepted";
                   $sMsg  = "The operating system you submitted (".$this->sName.") has been accepted.\n";
               }
            break;
            case "delete":
                {
                    $sSubject =  "Submitted operating system deleted";
                    $sMsg  = "The operating system you submitted (".$this->sName.") has been deleted.";
                    $sMsg .= "Reason given:\n";
                    $sMsg .= $aClean['sReplyText']."\n"; // append the reply text, if there is any 
                }
            break;
            }
            $sMsg .= "We appreciate your help in making the Application Database better for all users.";

            mail_appdb($oSubmitter->sEmail, $sSubject ,$sMsg);
        }
    }

    function outputEditor()
    {
        echo "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";

        $this->sName = str_replace('"', '&quot;', $this->sName);
        // Name
        echo html_tr(array(
                array("<b>Operating system name:</b>", 'align=right class="color0"'),
                array('<input type=text name="sDistribution" value="'.$this->sName.
                        '" size="60" />', 'class="color0"')
                    ));

        // URL
        echo html_tr(array(
                array("<b>Operating system URL:</b>", 'align=right class="color0"'),
                array('<input type=text name="sUrl" value="'.$this->sUrl.
                        '" size="60" />', 'class="color0"')
                    ));


        echo "</table>\n";

        if($this->iDistributionId)
        {
            echo  '<input type="hidden" name="iDistributionId" '.
                    'value="'.$this->iDistributionId.'">',"\n";
        }
    }

    /**
     * Retrieves values from $_REQUEST that were output by outputEditor()
     * @param array $aValues Can be $_REQUEST or any array with the values from outputEditor()
     */
    function GetOutputEditorValues($aValues)
    {
        if($aValues['iDistributionId'])
            $this->iDistributionId = $aValues['iDistributionId'];

        $this->sName = $aValues['sDistribution'];
        $this->sUrl = $aValues['sUrl'];
    }

    function objectGetFilterInfo()
    {
        $oFilter = new FilterInterface();

        $oFilter->AddFilterInfo('name', 'Name', array(FILTER_CONTAINS, FILTER_STARTS_WITH, FILTER_ENDS_WITH), FILTER_VALUES_NORMAL);
        return $oFilter;
    }

    /** Get the total number of Distributions in the database */
    public static function objectGetEntriesCount($sState, $oFilter = null)
    {
        /* Not implemented */
        if($sState == 'rejected')
            return FALSE;

        $sWhereFilter = $oFilter ? $oFilter->getWhereClause() : '';

        if($sWhereFilter)
            $sWhereFilter = " AND $sWhereFilter";

        $hResult = query_parameters("SELECT count(distributionId) as num_dists FROM
                                     distributions WHERE state='?' $sWhereFilter",
                                    $sState);

        if($hResult)
        {
            $oRow = query_fetch_object($hResult);
            return $oRow->num_dists;
        }
        return 0;
    }

    /* Make a dropdown list of distributions */
    public static function make_distribution_list($varname, $cvalue)
    {
        $sQuery = "SELECT name, distributionId FROM distributions
                WHERE state = 'accepted'
                ORDER BY name";
        $hResult = query_parameters($sQuery);
        if(!$hResult) return;

        echo "<select name='$varname' class='form-control form-control-inline'>\n";
        echo "<option value=\"\">Choose your operating system</option>\n";
        while(list($name, $value) = query_fetch_row($hResult))
        {
            if($value == $cvalue)
                echo "<option value=$value selected>$name\n";
            else
                echo "<option value=$value>$name\n";
        }
        echo "</select>\n";
    }

    function objectGetHeader()
    {
        $oTableRow = new TableRowSortable();

        $oTableRow->AddSortableTextCell("Operating system name", "name");

        $oTableCell = new TableCell("Test reports");
        $oTableRow->AddCell($oTableCell);

        $oTableRow->AddTextCell("Operating system URL");

        return $oTableRow;
    }

    public static function objectGetSortableFields()
    {
        return array('name');
    }

    public static function objectGetDefaultSort()
    {
        return 'name';
    }

    public static function objectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = "name", $bAscending = TRUE, $oFilter = null)
    {
        /* Not implemented */
        if($sState == 'rejected')
            return FALSE;

        /* Only users with edit privileges are allowed to view queued
           items, so return NULL in that case */
        if($sState != 'accepted' && !distribution::canEdit())
            return NULL;

        $sOrder = $bAscending ? "ASC" : "DESC";

        /* If row limit is 0 we want to fetch all rows */
        if(!$iRows)
            $iRows = distribution::objectGetEntriesCount($sState, $oFilter);

	$sWhereFilter = $oFilter ? $oFilter->getWhereClause() : '';

	if($sWhereFilter)
	    $sWhereFilter = " AND $sWhereFilter";

        $sQuery = "SELECT * FROM distributions
                       WHERE state = '?' $sWhereFilter ORDER BY $sOrderBy $sOrder LIMIT ?,?";

        return query_parameters($sQuery, $sState,
                                $iStart, $iRows);
    }

    function objectGetTableRow()
    {
        $oTableRow = new TableRow();

        $oTableRow->AddTextCell($this->objectMakeLink());

        $oTableCell = new TableCell(sizeof($this->aTestingIds));
        $oTableRow->AddCell($oTableCell);

        $oTableCell = new TableCell("$this->sUrl");
        $oTableCell->SetCellLink($this->sUrl);
        $oTableRow->AddCell($oTableCell);


        // enable the 'delete' action if this distribution has no test results
        $bDeleteLink = sizeof($this->aTestingIds) ? FALSE : TRUE;

        $oOMTableRow = new OMTableRow($oTableRow);
        $oOMTableRow->SetHasDeleteLink($bDeleteLink);
        return $oOMTableRow;
    }

    public function objectGetState()
    {
        return $this->sState;
    }

    // Whether the user has permission to edit distributions
    function canEdit()
    {
        if($_SESSION['current']->hasPriv("admin"))
            return TRUE;

        /* Maintainers are allowed to process queued test results and therefore also
           queued distributions */
        if(is_object($this) && $this->sState != 'accepted' &&
           maintainer::isUserMaintainer($_SESSION['current']))
            return TRUE;

        return FALSE;
    }

    function mustBeQueued()
    {
        if($_SESSION['current']->hasPriv("admin") ||
          maintainer::isUserMaintainer($_SESSION['current']))
            return FALSE;
        else
            return TRUE;
    }

    function objectHideDelete()
    {
        return TRUE;
    }

    function display()
    {
        echo "Operating system name: ";

        if($this->sUrl)
            echo "<a href='".$this->sUrl."'>";

        echo $this->sName;

        if ($this->sUrl) 
        {
            echo " (".$this->sUrl.")";
            echo "</a> <br>\n";
        } else 
        {
            echo "<br>\n";
        }

        if($this->aTestingIds)
        {
            echo '<h1 class="whq-app-title">Test Results for '.$this->sName.'</h1>',"\n";
            echo '<table class="whq-table whq-table-full">',"\n";
            echo '<thead>',"\n";
            echo '<tr>',"\n";
            echo '<td>Application Version</td>',"\n";
            echo '<td>Submitter</td>',"\n";
            echo '<td>Date Submitted</td>',"\n";
            echo '<td>Wine version</td>',"\n";
            echo '<td>Installs?</td>',"\n";
            echo '<td>Runs?</td>',"\n";
            echo '<td>Rating</td>',"\n";
            echo '<td></td>',"\n";
            echo '</tr></thead><tbody>',"\n";
            foreach($this->aTestingIds as $iTestingId)
            {
                $oTest = new testData($iTestingId);

                if($oTest->objectGetState() != $this->objectGetState())
                    continue;

                $oVersion = new Version($oTest->iVersionId);
                $oApp  = new Application($oVersion->iAppId);
                $oSubmitter = new User($oTest->iSubmitterId);
                $bgcolor = $oTest->sTestedRating;

                /* make sure the user can view the versions we list in the table */
                /* otherwise skip over displaying the entries in this table */
                if(!$_SESSION['current']->canViewApplication($oApp))
                    continue;
                if(!$_SESSION['current']->canViewVersion($oVersion))
                    continue;

                echo "<tr>\n";
                echo '<td><a href="'.$oVersion->objectMakeUrl().'&amp;iTestingId='.$oTest->iTestingId.'">',"\n";
                echo version::fullName($oVersion->iVersionId).'</a></td>',"\n";
                echo "<td>\n";

                echo $oSubmitter->objectMakeLink();

                echo '</td>',"\n";
                echo '<td>'.date("M d Y", mysqldatetime_to_unixtimestamp($oTest->sSubmitTime)).'</td>',"\n";
                echo '<td>'.$oTest->sTestedRelease.'&nbsp;</td>',"\n";
                echo '<td>'.$oTest->sInstalls.'&nbsp;</td>',"\n";
                echo '<td>'.$oTest->sRuns.'&nbsp;</td>',"\n";
                echo "<td class=\"{$bgcolor}\">{$oTest->sTestedRating}</td>\n";
                if ($_SESSION['current']->hasAppVersionModifyPermission($oVersion))
                {
                    echo '<td><a href="'.$oTest->objectMakeUrl().'" class="btn btn-default btn-xs">',"\n";
                    echo '<i class="fa fa-pencil-square-o"></i> Edit</a></td>',"\n";
                }
                echo '</tr>',"\n";
            }
            echo '</tbody></table>',"\n";
        }
    }

    /* Make a URL for viewing the specified distribution */
    function objectMakeUrl()
    {
        $oObject = new objectManager("distribution", "View Distribution");
        return $oObject->makeUrl("view", $this->iDistributionId);
    }

    /* Make an HTML link for viewing the specified distirbution */
    function objectMakeLink()
    {
        return "<a href=\"".$this->objectMakeUrl()."\">$this->sName</a>";
    }

    function objectMoveChildren($iNewId)
    {
        /* Keep track of how many children we modified */
        $iCount = 0;

        foreach($this->aTestingIds as $iTestId)
        {
            $oTest = new testData($iTestId);
            $oTest->iDistributionId = $iNewId;
            if($oTest->update(TRUE))
                $iCount++;
            else
                return FALSE;
        }

        return $iCount;
    }

    function objectGetItemsPerPage($sState = 'accepted')
    {
        $aItemsPerPage = array(25, 50, 100, 200);
        $iDefaultPerPage = 25;
        return array($aItemsPerPage, $iDefaultPerPage);
    }

    function objectGetid()
    {
        return $this->iDistributionId;
    }

    function allowAnonymousSubmissions()
    {
        return FALSE;
    }

    function objectShowAddEntry()
    {
        return TRUE;
    }
}


