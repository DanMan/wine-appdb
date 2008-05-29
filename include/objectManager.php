<?php

define("PREVIEW_ENTRY", 2);

/* class for managing objects */
/* - handles processing of queued objects */
/* - handles the display and editing of unqueued objects */
class ObjectManager
{
    private $sClass;
    private $sTitle;
    private $iId;
    private $sState;
    private $sReturnTo;
    private $sReturnToTitle; /* Used to preserve the title when processing entries from a queue list, for instance */
    private $oMultiPage;
    private $oTableRow;
    private $oSortInfo; /* Contains sort info used when displaying tables */
    private $oObject; /* Store an instance of the object of the class
                         we are working with.  This is useful if
                         we are calling object functions which modify
                         the object without storing it in the database,
                         and then call objectManager functions which
                         operate on the object, such as in processForm
                         where we first run the object's getOutputEditorValues()
                         and then objectManager's delete_entry(). */

    // an array of common responses used when replying to
    // queued entries
    private $aCommonResponses;

    /* Remove the cached object of the class we are working with, useful in cases where we
       modify the object in such a way that it needs to be reloaded */
    private function flushCachedObject()
    {
        $this->oObject = null;
    }

    /* Get an instance of the object of the class we are working with */
    private function getObject()
    {
        if(!$this->oObject)
            $this->oObject = new $this->sClass($this->iId);

        return $this->oObject;
    }

    private function setId($iId)
    {
        $this->iId = $iId;

        if($this->oObject)
            $this->oObject = new $this->sClass($this->iId);
    }

    public function getClass()
    {
      return $this->sClass;
    }

    public function getState()
    {
        return $this->sState;
    }

    public function setState($sState)
    {
        /* Ensure that the given state is valid */
        switch($sState)
        {
            case 'accepted':
            case 'queued':
            case 'rejected':
            case 'deleted':
                $this->sState = $sState;
                return true;

            default:
                return false;
        }
    }

    public function getIsQueue()
    {
      return $this->sState != 'accepted';
    }

    public function setReturnTo($sReturnTo)
    {
      $this->sReturnTo = $sReturnTo;
    }

    public function setReturnToTitle($sTitle)
    {
      $this->sReturnToTitle = $sTitle;
    }

    public function setSortInfo($aClean = null)
    {
        /* No use to continue if there are no sortable fields */
        if(!$this->getOptionalSetting("objectGetSortableFields", FALSE))
            return;

        $this->oSortInfo = null;
        $this->oSortInfo = new TableSortInfo($this->makeUrl().'&amp;');

        if($aClean)
            $this->oSortInfo->ParseArray($aClean, $this->getObject()->objectGetSortableFields());
    }

    public function getId()
    {
      return $this->iId;
    }

    public function getMultiPageDataFromInput($aClean)
    {
      $this->oMultiPage->getDataFromInput($aClean);
    }

    function ObjectManager($sClass, $sTitle = "list", $iId = false)
    {
        $this->sClass = $sClass;
        $this->sTitle = $sTitle;
        $this->iId = $iId;
        $this->oMultiPage = new MultiPage(FALSE);
        $this->oTableRow = new OMTableRow(null);
        $this->sState = 'accepted';

        // initialize the common responses array
        $this->aCommonResponses = array();
        $this->aCommonResponses[] = "Thank you for your submission.";
	$this->aCommonResponses[] = "Please consider filing a bug for the ".
	"problem you have with this app, if there are not bugs filed already".
	" at http://bugs.winehq.org.  If you want some advice or guidance on".
	" this, please visit http://forum.winehq.org";
        $this->aCommonResponses[] = "Please do not paste debug output in the AppDB,".
	" it belongs only as an attachment on bugzilla.";
        $this->aCommonResponses[] = "We appreciate your submission but it".
          " needs to be more detailed before it will be most useful to other users of".
          " the Application Database.".
          " Please try to improve the entry and resubmit.";
        $this->aCommonResponses[] = "We appreciate your submission but it".
          " requires improvement to its grammar and/or spelling".
          " before it will be most useful to other users of".
          " the Application Database.".
          " Please try to improve the entry and resubmit.";
        $this->aCommonResponses[] = "Please do not copy large amount of text from".
          " the program's website";
    }

    /* Check whether the associated class has the given method */
    public function checkMethod($sMethodName, $bEnableOutput)
    {
        if(!method_exists($this->sClass, $sMethodName))
        {
            if($bEnableOutput) echo "class '".$this->sClass."' lacks method '".$sMethodName."'\n";
            return false;
        }

        return true;
    }

    /* Check whether the specified methods are valid */
    public function checkMethods($aMethods, $bExit = true)
    {
        foreach($aMethods as $sMethod)
        {
            if(!$this->checkMethod($sMethod, false))
            {
                echo "Selected class does not support this operation ".
                     "(missing '$sMethod()')\n";

                if($bExit)
                    exit;
                else
                    return FALSE;
            }
        }

        return TRUE;
    }

    public static function error_exit($shMessage)
    {
        echo '<div align="center"><p><font color="red"><b>'.$shMessage.'</b></font></p></div';
        echo apidb_footer();
        exit;
    }

    public function drawTable($hResult)
    {
        /* output the header */
        echo '<table width="100%" border="0" cellpadding="3" cellspacing="0">';

        /* Output header cells */
        $this->outputHeader("color4");

        /* Preserve the page title */
        $this->setReturnToTitle($this->sTitle);

        /* output each entry */
        for($iCount = 0; $oRow = query_fetch_object($hResult); $iCount++)
        {
            $oObject = new $this->sClass(null, $oRow);

            $this->oTableRow = $oObject->objectGetTableRow();

            $sColor = ($iCount % 2) ? "color0" : "color1";

            // if there is no class set for a given row use the
            // default one in $sColor
            if(!$this->oTableRow->GetTableRow()->GetClass())
            {
                $this->oTableRow->GetTableRow()->SetClass($sColor);
            }

            // if this row is clickable, make it highlight appropirately
            $oTableRowClick = $this->oTableRow->GetTableRow()->GetTableRowClick();
            if($oTableRowClick)
            {
            $oTableRowHighlight = GetStandardRowHighlight($iCount);
            $oTableRowClick->SetHighlight($oTableRowHighlight);
            }

            $sEditLinkLabel = $this->getIsQueue() ? 'process' : 'edit';

            /* We add some action links */
            if($oObject->canEdit())
            {
                $shDeleteLink = "";
                if($this->oTableRow->GetHasDeleteLink())
                {
                $shDeleteLink = ' [&nbsp;<a href="'.$this->makeUrl("delete", $oObject->objectGetId()).
                    '">delete</a>&nbsp;]';
                }

                $oTableCell = new TableCell('[&nbsp;<a href="'.$this->makeUrl("edit",
                                $oObject->objectGetId()).'">'.$sEditLinkLabel.'</a>&nbsp;]'.$shDeleteLink);
                $this->oTableRow->AddCell($oTableCell);
            }

            echo $this->oTableRow->GetString();
        }

        echo "</table>";
    }

    /* displays the list of entries */
    public function display_table($aClean)
    {
        $this->checkMethods(array("ObjectGetEntries", "ObjectGetHeader",
             "objectGetTableRow", "objectGetId", "canEdit"));

        /* We cannot process a queue if we are not logged in */
        if(!$_SESSION['current']->isLoggedIn() && $this->getIsQueue())
        {
            $sQueueText = $this->sState == 'rejected' ? "rejected" : "queued";
            echo '<div align="center">You need to ';
            echo "log in in order to process $sQueueText entries</div>\n";
            login_form(false);
            return;
        }

        // if displaying a queue display the help for the given queue
        if($this->getIsQueue())
            $this->GetOptionalSetting("ObjectDisplayQueueProcessingHelp", "");

        $oObject = new $this->sClass();

        // Display top of the page selectors for items per page and
        // current page, if applicable.
        $this->handleMultiPageControls($aClean, TRUE);

        /* Set the sort info */
        $this->setSortInfo($aClean);

        if(!$this->oSortInfo->sCurrentSort)
            $this->oSortInfo->sCurrentSort = $this->getOptionalSetting('objectGetDefaultSort', '');

        /* query the class for its entries */
        /* We pass in queue states to tell the object */
        /* if we are requesting a list of its queued objects or */
        /* all of its objects */
        $hResult = $oObject->objectGetEntries($this->sState,
                                              $this->oMultiPage->iItemsPerPage,
                                              $this->oMultiPage->iLowerLimit,
                                              $this->oSortInfo->sCurrentSort,
                                              $this->oSortInfo->bAscending);

        /* did we get any entries? */
        if(!$hResult || query_num_rows($hResult) == 0)
        {
            switch($this->getQueueString($this->getIsQueue(), $this->sState == 'rejected'))
            {
                case "true":
                    echo "<center>The queue for '$this->sClass' is empty</center>";
                break;
                case "false":
                    echo "<center>No entries of '$this->sClass' are present</center>";
                break;
                case "rejected":
                    echo "<center>No rejected entries of '$this->sClass' are ".
                            "present</center>";
                break;
            }

            if($this->GetOptionalSetting("objectShowAddEntry", FALSE))
            {
                echo "<br><center><a href=\"".
                     $this->makeUrl("add", false,
                     "Add $this->sClass entry").
                     "\">Add an entry?</a></center>";
            }
            return;
        }

        /* Show a link to the 'purge rejected entries' page if we are an admin */
        if($_SESSION['current']->hasPriv('admin') && $this->getOptionalSetting('objectAllowPurgingRejected', FALSE))
        {
            echo '<div align="center">';
            $oM = new objectManager($this->sClass, 'Purge Rejected Entries');
            echo '<a href="'.$oM->makeUrl('purgeRejected').'">Purge rejected entries</a><br><br>';
            echo '</div>';
        }

        $sQueued = $this->getQueueString($this->getIsQueue(),                                                                         $this->sState == 'rejected');

        /* Should we let the class draw its own custom table? */
        if(method_exists($this->sClass, 'objectWantCustomDraw') && 
           $oObject->objectWantCustomDraw('table', $sQueued))
            $oObject->objectDrawCustomTable($hResult, $sQueued);
        else
            $this->drawTable($hResult);

        $oObject = new $this->sClass();
        if($oObject->canEdit() && $this->GetOptionalSetting("objectShowAddEntry", FALSE))
        {
            echo "<br><br><a href=\"".$this->makeUrl("add", false,
                    "Add $this->sClass")."\">Add entry</a>\n";
        }

        // Display bottom of page selectors current page, if applicable
        // NOTE: second parameter is false because we don't want the
        //       items per page selector appearing for the second set of page controls
        $this->handleMultiPageControls($aClean, FALSE);
    }

    private function getOptionalSetting($sFunction, $bDefault)
    {
        if(!method_exists($this->sClass, $sFunction))
            return $bDefault;

        return $this->getObject()->$sFunction();
    }

    /* display the entry for editing */
    public function display_entry_for_editing($aClean, $sErrors)
    {
        $this->checkMethods(array("outputEditor", "getOutputEditorValues",
                                  "update", "create"));

        // open up the div for the default container
        echo "<div class='default_container'>\n";

        // link back to the previous page
        echo html_back_link(1, null);

        $oObject = new $this->sClass($this->iId);

        /* The entry does not exist */
        if(!$oObject->objectGetId())
        {
            echo "<font color=\"red\">There is no entry with that id in the database</font>.\n";
            echo "</div>";
            return;
        }

        /* Display errors, if any, and fetch form data */
        if($this->displayErrors($sErrors))
        {
            $oObject->getOutputEditorValues($aClean);

            if($sErrors === PREVIEW_ENTRY)
                $this->show_preview($oObject, $aClean);
        }

        echo '<form name="sQform" action="'.$this->makeUrl("edit", $this->iId).
                '" method="post" enctype="multipart/form-data">',"\n";

        echo $this->makeUrlFormData();

        $aCustomVars = $this->get_custom_vars($aClean, "edit");

        if($aCustomVars)
            $oObject->outputEditor($aClean);
        else
            $oObject->outputEditor();

        $this->oObject = $oObject;

        /* if this is a queue add a dialog for replying to the submitter of the
           queued entry */
        if($this->getIsQueue() || ($oObject->objectGetSubmitterId() && $oObject->objectGetSubmitterId() != $_SESSION['current']->iUserId))
        {
            /* If it isn't implemented, that means there is no default text */
            $sDefaultReply = $this->getOptionalSetting("getDefaultReply", "");

            echo html_frame_start("Reply text", "90%", "", 0);
            echo "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";
            echo '<tr valign=top><td class="color0"><b>email Text</b></td>',"\n";
            echo '<td><textarea name="sReplyText" style="width: 100%" cols="80" '. 
                 'rows="10">'.$sDefaultReply.'</textarea></td></tr>',"\n";

            if($this->getIsQueue())
            {
                /////////////////////////////////////////////////
                // output radio buttons for some common responses
                echo '<tr valign=top><td class="color0"></td><td class="color0">'.
                '<b>Common replies</b><br> Email <a href="mailto:'.APPDB_OWNER_EMAIL.'">'.
                APPDB_OWNER_EMAIL.'</a> if you want to suggest a new common reply.</td></tr>',"\n";

                // NOTE: We use the label tag so the user can click anywhere in
                // the text of the radio button to select the radio button.
                // Otherwise the user has to click on the very small circle portion
                // of the button to select it
                foreach($this->aCommonResponses as $iIndex => $sReply)
                {
                echo '<tr valign=top><td class="color0"></td>',"\n";
                echo '<td class="color0"><label for="'.$iIndex.'"><input'.
                    ' type="radio" name="sOMCommonReply" id="'.$iIndex.'" value="'.$sReply.'">'.
                    $sReply.'</label></td>',"\n";
                echo '</tr>',"\n";
                }
                // end output radio buttons for common responses
                /////////////////////////////////////////////////
            }


            /* buttons for operations we can perform on this entry */
            echo '<tr valign=top><td class=color3 align=center colspan=2>' ,"\n";
            echo '<input name="sSubmit" type="submit" value="Submit" class="button" '. 
                 '>',"\n";
            if(!$this->getOptionalSetting("objectHideDelete", FALSE))
            {
                echo '<input name="sSubmit" type="submit" value="Delete" '.
                     'class="button">',"\n";
            }

            if($this->sState != 'rejected' && !$this->getOptionalSetting("objectHideReject", FALSE))
            {
                echo '<input name="sSubmit" type="submit" value="Reject" class="button" '.
                    '>',"\n";
            }

            echo '<input name="sSubmit" type="submit" value="Cancel" class="button" '.
                 '>',"\n";
            echo '</td></tr>',"\n";
            echo '</table>';
            echo html_frame_end();
        } else
        {
            // display the move children entry
            $this->displayMoveChildren($oObject);

            echo '<tr valign=top><td class=color3 align=center colspan=2>',"\n";
            echo '<input name="sSubmit" type="submit" value="Submit" class="button">'.
                 '&nbsp;',"\n";
            echo '<input name="sSubmit" type="submit" value="Delete" class="button">'."\n";
            $this->handle_preview_button();
            echo "</td></tr>\n";
        }

        echo '</form>';

        echo "</div>\n";

    }

    /* Ask whether the user really wants to delete the entry and display a delete reason box */
    public function delete_prompt()
    {
        $this->checkMethods(array("delete", "canEdit"));

        $oObject = new $this->sClass($this->iId);

        /* Check permissions */
        if(!$oObject->canEdit())
        {
            echo "<font color=\"red\">You do not have permission to delete this entry.</font>\n";
            return;
        }

        /* Check whether the object exists */
        if(!$oObject->objectGetId())
        {
            echo "<font>There is no entry with that id in the database.</font>\n";
            return;
        }

        /* Why the user should write an explanation for the deletion */
        if($oObject->objectGetSubmitterId() != $_SESSION['current']->iUserId)
        {
            $shWhyComment = "Please enter a reason why so that you don&#8217;t hurt the submitter&#8217;s".
                            " feelings.";

            $oUser = new user($oObject->objectGetSubmitterId());
            $sSubmitter = " (submitted by ".$oUser->objectMakeLink().")";
        } else
        {
            $shWhyComment = "Although you submitted it it might be useful for other users to know why ".
                            "you are deleting it.";

            $sSubmitter = "";
        }

        $oTable = new Table();
        $oTableRow = new TableRow();
        $oTable->setAlign("center");
        $oTable->addRow($oTableRow);
        $oTableRow->addTextCell(
        '<div style="left: 200px; width: 400px;" align="center" class="default_container">'.
        '<div style="text-align: left;" class="info_container">'.
        '<div class="title_class">'.
        "Confirm deletion".
        "</div>".
        '<div class="info_contents">'.
        "Are you sure you wish to delete this entry".$sSubmitter."?<br>".
        $shWhyComment.
        "</div>".
        "</div>".

        '<form method="post" action="'.$this->makeUrl().'">'.
        $this->makeUrlFormData().
        '<input type="hidden" name="iId" value="'.$this->iId.'">'.
        '<textarea rows="15" cols="50" name="sReplyText"></textarea><br><br>'.
        '<input type="submit" value="Delete" name="sSubmit" class="button">'.
        "</form>".
        "</div>");
        echo $oTable->getString();
    }

    public function delete_child($sReplyText, $bMailSubmitter, $bMailCommon)
    {
        $this->checkMethods(array("delete", "canEdit"));

        $oObject = new $this->sClass($this->iId);
        $oSubmitterMail = null;
        $oCommonMail = null;

        if(!$oObject->canEdit())
            return FALSE;

        if($bMailSubmitter)
            $oSubmitterMail = $this->get_mail(TRUE, "delete", TRUE);

        if($bMailCommon)
            $oCommonMail = $this->get_mail(FALSE, "delete", TRUE);

        if($oObject->delete())
        {
            if($oCommonMail || $oSubmitterMail)
            {
                $sReplyText = "The parent entry was deleted. The reason given for ".
                              "that deletion was:\n$sReplyText";

                if($oCommonMail)
                        $oCommonMail->send("delete", $sReplyText);

                if($oSubmitterMail)
                    $oSubmitterMail->send("delete", $sReplyText);
            }

            return TRUE;
        } else
        {
            return FALSE;
        }
    }

    /* Delete the object associated with the given id
       bStandAlone determines whether this is a stand alone delete operation, where we want to output messages and return */
    public function delete_entry($sReplyText, $bStandAlone = true)
    {
        $this->checkMethods(array("delete", "canEdit"));

        $oObject = $this->getObject();
        $oOriginalObject = new $this->sClass($this->iId); /* Prevent possible security hole if users change key
                                                             variables, making the permission checks run on
                                                             the wrong criteria */

        if(!$oOriginalObject->objectGetId())
        {
            addmsg("No id defined", "red");
            return FALSE;
        }

        if(!$oOriginalObject->canEdit())
        {
            addmsg("You don&#8217;t have permission to delete this entry", "red");
            return FALSE;
        }

        $oSubmitterMail = $this->get_mail(TRUE, "delete");
        $oCommonMail = $this->get_mail(FALSE, "delete");

        $iFailed = 0;
        $iDeleted = 0;

        /* Delete children first, if there are any */
        if(method_exists($oObject, "objectGetChildren"))
        {
            $aChildren = $oObject->objectGetChildren();

            if(!is_array($aChildren))
            {
                addmsg("Failed to get child entries, aborting", "red");
                util_redirect_and_exit($this->makeUrl("view", false));
            }

            /* Keep track of whether we should send mails.  This is used by the
               'mail once' option */
            $aSendMailSubmitter = array();
            $aSendMailCommon = array();

            foreach($aChildren as $oChild)
            {
                if(!is_object($oChild))
                {
                    addmsg("Failed to get child entries, aborting", "red");
                    util_redirect_and_exit($this->makeUrl("view", false));
                }

                $oM = $this->om_from_object($oChild);

                if(!isset($aSendMailSubmitter[$oM->sClass][$oChild->objectGetSubmitterId()]))
                    $aSendMailSubmitter[$oM->sClass][$oChild->objectGetSubmitterId()] = TRUE;

                if(!isset($aSendMailCommon[$oM->sClass]))
                    $aSendMailCommon[$oM->sClass] = TRUE;

                if($oM->delete_child($sReplyText, $aSendMailSubmitter[$oM->sClass][$oChild->objectGetSubmitterId()], $aSendMailCommon[$oM->sClass]))
                {
                    $iDeleted++;

                    if($oChild->objectGetMailOptions("delete", TRUE, TRUE)->bMailOnce)
                        $aSendMailSubmitter[$oM->sClass][$oChild->objectGetSubmitterId()] = FALSE;

                    if($oChild->objectGetMailOptions("delete", FALSE, TRUE)->bMailOnce)
                        $aSendMailCommon[$oM->sClass] = FALSE;
                } else
                {
                    $iFailed++;
                }
            }
        }

        if($oObject->delete())
        {
            $oCommonMail->send("delete", $sReplyText);

            if($oSubmitterMail)
                $oSubmitterMail->send("delete", $sReplyText);

            if($bStandAlone)
            {
                addmsg("Entry deleted", "green");

                if($iDeleted)
                {
                    if($iDeleted == 1)
                        $sNoun = 'entry';
                    else
                        $sNoun = 'entries';

                    addmsg("Deleted $iDeleted child $sNoun", 'green');
                }

                if($iFailed)
                {
                    if($iFailed == 1)
                        $sNoun = 'entry';
                    else
                        $sNoun = 'entries';

                    addmsg("Failed to delete $iFailed child $sNoun", 'red');
                }

                $this->return_to_url($this->makeUrl("view", false));
            }
            return TRUE;
        }

        if($bStandAlone)
            addmsg("Failed to delete entry", "red");

        return FALSE;
    }

    /* Return the user to the url specified in the objectManager object.  Fall back to a
       given value if the object member is not set */
    private function return_to_url($sFallback)
    {
        $sUrl = $this->sReturnTo;

        if(!$sUrl)
            $sUrl = $sFallback;

        util_redirect_and_exit($sUrl);
    }

    private function om_from_object($oObject)
    {
        return new objectManager(get_class($oObject), "", $oObject->objectGetId());
    }

    /* Creates a mail object using information from objectGetMail().  If bMailSubmitter
       is true then we first check to see whether the submitter is the one deleting the
       entry, in which case we don't send him a notification mail.
       Thus it returns null if no mail is to be sent, or a Mail object otherwise.
       bParentAction states whether the action was caused by a change to the parent
       entry, for instance this will be true when deleting a version because we
       delete its parent application. */
    private function get_mail($bMailSubmitter, $sAction, $bParentAction = FALSE)
    {
        $oObject = new $this->sClass($this->iId);

        if($bMailSubmitter)
        {
            $iSubmitterId = $oObject->objectGetSubmitterId();

            /* Should we mail the submitter? */
            if($iSubmitterId && $iSubmitterId != $_SESSION['current']->iUserId)
            {
                return new mail($oObject->objectGetMail($sAction, $bMailSubmitter,
                                                        $bParentAction),
                                $iSubmitterId);
            } else
            {
                return null;
            }
        } else
        {
            return new mail($oObject->objectGetMail("delete", $bMailSubmitter,
                                                    $bParentAction));
        }
    }

    /* Purge rejected entries, optionally by date */
    public function purgeRejected($aClean)
    {
        if(!$_SESSION['current']->hasPriv("admin"))
        {
            addmsg("Insufficient privileges", "red");
            return FALSE;
        }

        if(!$this->getOptionalSetting("objectAllowPurgingRejected", FALSE))
        {
            addmsg("Purging rejected entries is not allowed for this object type");
            return FALSE;
        }

        $oObject = $this->getObject();

        $hResult = $oObject->objectGetEntries('rejected');

        if(!$hResult)
        {
            addmsg("Failed to get list of rejected entries", "red");
            return FALSE;
        }

        if($aClean['bTimeLimit'] == 'true')
            $iSubmittedBefore = mysqltimestamp_to_unixtimestamp($aClean['sSubmittedBefore']);
        else
            $iSubmittedBefore = 0;

        $iDeleted = 0;
        $iFailed = 0;

        if($sSubmittedBefore)
            $sMailWord = "old";
        else
            $sMailWord = "all";

        while($oRow = mysql_fetch_object($hResult))
        {
            $oObject = new $this->sClass(null, $oRow);

            if(!$iSubmittedBefore || $oObject->objectGetSubmitTime() < $iSubmittedBefore)
            {
                $oM = new objectManager($this->sClass, "", $oObject->objectGetId());
                if($oM->delete_entry("Purging $sMailWord rejected entries", false))
                    $iDeleted++;
                else
                    $iFailed++;
            }
        }

        if($iFailed)
            addmsg("Failed to delete $iFailed entries", 'red');

        $sNoun = ($iDeleted == 1) ? 'entry' : 'entries';

        if($iDeleted)
            addmsg("Deleted $iDeleted $sNoun", 'green');

        $this->return_to_url(APPDB_ROOT);
    }

    public function displayPurgeRejected()
    {
        if(!$_SESSION['current']->hasPriv("admin"))
        {
            $this->error_exit('Only admins can do this');
            return FALSE;
        }

        if(!$this->getOptionalSetting("objectAllowPurgingRejected", FALSE))
        {
            $this->error_exit('Purging rejected entries is not allowed for this object type');
            return FALSE;
        }

        echo '<form action="objectManager.php" action="post" />';
        echo 'Purge rejected entries of this type<br>';
        echo '<input type="checkbox" value="true" name="bTimeLimit"> ';
        echo 'Only entries submitted before ';
        echo '<input type="text" name="sSubmittedBefore" size="25" value="'.date('Y-m-d H:i:s').'"><br><br>';
        echo '<input type="hidden" name="sAction" value="doPurgeRejected">';
        echo $this->makeUrlFormData();
        echo '<input type="submit" value="Purge">';
        echo '</form>';
    }

    /* Move all the object's children to another object of the same type, and
       delete the original object afterwards */
    public function move_children($iNewId)
    {
        $oObject = new $this->sClass($this->iId);
        $oNewObject = new $this->sClass($iNewId);

        /* The user needs to have edit rights to both the old and the new object
           If you have edit rights to an object then you should have edit rights
           to its child objects as well */
        if(!$oObject->canEdit() || !$oNewObject->canEdit())
            return FALSE;

        $iAffected = $oObject->objectMoveChildren($iNewId);

        /* Some classes record the id of their child objects, so we shouldn't keep an old instance around */
        $this->flushCachedObject();

        if($iAffected)
        {
            $sPlural = ($iAffected == 1) ? "": "s";
            addmsg("Moved $iAffected child object$sPlural", "green");
        } else if($iAfffected === FALSE)
        {
            /* We don't want to delete this object if some children were not moved */
            addmsg("Failed to move child objects", "red");
            return FALSE;
        }

        /* The argument is the reply text */
        $this->delete_entry("Duplicate entry");
    }

    /* Display a page where the user can select which object the children of the current
       object can be moved to */
    public function display_move_children()
    {
        $oObject = new $this->sClass($this->iId);
        if(!$oObject->canEdit())
        {
            echo "Insufficient privileges.<br>\n";
            return FALSE;
        }

        /* We only allow moving to non-queued objects */
        if(!$hResult = $oObject->objectGetEntries('accepted'))
        {
            echo "Failed to get list of objects.<br>\n";
            return FALSE;
        }

        /* Display some help text */
        echo "<p>Move all child objects of ".$oObject->objectMakeLink()." to the entry ";
        echo "selected below, and delete ".$oObject->objectMakeLink()." afterwards.</p>\n";

        echo "<table width=\"50%\" cellpadding=\"3\">\n";
        echo html_tr(array(
                "Name",
                "Move here"),
                    "color4");

        for($i = 0; $oRow = query_fetch_object($hResult); $i++)
        {
            $oCandidate = new $this->sClass(null, $oRow);
            if($oCandidate->objectGetId() == $this->iId)
            {
                $i++;
                continue;
            }

            echo html_tr(array(
                    $oCandidate->objectMakeLink(),
                    "<a href=\"".$this->makeUrl("moveChildren", $this->iId).
                    "&amp;iNewId=".$oCandidate->objectGetId()."\">Move here</a>"),
                        ($i % 2) ? "color0" : "color1");
        }
        echo "</table>\n";
    }

    private function show_preview($oObject, $aClean)
    {
        echo html_frame_start("Preview", "75%");

        $aVars = $this->get_custom_vars($aClean, "preview");

        if($aVars)
            $oObject->display($aVars);
        else
            $oObject->display();

        echo html_frame_end();
    }

    /* Display screen for submitting a new entry of given type */
    public function add_entry($aClean, $sErrors = "")
    {
        $this->checkMethods(array("outputEditor", "getOutputEditorValues",
                                  "update", "create"));


        $oObject = new $this->sClass();

        echo "<div class='default_container'>\n";

        /* Display errors, if any, and fetch form data */
        if($this->displayErrors($sErrors))
        {
            global $aClean;
            $oObject->getOutputEditorValues($aClean);

            if($sErrors === PREVIEW_ENTRY)
                $this->show_preview($oObject, $aClean);
        }

        /* Display help if it is exists */
        if(method_exists(new $this->sClass, "objectDisplayAddItemHelp"))
        {
            $aVars = $this->get_custom_vars($aClean, "addHelp");

            if($aVars)
                $oObject->objectDisplayAddItemHelp($aClean);
            else
                $oObject->objectDisplayAddItemHelp();
        }

        echo "<form method=\"post\" action=\"".$this->makeUrl("add")."\">\n";

        echo $this->makeUrlFormData();

        $aVars = $this->get_custom_vars($aClean, "add");

        if($aVars)
            $oObject->outputEditor($aVars);
        else
            $oObject->outputEditor();

        $this->oObject = $oObject;
        echo "<div align=\"center\">";
        echo "<input type=\"submit\" class=\"button\" value=\"Submit\" ". 
        "name=\"sSubmit\">\n";
        $this->handle_preview_button();
        echo "</div></form>\n";
        echo html_back_link(1);

        echo "</div>\n";
    }

    private function handle_preview_button()
    {
        $oObject = $this->getObject();

        if(!method_exists($oObject, "objectShowPreview"))
            return;

        if(!$oObject->objectShowPreview())
            return;

        echo '<input type="submit" name="sSubmit" class="button" value="Preview">';
    }

    public function handle_anonymous_submission()
    {
        $oObject = new $this->sClass();
        if($oObject->allowAnonymousSubmissions() || $_SESSION['current']->isLoggedIn())
            return;

        login_form();
        exit;
    }

    private function displayMoveChildren($oObject)
    {
        /* Display a link to the move child objects page if the class has the necessary
           functions and the user has edit rights.  Not all classes have child objects. */
        if(method_exists($oObject, "objectMoveChildren") &&
           method_exists($oObject, "objectGetId") && $oObject->canEdit())
        {
            echo "<a href=\"".$this->makeUrl("showMoveChildren", $this->iId,
                 "Move Child Objects")."\">Move child objects</a>\n";
        }
    }

    /* Gets the title of the page to be displayed. Classes can set
       the page title depending on the action, or return null to
       let objectManager use one, normally the title specified in
       the URL. Since this only affects the user interface and not
       functionality, objectGetCustomTitle is not a required method.
       Why do we need this method?  Think of the versions, for instance.
       If we were to fetch the name from the URL, that would mean
       that changes to the version name would not be reflected in
       static URLs, like external links to the AppDB. */
    public function get_title($sAction)
    {
        $oObject = new $this->sClass($this->iId);
        $sTitle = "";

        if(method_exists($oObject, "objectGetCustomTitle"))
            $sTitle = $oObject->objectGetCustomTitle($sAction);

        if(!$sTitle)
            $sTitle = $this->sTitle;

        return $sTitle;
    }

    /* Gets the custom variables, if any, from a class depending on
       the action which is being taken, such as viewing an entry,
       editing one etc.
       Returns null if there are no custom vars, or a labelled array
       with the variable contents otherwise */
    private function get_custom_vars($aClean, $sAction)
    {
        $oObject = new $this->sClass($this->iId);

        if(!method_exists($oObject, "objectGetCustomVars"))
            return null; /* No vars */

        $aVars = array();
        $aVarNames = $oObject->objectGetCustomVars($sAction);

        if(!$aVarNames) /* No vars */
            return null;

        foreach($aVarNames as $sVar)
            $aVars[$sVar] = $aClean[$sVar];

        return $aVars;
    }

    /* View an entry */
    public function view($sBackLink, $aClean)
    {
        $this->checkMethods(array("display"));

        $oObject = new $this->sClass($this->iId);

        $aVars = $this->get_custom_vars($aClean, "view");

        if(!$aVars)
            $oObject->display();
        else
            $oObject->display($aVars);

        // display the move children entry
        $this->displayMoveChildren($oObject);

        echo html_back_link(1, $sBackLink);
    }

    /* Process form data generated by adding or updating an entry */
    public function processForm($aClean)
    {
        // FIXME: hack so if we modify $aClean in here any objects that use the global
        // $aClean will see the modified value. Should be replaced when we have
        // general purpose objectManager email code in place since the sReplyText value
        // is the value we modify and we'll be passing that into methods in the future
        global $aClean;

        if(!isset($aClean['sSubmit']))
            return;

        $this->checkMethods(array("getOutputEditorValues", "update", "create",
                                  "canEdit"));

        $this->iId = $this->getIdFromInput($aClean);

        $oObject = new $this->sClass($this->iId);
        $oOriginalObject = new $this->sClass($this->iId);  /* Prevent possible security hole if users change key
                                                              variables, making the permission checks run on
                                                              the wrong criteria */

        /* If it isn't implemented, that means there is no default text */
        if(method_exists(new $this->sClass, "getDefaultReply"))
        {
            /* Don't send the default reply text */
            if($oObject->getDefaultReply() == $aClean['sReplyText'])
                $aClean['sReplyText'] = "";
        }

        // handle the common response radio button value
        // if sReplyText is empty, if sOMCommonReply was set because
        // the user selected a radio button then use that text instead
        if( isset($aClean['sReplyText']) && $aClean['sReplyText'] == "" && isset($aClean['sOMCommonReply']))
        {
          $aClean['sReplyText'] = $aClean['sOMCommonReply'];
        }

        $oObject->getOutputEditorValues($aClean);

        /* Check input, if necessary */
        if($aClean['sSubmit'] != "Delete" &&
                method_exists(new $this->sClass, "checkOutputEditorInput"))
        {
            $sErrors = $oObject->checkOutputEditorInput($aClean);
        }

        // NOTE: we only check for errors when submitting
        //       because there is always the possibility that we can
        //       get into some error state but we don't want to be stuck, unable
        //       to delete an entry because of an error that we don't want to
        //       have to correct
        switch($aClean['sSubmit'])
        {
            case "Preview":
                return PREVIEW_ENTRY;

            case "Submit":
                // if we have errors, return them
                if($sErrors)
                    return $sErrors;

                // if we have a valid iId then we are displaying an existing entry
                // otherwise we should create the entry in the 'else' case
                if($this->iId)
                {
                    if(!$oOriginalObject->canEdit())
                        return FALSE;

                    if($this->sState == 'rejected')
                        $oObject->ReQueue();

                    if($this->getIsQueue() && !$oOriginalObject->mustBeQueued())
                        $oObject->unQueue();

                    $oObject->update();
                } else
                {
                    $this->handle_anonymous_submission();

                    $oObject->create();
                }
                break;

            case "Reject":
                if(!$oOriginalObject->canEdit())
                    return FALSE;

                $oObject->reject();
                break;

            case "Delete":
                /* Heere we call an objectManager function instead
                   of a function of the object's class.  Thus we
                   need to store the object so changes in
                   getOutputEditorValues() are caught. */
                $this->oObject = $oObject;
                $this->delete_entry($aClean['sReplyText']);
                break;

            default:
              // shouldn't end up here, log the submit type that landed us here
              error_log::log_error(ERROR_GENERAL, "processForm() received ".
                                   "unknown aClean[sSubmit] of: ".$aClean['sSubmit']);
              return false;
        }

        /* Displaying the entire un-queued list for a class is not a good idea,
        so only do so for queued data */
        if($this->getIsQueue())
            $sRedirectLink = $this->makeUrl("view", false, $this->sReturnToTitle ? $this->sReturnToTitle : "$this->sClass list");
        else
            $sRedirectLink = APPDB_ROOT;

        $this->return_to_url($sRedirectLink);

        return TRUE;
    }

    /* Make an objectManager URL based on the object and optional parameters */
    public function makeUrl($sAction = false, $iId = false, $sTitle = false)
    {
        $sUrl = APPDB_ROOT."objectManager.php?";

        $sIsQueue = $this->getIsQueue() ? "true" : "false";
        $sUrl .= "bIsQueue=$sIsQueue";
        $sIsRejected = $this->sState == 'rejected' ? "true" : "false";
        $sUrl .= "&amp;bIsRejected=$sIsRejected";

        $sUrl .= "&amp;sClass=".$this->sClass;
        if($iId)
            $sUrl .= "&amp;iId=$iId";

        if($sAction)
            $sUrl .= "&amp;sAction=$sAction";

        if($this->sReturnTo)
            $sUrl .= "&amp;sReturnTo=".urlencode($this->sReturnTo);

        if(!$sTitle)
            $sTitle = $this->sTitle;

        if($this->sReturnToTitle)
            $sUrl .= "&amp;sReturnToTitle=".$this->sReturnToTitle;

        $sUrl .= "&amp;sTitle=".urlencode($sTitle);

        if($this->oMultiPage->bEnabled)
        {
            $sUrl .= "&amp;iItemsPerPage=".$this->oMultiPage->iItemsPerPage;
            $sUrl .= "&amp;iPage=".$this->oMultiPage->iPage;
        }

        if($this->oSortInfo && $this->oSortInfo->sCurrentSort)
        {
            $sUrl .= "&amp;sOrderBy={$this->oSortInfo->sCurrentSort}";
            $sUrl .= '&amp;bAscending='.($this->oSortInfo->bAscending ? 'true' : 'false');
        }

        return $sUrl;
    }

    /* Inserts the information in an objectManager object as form data, so that it
       is preserved when submitting forms */
    public function makeUrlFormData()
    {
        $sIsQueue = $this->getIsQueue() ? "true" : "false";
        $sIsRejected = $this->sState == 'rejected' ? "true" : "false";

        $sReturn = "<input type=\"hidden\" name=\"bIsQueue\" value=\"$sIsQueue\">\n";
        $sReturn .= "<input type=\"hidden\" name=\"bIsRejected\" value=\"$sIsRejected\">\n";
        $sReturn .= "<input type=\"hidden\" name=\"sClass\" value=\"".$this->sClass."\">\n";
        $sReturn .= "<input type=\"hidden\" name=\"sTitle\" value=\"".$this->sTitle."\">\n";
        $sReturn .= "<input type=\"hidden\" name=\"sReturnTo\" value=\"".$this->sReturnTo."\">\n";
        $sReturn .= "<input type=\"hidden\" name=\"iId\" value=\"".$this->iId."\">\n";

        if($this->oMultiPage->bEnabled)
        {
            $sReturn .= "<input type=\"hidden\" name=\"iItemsPerPage\" value=\"".
                    $this->oMultiPage->iItemsPerPage."\">\n";
            $sReturn .= "<input type=\"hidden\" name=\"iPage\" value=\"".
                    $this->oMultiPage->iPage."\">\n";
        }

        if($this->sReturnToTitle)
            $sReturn .= "<input type=\"hidden\" name=\"sReturnToTitle\" value=\"".$this->sReturnToTitle."\">\n";

        if($this->oSortInfo && $this->oSortInfo->sCurrentSort)
        {
            $sReturn .= "<input type=\"hidden\" name=\"sOrderBy\" value=\"{$this->oSortInfo->sCurrentSort}\">";
            $sReturn .= "<input type=\"hidden\" name=\"bAscending\" value=\"".($this->oSortInfo->bAscending ? 'true' : 'false')."\">";
        }

        return $sReturn;
    }

    /* Get id from form data */
    private function getIdFromInput($aClean)
    {
        $sId = "i".ucfirst($this->sClass)."Id";
        $iId = isset($aClean['sId']) ? $aClean['sId'] : $aClean['iId'];

        return $iId;
    }

    /* Output headers for a table */
    private function outputHeader($sClass)
    {
        $oObject = new $this->sClass();
        $oTableRow = $oObject->objectGetHeader();

        /* Add an action column if the user can edit this class, or if it is a queue.
           Even though a user annot process items, he can edit his queued submissions */
        if($oObject->canEdit() || $this->getIsQueue())
        {
            $oTableRow->AddTextCell("Action");
        }

        $oTableRow->SetClass($sClass);

        /* Set the current sorting info if the header is sortable */
        if(get_class($oTableRow) == "TableRowSortable")
            $oTableRow->SetSortInfo($this->oSortInfo);

        echo $oTableRow->GetString();
    }

    private function handleMultiPageControls($aClean, $bItemsPerPageSelector = TRUE)
    {
        /* Display multi-page browsing controls (prev, next etc.) if applicable.
           objectGetItemsPerPage returns FALSE if no multi-page display should be used,
           or an array of options, where the first element contains an array of items
           per page values and the second contains the default value.
           If the function does not exist we assume no multi-page behaviour */
        $oObject = new $this->sClass();

        if(!method_exists($oObject, "objectGetItemsPerPage") ||
          $oObject->objectGetItemsPerPage($this->sState) === FALSE)
        {
            /* Do not enable the MultiPage controls */
            $this->oMultiPage->MultiPage(FALSE);
            return;
        }

        $aReturn = $oObject->objectGetItemsPerPage($this->sState);
        $aItemsPerPage = $aReturn[0];
        $iDefaultPerPage = $aReturn[1];


        $iItemsPerPage = $iDefaultPerPage;

        if ( isset($aClean['iItemsPerPage']) && 
             in_array($aClean['iItemsPerPage'], $aItemsPerPage) )
        {
            $iItemsPerPage = $aClean['iItemsPerPage'];
        }
        
        // if $bItemsPerPageSelector is true, display the items
        // per-page dropdown and update button
        if($bItemsPerPageSelector)
        {
            $sControls = "<form action=\"".$this->makeUrl()."\" method=\"get\">";

            /* Fill in form data for the objectManager URL */
            $sControls .= $this->makeUrlFormData();
            $sControls .= "<p><b>&nbsp;Items per page</b>";
            $sControls .= "<select name=\"iItemsPerPage\">";

            foreach($aItemsPerPage as $iNum)
            {
                $sSelected = ($iNum == $iItemsPerPage) ? ' selected="selected"' : "";
                $sControls .= "<option$sSelected>$iNum</option>";
            }
            $sControls .= "</select>";
            $sControls .= " &nbsp; <input type=\"submit\" value=\"Update\">";
            $sControls .= "</form>";
        }

        $iTotalEntries = $oObject->objectGetEntriesCount($this->sState);
        $iNumPages = ceil($iTotalEntries / $iItemsPerPage);
        if($iNumPages == 0)
            $iNumPages = 1;

        /* Check current page value */
        $iPage = isset($aClean['iPage']) ? $aClean['iPage'] : 1;
        $iCurrentPage = min($iPage, $iNumPages);

        // if iPage is beyond the maximum number of pages, make it the
        // highest page number
        if($iPage > $iNumPages)
          $iPage = $iNumPages;

        /* Display selectors and info */
        echo '<div align="center">';
        echo "<b>Page $iPage of $iNumPages</b><br>";

        /* Page controls */
        $iPageRange = 7; // the number of page links we want to display
        display_page_range($iPage, $iPageRange, $iNumPages,
                           $this->makeUrl()."&amp;iItemsPerPage=$iItemsPerPage");

        echo $sControls;
        echo "</div>\n";

        /* Fill the MultiPage object with the LIMIT related values */
        $iLowerLimit = ($iPage - 1) * $iItemsPerPage;
        $this->oMultiPage->MultiPage(TRUE, $iItemsPerPage, $iLowerLimit);
        $this->oMultiPage->iPage = $iPage;
    }

    public function getQueueString($bQueued, $bRejected)
    {
        if($bQueued)
        {
            if($bRejected)
                $sQueueString = "rejected";
            else
                $sQueueString = "true";
        } else
            $sQueueString = "false";

        return $sQueueString;
    }

    public function getStateString($bQueued, $bRejected)
    {
        if($bQueued)
        {
            if($bRejected)
                $sStateString = 'rejected';
            else
                $sStateString = 'queued';
        } else
            $sStateString = 'accepted';

            return $sStateString;
    }

    private function displayErrors($sErrors)
    {
        if($sErrors)
        {
            /* A class's checkOutputEditorInput() may simply return TRUE if
               it wants the editor to be displayed again, without any error
               messages.  This is for example useful when gathering information
               in several steps, such as with application submission */
            if($sErrors === TRUE)
                return TRUE;


            if($sErrors == PREVIEW_ENTRY)
                return TRUE;

            echo "<font color=\"red\">\n";
            echo "The following errors were found<br>\n";
            echo "<ul>$sErrors</ul>\n";
            echo "</font><br>";
            return TRUE;
        } else
        {
            return FALSE;
        }
    }
}

class MultiPage
{
    var $iItemsPerPage;
    var $iLowerLimit; /* Internal; set by handleMultiPageControls.  We use iPage in the URls */
    var $iPage;
    var $bEnabled;

    function MultiPage($bEnabled = FALSE, $iItemsPerPage = 0, $iLowerLimit = 0)
    {
        $this->bEnabled = $bEnabled;
        $this->iItemsPerPage = $iItemsPerPage;
        $this->iLowerLimit = $iLowerLimit;
    }

    function getDataFromInput($aClean)
    {
        if(isset($aClean['iItemsPerPage']) && isset($aClean['iPage']))
            $this->bEnabled = TRUE;
        else
            return;

        $this->iItemsPerPage = $aClean['iItemsPerPage'];
        $this->iPage = $aClean['iPage'];
    }
}

class mailOptions
{
    var $bMailOnce;

    function mailOptions()
    {
        /* Set default options */
        $this->bMailOnce = FALSE;
    }
}

class mail
{
    var $sSubject;
    var $sMessage;
    var $aRecipients;

    function mail($aInput, $iRecipientId = null)
    {
        if(!$aInput)
            return;

        /* $aInput is returned from objectGetMail(); an array with the following members
           0: Mail subject
           1: Mail text
           2: Array of recipients
           If iRecipientId is set the third array member is ignored. */
        $this->sSubject = $aInput[0];
        $this->sMessage = $aInput[1];

        if($iRecipientId)
        {
            $oRecipient = new user($iRecipientId);
            $this->aRecipients = array($oRecipient->sEmail);
        } else
        {
            $this->aRecipients = $aInput[2];
        }
    }

    function send($sAction, $sReplyText)
    {
        /* We don't send empty mails */
        if(!$this->sSubject && !$this->sMessage)
            return;

        $this->sMessage .= "\n";

        $this->sMessage .= "The action was performed by ".$_SESSION['current']->sRealname."\n";

        switch($sAction)
        {
            case "delete":
                $this->sMessage .= "Reasons given\n";
            break;
        }

        $this->sMessage .= $sReplyText;

        mail_appdb($this->aRecipients, $this->sSubject, $this->sMessage);
    }
}

?>
