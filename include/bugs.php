<?php
require_once(BASE."include/util.php");
require_once(BASE."include/application.php");
/******************************************/
/* bug class and related functions */
/******************************************/


/**
 * Bug Link class for handling Bug Links and thumbnails
 */
class Bug
{
    var $iLinkId;

    // parameters necessary to create a new Bug with Bug::create()
    var $iVersionId;
    var $iBug_id;

    // values retrieved from bugzilla
    var $sShort_desc;
    var $sBug_status;
    var $sResolution;
    var $sSubmitTime;
    var $iSubmitterId;
    var $bQueued;

    /**    
     * Constructor, fetches the data and bug objects if $ilinkId is given.
     */
    function bug($iLinkId = null, $oRow = null)
    {
        if(!$iLinkId && !$oRow)
          return;

        if(!$oRow)
        {
            $sQuery = "SELECT * FROM buglinks
                       WHERE linkId = '?'";
            if($hResult = query_parameters($sQuery, $iLinkId))
            {
                $oRow = query_fetch_object($hResult);
            }
        }

        if($oRow)
        {
            $this->iLinkId = $oRow->linkId;
            $this->iBug_id = $oRow->bug_id;
            $this->iVersionId = $oRow->versionId;
            $this->bQueued = ($oRow->state=='queued') ? true : false;
            $this->sSubmitTime = $oRow->submitTime;
            $this->iSubmitterId = $oRow->submitterId;
            /* lets fill in some blanks */ 
            if ($this->iBug_id)
            {
                $sQuery = "SELECT *
                              FROM bugs 
                              WHERE bug_id = ".$this->iBug_id;
                if($hResult = query_bugzilladb($sQuery))
                {
                    $oRow = query_fetch_object($hResult);
                    if($oRow)
                    {
                        $this->sShort_desc = $oRow->short_desc;
                        $this->sBug_status = $oRow->bug_status;
                        $this->sResolution = $oRow->resolution;
                    }
                }
            }
        }
    }
 

    /**
     * Creates a new Bug.
     */
    function create()
    {
        $oVersion = new Version($this->iVersionId);

        // Security, if we are not an administrator or a maintainer,
        // the Bug must be queued.
        if($this->mustBeQueued())
        {
            $this->bQueued = true;
        } else
        {
            $this->bQueued = false;
        }

        /* lets check for a valid bug id */
        if(!is_numeric($this->iBug_id))
        {
            addmsg($this->iBug_id." is not a valid bug number.", "red");
            return false;
        }

        /* check that bug # exists in bugzilla*/
        $sQuery = "SELECT *
                   FROM bugs 
                   WHERE bug_id = ".$this->iBug_id;
        $sResult = query_bugzilladb($sQuery, "checking bugzilla");
        if(query_num_rows($sResult) == 0)
        {
            addmsg("There is no bug in Bugzilla with that bug number.", "red");
            return false;
        }
        unset($sResult);

        /* Check for duplicates */
        if($this->isDuplicate())
        {
           addmsg("The Bug link has already been submitted.", "red");
           return false;
        }

        /* passed the checks so lets insert the puppy! */
        $hResult = query_parameters("INSERT INTO buglinks (versionId, bug_id, ".
                                    "submitTime, submitterId, state) ".
                                    "VALUES('?', '?', ?, '?', '?')",
                                    $this->iVersionId, $this->iBug_id,
                                    "NOW()",
                                    $_SESSION['current']->iUserId,
                                    $this->bQueued ? 'queued' : 'accepted');
        if($hResult)
        {
            $this->iLinkId = query_appdb_insert_id();

            $this->SendNotificationMail();

            return true;
        } else
        {
            addmsg("Error while creating a new Bug link.", "red");
            return false;
        }
    }

    function purge()
    {
        return $this->delete();
    }

    /**    
     * Deletes the Bug from the database. 
     * and request its deletion from the filesystem (including the thumbnail).
     *
     * Return true if successful, false if an error occurs
     */
    function delete()
    {
        $sQuery = "DELETE FROM buglinks 
                   WHERE linkId = '?'";
        if(!($hResult = query_parameters($sQuery, $this->iLinkId)))
            return false;

        return true;
    }

    /**
     * Move Bug out of the queue.
     */
    function unQueue()
    {
        // If we are not in the queue, we can't move the Bug out of the queue.
        if(!$this->bQueued)
            return false;

        if(query_parameters("UPDATE buglinks SET state = '?' WHERE linkId='?'",
                            'accepted', $this->iLinkId))
        {
            $this->bQueued = false;
            // we send an e-mail to interested people
            $this->mailSubmitter();
            $this->SendNotificationMail();
            // the Bug has been unqueued
            addmsg("The Bug has been unqueued.", "green");
        }
    }

    function update()
    {
        $oBug = new bug($this->iLinkId);

        // There is no need to have two links to a bug.  The update is still
        // considered successful
        if($this->isDuplicate())
            return $this->delete();

        if($this->iVersionId && $this->iVersionId != $oBug->iVersionId)
        {
            $hResult = query_parameters("UPDATE buglinks SET versionId = '?'
                                         WHERE linkId = '?'",
                                         $this->iVersionId, $this->iLinkId);

            if(!$hResult)
                return false;
        }

        return true;
    }

    /* Checks whether the version already has a link for this bug */
    public function isDuplicate()
    {
        $sQuery = "SELECT COUNT(linkId) as count
                   FROM buglinks 
                   WHERE versionId = '?'
                   AND bug_id = '?' AND linkId != '?'";
        if($hResult = query_parameters($sQuery, $this->iVersionId, $this->iBug_id, $this->iLinkId))
        {
            if(($oRow = query_fetch_object($hResult)))
            {
                return $oRow->count > 0;
            }
        }
        return false;
    }

    function mailSubmitter($bRejected=false)
    {
        global $aClean;
        if(!isset($aClean['sReplyText']))
            $aClean['sReplyText'] = "";

        if($this->iSubmitterId)
        {
            $oSubmitter = new User($this->iSubmitterId);
            $sAppName = Version::fullName($this->iVersionId);
            if(!$bRejected)
            {
                $sSubject =  "Submitted Bug Link accepted";
                $sMsg  = "The bug link you submitted between Bug ".$this->iBug_id." and ".  
                        $sAppName." has been accepted.";
            } else
            {
                 $sSubject =  "Submitted Bug Link rejected";
                 $sMsg  = "The bug link you submitted between Bug ".$this->iBug_id." and ".  
                        $sAppName." has been deleted.";
            }
            $sMsg .= $aClean['sReplyText']."\n";
            $sMsg .= "We appreciate your help in making the Application Database better for all users.";
                
            mail_appdb($oSubmitter->sEmail, $sSubject ,$sMsg);
        }
    }

 
    function SendNotificationMail($bDeleted=false)
    {
        $sAppName = version::fullName($this->iVersionId);
        $oVersion = new version($this->iVersionId);

        //show the application version URL and the bug URL
        $sMsg = $oVersion->objectMakeUrl()."\n\n";
        $sMsg .= BUGZILLA_ROOT."show_bug.cgi?id=".$this->iBug_id."\n";

        if(!$bDeleted)
        {
            if(!$this->bQueued)
            {
                $sSubject = "Link between Bug ".$this->iBug_id." and ".$sAppName." added by ".$_SESSION['current']->sRealname;
                if($this->iSubmitterId)
                {
                    $oSubmitter = new User($this->iSubmitterId);
                    $sMsg .= "This Bug Link has been submitted by ".$oSubmitter->sRealname.".\n";
                }
                addmsg("The Bug Link was successfully added into the database.", "green");
            } else // Bug Link queued.
            {
                $sSubject = "Link between Bug ".$this->iBug_id." and ".$sAppName." submitted by ".$_SESSION['current']->sRealname;
                $sMsg .= "This Bug Link has been queued.\n";
                addmsg("The Bug Link you submitted has been added to the database and queued for review by the maintainer or an administrator.", "green");
            }
        } else // Bug Link deleted.
        {
            $sSubject = "Link between Bug ".$this->iBug_id." and ".$sAppName." deleted by ".$_SESSION['current']->sRealname;
            addmsg("Bug Link deleted.", "green");
        }

        $sEmail = User::get_notify_email_address_list(null, $this->iVersionId);
        if($sEmail)
        {
            mail_appdb($sEmail, $sSubject ,$sMsg);
        }
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
        /* We don't do this at the moment */
                return array(null, null, null);
    }

    public function objectGetParent($sClass = '')
    {
        return new version($this->iVersionId);
    }

    public function objectSetParent($iNewId, $sClass = '')
    {
        $this->iVersionId = $iNewId;
    }

    function objectGetChildren($bIncludeDeleted = false)
    {
        return array();
    }

    /* Get a list of bugs submitted by a given user */
    function listSubmittedBy($iUserId, $bQueued = true)
    {
        $hResult = query_parameters("SELECT appFamily.appName, buglinks.versionId, appVersion.versionName, buglinks.submitTime, buglinks.bug_id, buglinks.linkId FROM buglinks, appFamily, appVersion WHERE appFamily.appId = appVersion.appId AND buglinks.versionId = appVersion.versionId AND buglinks.state = '?' AND buglinks.submitterId = '?' ORDER BY buglinks.versionId", $bQueued ? 'queued' : 'accepted', $iUserId);

        if(!$hResult || !query_num_rows($hResult))
            return FALSE;

        $oTable = new Table();
        $oTable->SetWidth("100%");
        $oTable->SetAlign("center");

        // setup the table header
        $oTableRow = new TableRow();
        $oTableRow->AddTextCell('Version');
        $oTableRow->AddTextCell('Bug #', 'width="50"');
        $oTableRow->AddTextCell('Status', 'width="80"');
        $oTableRow->AddTextCell('Resolution', 'width="110"');
        $oTableRow->AddTextCell('Description', 'Submit time');
        $oTableRow->AddTextCell('Submit time');

        if($bQueued)
            $oTableRow->addTextCell('Action');

        $oTableRow->SetClass('color4');
        $oTable->AddRow($oTableRow);

        for($i = 1; $oRow = query_fetch_object($hResult); $i++)
        {
            $oTableRow = new TableRow();

            $oTableRow->AddTextCell(version::fullNameLink($oRow->versionId));
            $oTableRow->AddTextCell('<a href="'.BUGZILLA_ROOT.'show_bug.cgi?id='.$oRow->bug_id.'">'.$oRow->bug_id.'</a>');
            $oTableRow->AddTextCell($oBug->sBug_status);
            $oTableRow->AddTextCell($oBug->sResolution);
            $oTableRow->AddTextCell($oBug->sShort_desc);
            $oTableRow->AddTextCell(print_date(mysqldatetime_to_unixtimestamp($oRow->submitTime)));

            if($bQueued)
            {
                $oM = new objectManager('bug');
                $oM->setReturnTo(array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : "");
                $shDeleteLink = '<a href="'.$oM->makeUrl('delete', $oRow->linkId, 'Delete entry').'">delete</a>';
                $oTableRow->addTextCell(" [ $shDeleteLink ]");
            }

            $oTableRow->SetClass(($i % 2 ) ? 'color0' : 'color1');
            $oTable->AddRow($oTableRow);
        }

        return $oTable->GetString();
    }

    function isOpen()
    {
        return ($this->sBug_status != 'CLOSED');
    }

    function allowAnonymousSubmissions()
    {
        return false;
    }

    function mustBeQueued()
    {
        $oVersion = new version($this->iVersionId);

        if($_SESSION['current']->hasPriv("admin") ||
           $_SESSION['current']->isMaintainer($oVersion->iVersionId) ||
           $_SESSION['current']->isSuperMaintainer($oVersion->iAppId))
        {
          return false;
        }

        return true;
    }

    function objectGetId()
    {
        return $this->iLinkId;
    }

    public function objectGetFilterInfo()
    {
        $oFilter = new FilterInterface();

        /* The following filters are only useful for admins */
        if(!$_SESSION['current']->hasPriv('admin'))
            return null;

        $oFilter->AddFilterInfo('onlyWithoutMaintainers', 'Only show bug links for versions without maintainers', array(FILTER_OPTION_BOOL), FILTER_VALUES_OPTION_BOOL, array('false','true'));
        $oFilter->AddFilterInfo('onlyMyMaintainedEntries', 'Only show bug links for versions you maintain', array(FILTER_OPTION_BOOL), FILTER_VALUES_OPTION_BOOL, array('false','true'));

        return $oFilter;
    }

    public static function objectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = '', $bAscending = true, $oFilters = null)
    {
        $sExtraTables = '';
        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyWithoutMaintainers' => 'false', 'onlyMyMaintainedEntries' => 'false');
        $sWhereFilter = '';
        $bOnlyMyMaintainedEntries = false;

        $oBug = new bug();

        if(getInput('onlyMyMaintainedEntries', $aOptions) == 'true'
           || ($sState != 'accepted' && !$oBug->canEdit()))
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

            $sWhereFilter .= " AND appVersion.hasMaintainer = 'false' AND appVersion.versionId = buglinks.versionId";
        }

        /* Selecting 0 rows makes no sense, so we assume the user
         wants to select all of them
         after an offset given by iStart */
        if(!$iRows)
          $iRows = bug::objectGetEntriesCount($sState, $oFilters);

        if($bOnlyMyMaintainedEntries)
        {
            $sQuery = "SELECT buglinks.* FROM buglinks, appVersion,
                        appMaintainers WHERE
                        buglinks.versionId = appVersion.versionId
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
                        buglinks.state = '?'$sWhereFilter LIMIT ?,?";

            $hResult = query_parameters($sQuery, $_SESSION['current']->iUserId,
                                        $sState, $iStart, $iRows);
        } else
        {
            $sQuery = "select * from buglinks$sExtraTables where buglinks.state = '?'$sWhereFilter LIMIT ?, ?";
            $hResult = query_parameters($sQuery, $sState, $iStart, $iRows);
        }

        return $hResult;
    }

    public static function objectGetEntriesCount($sState, $oFilters = null)
    {
        $sExtraTables = '';
        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyWithoutMaintainers' => 'false', 'onlyMyMaintainedEntries' => 'false');
        $sWhereFilter = '';
        $bOnlyMyMaintainedEntries = false;

        $oBug = new bug();

        if(getInput('onlyMyMaintainedEntries', $aOptions) == 'true'
           || ($sState != 'accepted' && !$oBug->canEdit()))
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

            $sWhereFilter .= " AND appVersion.hasMaintainer = 'false' AND appVersion.versionId = buglinks.versionId";
        }

        if($bOnlyMyMaintainedEntries)
        {
            $sQuery = "SELECT COUNT(buglinks.linkId) as cnt FROM buglinks, appVersion,
                        appMaintainers WHERE
                        buglinks.versionId = appVersion.versionId
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
                        buglinks.state = '?'$sWhereFilter";

            $hResult = query_parameters($sQuery, $_SESSION['current']->iUserId,
                                        $sState);
        } else
        {
            $sQuery = "select count(*) as cnt from buglinks$sExtraTables where buglinks.state = '?'$sWhereFilter";
            $hResult = query_parameters($sQuery, $sState);
        }

        $oRow = query_fetch_object($hResult);

        return $oRow->cnt;
    }

    function objectGetHeader()
    {
        $oTableRow = new TableRow();
        
        $oTableRow->AddTextCell("Bug #");

        $oTableCell = new TableCell("Status");
        $oTableCell->SetAlign("center");
        $oTableRow->AddCell($oTableCell);

        $oTableRow->AddTextCell("Bug description");

        $oTableCell = new TableCell("Application name");
        $oTableCell->SetAlign("center");
        $oTableRow->AddCell($oTableCell);

        $oTableCell = new TableCell("Version");
        $oTableCell->SetAlign("center");
        $oTableRow->AddCell($oTableCell);

        $oTableCell = new TableCell("Has maintainer");
        $oTableCell->SetAlign("center");
        $oTableRow->AddCell($oTableCell);

        return $oTableRow;
    }

    // returns a table row
    function objectGetTableRow()
    {
        $oTableRow = new TableRow();
        
        $oVersion = new version($this->iVersionId);
        $oApp = new application($oVersion->iAppId);

        $oTableCell = new TableCell($this->iBug_id);
        $oTableCell->SetAlign("center");
        $oTableCell->SetCellLink(BUGZILLA_ROOT.'show_bug.cgi?id='.$this->iBug_id);
        $oTableRow->AddCell($oTableCell);

        $oTableCell = new TableCell($this->sBug_status);
        $oTableCell->SetAlign("center");
        $oTableRow->AddCell($oTableCell);

        $oTableRow->AddTextCell($this->sShort_desc);

        $oTableRow->AddTextCell($oApp->objectMakeLink());

        $oTableRow->AddTextCell($oVersion->objectMakeLink());

        $oTableRow->AddTextCell($oVersion->hasMaintainer() ? 'YES' : 'No');

        $oOMTableRow = new OMTableRow($oTableRow);

        // enable the deletion link, the objectManager will check
        // for appropriate permissions before adding the link
        $oOMTableRow->SetHasDeleteLink(true);

        return $oOMTableRow;
    }

    function objectMakeUrl()
    {
        $oManager = new objectManager("bug", "View Bug");
        return $oManager->makeUrl("view", $this->objectGetId());
    }

    function objectMakeLink()
    {
        $sLink = "<a href=\"".$this->objectMakeUrl()."\">".
                 $this->sShort_desc."</a>";
        return $sLink;
    }

    function objectGetState()
    {
        return ($this->bQueued) ? 'queued' : 'accepted';
    }

    function canEdit()
    {
        if($_SESSION['current']->hasPriv("admin"))
        {
            return true;
        } else if($this->iVersionId)
        {
            if(maintainer::isUserMaintainer($_SESSION['current'],
                                          $this->iVersionId))
            {
                return true;
            }

            if($this->iSubmitterId == $_SESSION['current']->iUserId)
                return true;
        }

        return false;
    }

    function objectGetItemsPerPage($sState = 'accepted')
    {
        $aItemsPerPage = array(25, 50, 100, 200);
        $iDefaultPerPage = 25;
        return array($aItemsPerPage, $iDefaultPerPage);
    }

    function display()
    {
        $oTable = new Table();
        $oTable->SetAlign("center");
        $oTable->SetClass("color0");
        $oTable->SetCellPadding(2);

        $oHeaderRow = $this->objectGetHeader();
        $oHeaderRow->SetClass("color4");
        $oTable->AddRow($oHeaderRow);

        $oDataRow = $this->objectGetTableRow();
        $oDataRow->GetTableRow()->SetClass("color0");
        $oTable->AddRow($oDataRow);
        
        echo $oTable->GetString();
    }

    function objectHideReject()
    {
        return TRUE;
    }

    // NOTE: we don't have any editing support for this entry at this time
    //       so output the entry and a field to identify the bug id
    function outputEditor()
    {
        $this->display();
        echo '<input type="hidden" name="iBuglinkId" value="'.$this->iBug_id.'">';
        echo '<input type="hidden" name="iVersionId" value="'.$this->iVersionId.'">';
    }

    function getOutputEditorValues($aClean)
    {
        if(getInput('iBuglinkId', $aClean))
            $this->iBug_id = $aClean['iBuglinkId'];

        if($aClean['iVersionId'])
            $this->iVersionId = $aClean['iVersionId'];
    }
}


/*
 * Bug Link functions that are not part of the class
 */

function view_version_bugs($iVersionId = null, $aBuglinkIds)
{
    global $aClean;

    $bCanEdit = FALSE;
    $oVersion = new Version($iVersionId);

    // Security, if we are an administrator or a maintainer, we can remove or ok links.
    if(($_SESSION['current']->hasPriv("admin") ||
                 $_SESSION['current']->isMaintainer($oVersion->iVersionId) ||
                 $_SESSION['current']->isSuperMaintainer($oVersion->iAppId)))
    {
        $bCanEdit = TRUE;
    } 
    
    //start format table
    echo "<table class=\"whq-table whq-table-full\">\n\n";
    echo "<thead><tr>\n";
    echo "    <td class=\"text-center\" width=\"80\">Bug #</td>\n";
    echo "    <td>Description</td>\n";
    echo "    <td class=\"text-center\" width=\"80\">Status</td>\n";
    echo "    <td class=\"text-center\" width=\"80\">Resolution</td>\n";
    echo "    <td class=\"text-center\" width=\"80\">Other apps affected</td>\n";

    if($bCanEdit == true)
    {
        echo "    <td class=\"text-center\"width=\"80\">Delete</td>\n";
        echo "    <td class=\"text-center\"width=\"80\">Checked</td>\n";
    }
    echo "</tr></thead><tbody>\n\n";

    foreach($aBuglinkIds as $iBuglinkId)
    {
        $oBuglink = new Bug($iBuglinkId);

        if ((!isset($aClean['sAllBugs']) && $oBuglink->isOpen() ) || isset($aClean['sAllBugs']))
        {
            //display row
            echo "<tr>\n";
            echo "<td class=\"text-center\"><a href=\"".BUGZILLA_ROOT."show_bug.cgi?id={$oBuglink->iBug_id}\"><i class=\"fa fa-bug\"></i> {$oBuglink->iBug_id}</a></td>\n";
            echo "<td>".$oBuglink->sShort_desc."</td>\n";
            echo "<td class=\"text-center\">".$oBuglink->sBug_status."</td>","\n";
            echo "<td class=\"text-center\">".$oBuglink->sResolution."</td>","\n";
            echo "<td class=\"text-center\"><a href=\"viewbugs.php?iBugId={$oBuglink->iBug_id}\" class=\"btn btn-default\">View</a></td>\n";

            if($bCanEdit == true)
            {
                $oM = new objectManager("bug");
                $oM->setReturnTo($oVersion->objectMakeUrl());
                echo "<td class=\"text-center\"><a href=\"".$oM->makeUrl("delete", $oBuglink->iLinkId)."&amp;sSubmit=delete\" class=\"btn btn-default btn-skinny\"><i class=\"fa fa-trash-o\"></i></a></td>\n";
                if ($oBuglink->bQueued)
                {
                    echo "<td class=\"text-center\"><a href=\"".$oM->makeUrl("edit", $oBuglink->iLinkId)."&amp;sSubmit=Submit&amp;bIsQueue=true\" class=\"btn btn-default btn-skinny\"><i class=\"fa fa-check\"></i></a></td>\n";
                }
                else
                {
                    echo "<td class=\"text-center\">Yes</td>\n";
                }
                
            }
            echo "</tr>\n\n";
        }
    }

    echo '</tbody></table>',"\n";

    if($_SESSION['current']->isLoggedIn())
    {
        echo "<form method=post action='".APPDB_ROOT."objectManager.php'>\n";
        echo '<input type="hidden" name="iVersionId" value="'.$iVersionId.'">',"\n";
        $sBuglinkId = isset($aClean['buglinkId']) ? $aClean['buglinkId'] : '';
        echo '<input type="text" name="iBuglinkId" value="'.$sBuglinkId.'" size="8">',"\n";
        echo '<input type="hidden" name="sSubmit" value="Submit">',"\n";
        echo '<input type="hidden" name="sClass" value="bug">',"\n";
        echo '<input type="hidden" name="sReturnTo" value="'.$oVersion->objectMakeUrl().'">',"\n";
        echo '<button type="submit" name="sSub" class="btn btn-default"><i class=\"fa fa-bug\"></i> Submit a new bug link</button>',"\n";
        echo '</form>',"\n";
    }

    // show only open link
    if ( isset( $aClean['sAllBugs'] ) )
    {
        $sURL = htmlentities(str_replace( '&sAllBugs', '', $_SERVER['REQUEST_URI']));
        $sLink = "<a href=\"{$sURL}\" class=\"btn btn-default\"><i class=\"fa fa-bug\"></i> Show <b>open</b> bugs</a>";
    }
    // show all link
    else
    {
        $sURL = htmlentities($_SERVER['REQUEST_URI'] . '&sAllBugs');
        $sLink = "<a href=\"{$sURL}\" class=\"btn btn-default\"><i class=\"fa fa-bug\"></i> Show <b>all</b> bugs</a>";
    }
    echo "<p>{$sLink}</p>\n";
}

?>
