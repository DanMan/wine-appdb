<?php
/************************************/
/* this class represents an version */
/************************************/

require_once(BASE."include/note.php");
require_once(BASE."include/comment.php");
require_once(BASE."include/url.php");
require_once(BASE."include/screenshot.php");
require_once(BASE."include/bugs.php");
require_once(BASE."include/util.php");
require_once(BASE."include/testData.php");
require_once(BASE."include/downloadurl.php");
require_once(BASE."include/monitor.php");
require_once(BASE."include/vote.php");

define("LICENSE_OPENSOURCE", "Open Source");
define("LICENSE_SHAREWARE", "Shareware");
define("LICENSE_DEMO", "Demo");
define("LICENSE_RETAIL", "Retail");
define('LICENSE_FREETOUSE', 'Free to use');
define('LICENSE_FREETOSHARE', 'Free to use and share');

/**
 * Version class for handling versions.
 */
class version {
    var $iVersionId;
    var $iAppId;
    var $oApp; /* Parento object */
    var $sName;
    var $sDescription;
    var $sTestedRelease;
    var $sTestedRating;
    var $sSubmitTime;
    var $iSubmitterId;
    private $sState;
    var $sLicense;
    var $bHasMaintainer;
    var $aTestResults; /* Array of test result objects. Especially useful when
                          we want to preview a version before submitting it;
                          in that case there is no data in the database */
    var $iObsoleteBy; /* Whether this version is marked as obsolete, and if so which
                         version its votes should be moved to. */
    var $iMaintainerRequest; /* Temporary variable for version submisson.
                                Indicates whether the user wants to become a 
                                maintainer or monitor of the version being submitted.
                                Value denotes type of request. */

    /**
     * constructor, fetches the data.
     */
    public function Version($iVersionId = null, $oRow = null)
    {
        $this->aTestResults = array(); // should always be an array
        // we are working on an existing version
        if(!$iVersionId && !$oRow)
            return;

        /*
        * We fetch the data related to this version.
        */
        if(!$oRow)
        {
            $sQuery = "SELECT *
                    FROM appVersion
                    WHERE versionId = '?'";
            if($hResult = query_parameters($sQuery, $iVersionId))
              $oRow = query_fetch_object($hResult);
        }

        if($oRow)
        {
            $this->iVersionId = $oRow->versionId;
            $this->iAppId = $oRow->appId;
            $this->iSubmitterId = $oRow->submitterId;
            $this->sSubmitTime = $oRow->submitTime;
            $this->sName = $oRow->versionName;
            $this->sDescription = $oRow->description;
            $this->sTestedRelease = $oRow->ratingRelease;
            $this->sTestedRating = $oRow->rating;
            $this->sState = $oRow->state;
            $this->sLicense = $oRow->license;
            $this->iObsoleteBy = $oRow->obsoleteBy;
            $this->bHasMaintainer = $oRow->hasMaintainer == 'true' ? true : false;
        }
    }


    /**
     * Creates a new version.
     */
    public function create()
    {
        if(!$_SESSION['current']->canCreateVersion())
            return;

        $oApp = new application($this->iAppId);
        if($oApp->objectGetState() != 'accepted')
            $this->sState = 'pending';
        else
            $this->sState = $this->mustBeQueued() ? 'queued' : 'accepted';

        $hResult = query_parameters("INSERT INTO appVersion
                   (versionName, description, ratingRelease,
                   rating, appId, submitTime, submitterId,
                   state, license)
                       VALUES ('?', '?', '?', '?', '?', ?, '?', '?', '?')",
                           $this->sName, $this->sDescription, $this->sTestedRelease,
                           $this->sTestedRating, $this->iAppId, "NOW()",
                           $_SESSION['current']->iUserId, $this->sState,
                           $this->sLicense);

        if($hResult)
        {
            $this->iVersionId = query_appdb_insert_id();
            $this->Version($this->iVersionId);
            $this->SendNotificationMail();

            /* Submit maintainer request if asked to */
            switch($this->iMaintainerRequest)
            {
                case MAINTAINER_REQUEST;
                    $oMaintainer = new Maintainer();
                    $oMaintainer->iAppId = $this->iAppId;
                    $oMaintainer->iVersionId = $this->iVersionId;
                    $oMaintainer->iUserId = $_SESSION['current']->iUserId;
                    $oMaintainer->sMaintainReason = "This user submitted the version;". 
                                                    "auto-queued.";
                    $oMaintainer->bSuperMaintainer = 0;
                    $oMaintainer->create();
                    break;

                case MONITOR_REQUEST:
                    $oMonitor = new Monitor();
                    $oMonitor->iVersionId = $this->iVersionId;
                    $oMonitor->iUserId = $_SESSION['current']->iUserId;
                    $oMonitor->iAppId = $this->iAppId;
                    $oMonitor->create();
                    break;
            }
            return true;
        }
        else
        {
            addmsg("Error while creating a new version", "red");
            return false;
        }
    }


    /**
     * Update version.
     */
    public function update($bSilent=false)
    {
        $sWhatChanged = "";

        if(!$_SESSION['current']->hasAppVersionModifyPermission($this))
            return;

        $oVersion = new Version($this->iVersionId);

        if ($this->sName && ($this->sName!=$oVersion->sName))
        {
            if (!query_parameters("UPDATE appVersion SET versionName = '?' WHERE versionId = '?'",
                                  $this->sName, $this->iVersionId))
                return false;
            $sWhatChanged .= "Name was changed from:\n\t'".$oVersion->sName."'\nto:\n\t'".$this->sName."'\n\n";
        }     

        if ($this->sDescription && ($this->sDescription!=$oVersion->sDescription))
        {
            if (!query_parameters("UPDATE appVersion SET description = '?' WHERE versionId = '?'",
                                  $this->sDescription, $this->iVersionId))
                return false;

            if($oVersion->sDescription != "")
                $sWhatChanged .= "Description was changed from\n ".$oVersion->sDescription."\n to \n".$this->sDescription.".\n\n";
            else
                $sWhatChanged .= "Description was changed to \n".$this->sDescription.".\n\n";
        }

        if ($this->sTestedRelease && ($this->sTestedRelease!=$oVersion->sTestedRelease))
        {
            if (!query_parameters("UPDATE appVersion SET ratingRelease = '?' WHERE versionId = '?'",
                                  $this->sTestedRelease, $this->iVersionId))
                return false;

            if($oVersion->sTestedRelease != "")
                $sWhatChanged .= "Last tested release was changed from ".$oVersion->sTestedRelease." to ".$this->sTestedRelease.".\n\n";
            else
                $sWhatChanged .= "Last tested release was changed to ".$this->sTestedRelease.".\n\n";
        }

        if ($this->sTestedRating && ($this->sTestedRating!=$oVersion->sTestedRating))
        {
            if (!query_parameters("UPDATE appVersion SET rating = '?' WHERE versionId = '?'",
                                  $this->sTestedRating, $this->iVersionId))
                return false;

            if($this->sTestedRating != "")
                $sWhatChanged .= "Rating was changed from ".$oVersion->sTestedRating." to ".$this->sTestedRating.".\n\n";
            else
                $sWhatChanged .= "Rating was changed to ".$this->sTestedRating.".\n\n";
        }
     
        if ($this->iAppId && ($this->iAppId!=$oVersion->iAppId))
        {
            $oAppBefore = new Application($oVersion->iAppId);
            $oAppAfter = new Application($this->iAppId);

            if($oAppAfter->objectGetState() == 'accepted' && $this->sState == 'pending')
                $this->sState = 'queued';

            if (!query_parameters("UPDATE appVersion SET appId = '?', state = '?' WHERE versionId = '?'",
                                  $this->iAppId, $this->sState, $this->iVersionId))
                return false;

            $sWhatChanged .= "Version was moved from application ".$oAppBefore->sName." to application ".$oAppAfter->sName.".\n\n";
        }

        if ($this->sLicense && ($this->sLicense!=$oVersion->sLicense))
        {
            if(!query_parameters("UPDATE appVersion SET license = '?'
                                  WHERE versionId = '?'",
                                      $this->sLicense, $this->iVersionId))
            return FALSE;

            $sWhatChanged .= "License was changed from $oVersion->sLicense to ".
                             "$this->sLicense.\n\n";
        }

        if($this->iObsoleteBy != $oVersion->iObsoleteBy)
        {
            if(!query_parameters("UPDATE appVersion SET obsoleteBy = '?' WHERE versionId = '?'",
                                 $this->iObsoleteBy, $this->iVersionId))
                return FALSE;

            if($this->iObsoleteBy)
                $sWhatChanged .= "The version was marked as obsolete.\n\n";
            else
                $sWhatChanged .= "The version is no longer marked as obsolete.\n\n";

            if($this->iObsoleteBy)
            {
                query_parameters("UPDATE appVotes SET versionId = '?' WHERE versionId = '?'",
                                $this->iObsoleteBy, $this->iVersionId);
            }
        }

        if($this->objectGetState() != $oVersion->objectGetState())
            query_parameters("UPDATE appVersion SET state = '?' WHERE versionId = '?'", $this->objectGetState(), $this->objectGetId());

        if($sWhatChanged and !$bSilent)
            $this->SendNotificationMail("edit",$sWhatChanged);
        return true;
    }

    /**
     * Removes the version from the database and
     * requests the same action for child entries
     */
    public function purge()
    {
        /* We need the versionId to continue */
                if(!$this->iVersionId)
                return;

        /* is the current user allowed to delete this version? */
                if(!$_SESSION['current']->canDeleteVersion($this))
                return false;

        $bSuccess = TRUE;

        foreach($this->objectGetChildren(TRUE) as $oChild)
        {
            if(!$oChild->purge())
                $bSuccess = FALSE;
        }

        /* now remove the version from the DB */
        $hResult = query_parameters("DELETE FROM appVersion
                WHERE versionId = '?' 
                LIMIT 1", $this->iVersionId);
        if(!$hResult)
            $bSuccess = FALSE;

        return $bSuccess;
    }

    /**
     * Flags the version as deleted
     * and request the deletion of linked elements.
     */
    public function delete()
    {
        /* We need the versionId to continue */
        if(!$this->iVersionId)
            return;

        /* is the current user allowed to delete this version? */
        if(!$_SESSION['current']->canDeleteVersion($this))
            return false;

        $bSuccess = TRUE;

        foreach($this->objectGetChildren() as $oChild)
        {
            if(!$oChild->delete())
                $bSuccess = FALSE;
        }

        /* now flag the version as deleted */
        $hResult = query_parameters("UPDATE appVersion SET state = 'deleted'
                                     WHERE versionId = '?' 
                                     LIMIT 1", $this->iVersionId);
        if(!$hResult)
            $bSuccess = FALSE;

        return $bSuccess;
    }

    /**
     * Move version out of the queue.
     */
    public function unQueue()
    {
        if(!$_SESSION['current']->canUnQueueVersion($this))
            return;

        // If we are not in the queue, we can't move the version out of the queue.
        if($this->sState == 'accepted')
            return false;

        if(query_parameters("UPDATE appVersion SET state = '?' WHERE versionId = '?'",
                            'accepted', $this->iVersionId))
        {
            $this->sState = 'accepted';
            // we send an e-mail to interested people
            $this->mailSubmitter("add");
            $this->SendNotificationMail();

            /* Unqueue matching maintainer request */
            $hResultMaint = query_parameters("SELECT maintainerId FROM 
            appMaintainers WHERE userId = '?' AND versionId = '?'", 
            $this->iSubmitterId, $this->iVersionId);

            if($hResultMaint && query_num_rows($hResultMaint))
            {
                $oMaintainerRow = query_fetch_object($hResultMaint);
                $oMaintainer = new Maintainer($oMaintainerRow->maintainerId);
                $oMaintainer->unQueue("OK");
            }
        }
    }

    public function Reject($bSilent=false)
    {
        if(!$_SESSION['current']->canRejectVersion($this))
            return;

        // If we are not in the queue, we can't move the version out of the queue.
        if($this->sState != 'queued')
            return false;

        if(query_parameters("UPDATE appVersion SET state = '?' WHERE versionId = '?'",
                            'rejected', $this->iVersionId))
        {
            $this->sState = 'rejected';
            // we send an e-mail to interested people
            if(!$bSilent)
            {
                $this->mailSubmitter("reject");
                $this->SendNotificationMail("reject");
            }
            // the version has been unqueued
            addmsg("The version has been rejected.", "green");
        }
    }

    public function ReQueue()
    {
        if(!$_SESSION['current']->canRequeueVersion($this))
            return;

        if(query_parameters("UPDATE appVersion SET state = '?' WHERE versionId = '?'",
                            'queued', $this->iVersionId))
        {
            $this->sState = 'queued';
            // we send an e-mail to interested people
            $this->SendNotificationMail();

            // the version has been unqueued
            addmsg("The version has been re-submitted", "green");
        }
    }

    public function objectGetSubmitterId()
    {
        return $this->iSubmitterId;
    }

    public static function objectGetMailOptions($sAction, $bMailSubmitter,
                                                $bParentAction)
    {
        return new mailOptions();
    }

    public function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        $oApp = new application($this->iAppId);

        if($bMailSubmitter)
        {
            switch($sAction)
            {
                case "delete":
                    $sSubject = "Submitted version deleted";
                    $sMsg  = "The version you submitted (".$oApp->sName." ".$this->sName.
                             ") has been deleted.";
                break;
            }
            $aMailTo = null;
        } else
        {
            switch($sAction)
            {
                case "delete":
                    $sSubject = "Version '".$this->sName."' of '".$oApp->sName."' ".
                                "deleted";
                    $sMsg = "";
                break;
            }
            $aMailTo = User::get_notify_email_address_list(null, $this->iVersionId);
        }
        return array($sSubject, $sMsg, $aMailTo);
    }

    private function mailSubmitter($sAction="add")
    {
        global $aClean; //FIXME: we should pass the sReplyText value in

        // use 'sReplyText' if it is defined, otherwise define the value as an empty string
        if(!isset($aClean['sReplyText']))
            $aClean['sReplyText'] = "";

        if($this->iSubmitterId)
        {
            $oApp = new Application($this->iAppId);
            $oSubmitter = new User($this->iSubmitterId);
            switch($sAction)
            {
            case "add":
                $sSubject = "Submitted version accepted";
                $sMsg  = "The version you submitted (".$oApp->sName." ".$this->sName.") has been accepted by ".$_SESSION['current']->sRealname.".\n";
                $sMsg .= "Administrators response:\n";
            break;
            case "reject":
                $sSubject = "Submitted version rejected";
                $sMsg  = "The version you submitted (".$oApp->sName." ".$this->sName.") has been rejected by ".$_SESSION['current']->sRealname.".";
                $sMsg .= "Clicking on the link in this email will allow you to modify and resubmit the version. ";
                $sMsg .= "A link to your queue of applications and versions will also show up on the left hand side of the Appdb site once you have logged in. ";
                $sMsg .= APPDB_ROOT."objectManager.php?sClass=version_queue".
                        "&amp;bIsQueue=true&amp;bIsRejected=true&amp;iId=".$this->iVersionId."&amp;".
                        "sTitle=Edit+Version\n";
            break;
            }
            $sMsg .= $aClean['sReplyText']."\n";
            $sMsg .= "We appreciate your help in making the Version Database better for all users.";
        
            mail_appdb($oSubmitter->sEmail, $sSubject ,$sMsg);
        }
    }

    private function SendNotificationMail($sAction="add",$sMsg=null)
    {
        global $aClean;
        
        // use 'sReplyText' if it is defined, otherwise define the value as an empty string
        if(!isset($aClean['sReplyText']))
            $aClean['sReplyText'] = "";

        $oApp = new Application($this->iAppId);
        switch($sAction)
        {
            case "add":
                if($this->sState == 'accepted')
                {
                    $sSubject = "Version ".$this->sName." of ".$oApp->sName." added by ".$_SESSION['current']->sRealname;
                    $sMsg  = $this->objectMakeUrl()."\n";
                    if($this->iSubmitterId)
                    {
                        $oSubmitter = new User($this->iSubmitterId);
                        $sMsg .= "This version has been submitted by ".$oSubmitter->sRealname.".";
                        $sMsg .= "\n";
                    }
                    if($aClean['sReplyText'])
                    {
                        $sMsg .= "Appdb admin reply text:\n";
                        $sMsg .= $aClean['sReplyText']."\n"; // append the reply text, if there is any 
                    }

                    addmsg("The version was successfully added into the database.", "green");
                } else // Version queued.
                {
                    $sSubject = "Version '".$this->sName."' of '".$oApp->sName."' submitted by ".$_SESSION['current']->sRealname;
                    $sMsg .= "This version has been queued.";
                    $sMsg .= "\n";
                    addmsg("The version you submitted will be added to the database after being reviewed.", "green");
                }
            break;
            case "edit":
                $sSubject =  "'".$oApp->sName." ".$this->sName."' has been modified by ".$_SESSION['current']->sRealname;
                $sMsg  .= $this->objectMakeUrl()."\n";
                addmsg("Version modified.", "green");
            break;
            case "delete":

                // if sReplyText is set we should report the reason the application was deleted 
                if($aClean['sReplyText'])
                {
                    $sMsg .= "Reason given:\n";
                    $sMsg .= $aClean['sReplyText']."\n"; // append the reply text, if there is any 
                }

                addmsg("Version deleted.", "green");
            break;
            case "reject":
                $sSubject = "Version '".$this->sName."' of '".$oApp->sName."' has been rejected by ".$_SESSION['current']->sRealname;
                $sMsg .= APPDB_ROOT."objectManager.php?sClass=version_queue".
                        "&amp;bIsQueue=true&amp;bIsRejected=true&amp;iId=".$this->iVersionId."&amp;".
                        "sTitle=Edit+Version\n";

                // if sReplyText is set we should report the reason the version was rejected 
                if($aClean['sReplyText'])
                {
                    $sMsg .= "Reason given:\n";
                    $sMsg .= $aClean['sReplyText']."\n"; // append the reply text, if there is any 
                }

                addmsg("Version rejected.", "green");
            break;
        }
        $sEmail = User::get_notify_email_address_list(null, $this->iVersionId);
        if($sEmail)
            mail_appdb($sEmail, $sSubject ,$sMsg);
    } 

    public function get_buglink_ids()
    {
        /*
         * We fetch Bug linkIds. 
         */
        $aBuglinkIds = array();
        $sQuery = "SELECT *
                       FROM buglinks
                       WHERE versionId = '?'
                       ORDER BY bug_id";
        if($hResult = query_parameters($sQuery, $this->iVersionId))
        {
            while($oRow = query_fetch_object($hResult))
            {
                $aBuglinkIds[] = $oRow->linkId;
            }
        }

        return $aBuglinkIds;
    }

    /* Makes a frame with title 'Mark as obsolete' and info about what it means, plus
       caller-defined content */
    private static function makeObsoleteFrame($sContent = "")
    {
        $sMsg = html_frame_start("Mark as obsolete", "90%", "", 0);

        $sMsg .= "Some applications need to be updated from time to time in order to ";
        $sMsg .= "be of any use. An example is online multi-player games, where you need ";
        $sMsg .= "to be running a version compatible with the server. ";
        $sMsg .= "If this is such an application, and this version is no longer usable, ";
        $sMsg .= "you can mark it as obsolete and move its current votes to a usable ";
        $sMsg .= "version instead.<br><br>";

        $sMsg .= $sContent;

        $sMsg .= html_frame_end();

        return $sMsg;
    }

    /* output html and the current versions information for editing */
    /* if $editParentApplication is true that means we need to display fields */
    /* to let the user change the parent application of this version */
    /* otherwise, if $editParentAppliation is false, we leave them out */
    public function outputEditor()
    {
        HtmlAreaLoaderScript(array("version_editor"));
        echo html_frame_start("Version Form", "90%", "", 0);

        echo '<input type="hidden" name="iVersionId" value="'.$this->iVersionId.'">';

        $oTable = new Table();
        $oTable->SetClass("color0");
        $oTable->SetWidth("100%");
        $oTable->SetBorder(0);
        $oTable->SetCellPadding(2);
        $oTable->SetCellSpacing(0);

        /* Fill in appId value */
        global $aClean;
        if(!$this->iAppId)
            $this->iAppId = $aClean['iAppId'];

        if($this->sState == 'accepted' && $this->iVersionId)
        {
            // app parent
            $x = new TableVE("view");
            $oTableRow = new TableRow();
            $oTableRow->SetValign("top");

            $oTableCell = new TableCell("Application");
            $oTableCell->SetBold(true);
            $oTableRow->AddCell($oTableCell);

            $sOptionList = $x->make_option_list("iAppId", $this->iAppId,
                                                "appFamily", "appId", "appName");
            $oTableCell = new TableCell($sOptionList);
            $oTableCell->SetClass("color0");
            
            $oTableRow->AddCell($oTableCell);

            $oTable->AddRow($oTableRow);
        } else
        {
            echo '<input type="hidden" name="iAppId" value="'.$this->iAppId.'">';
        }

        // version name
        $oTableRow = new TableRow();
        $oTableRow->SetValign("top");

        $oTableCell = new TableCell("Version Name");
        $oTableCell->SetBold(true);
        $oTableCell->SetClass("color0");
        $oTableRow->AddCell($oTableCell);

        $oTableRow->AddTextCell('<input size="20" type="text" name="sVersionName" value="'.$this->sName.'">');
        
        $oTable->AddRow($oTableRow);

        // version license
        $oTableRow = new TableRow();
        $oTableCell = new TableCell("License");
        $oTableCell->SetBold(true);
        $oTableCell->SetClass("color0");
        $oTableRow->AddCell($oTableCell);

        $oTableRow->AddTextCell($this->makeLicenseList());

        $oTable->AddRow($oTableRow);

        // version description
        $oTableRow = new TableRow();
        $oTableRow->SetValign("top");
        
        $oTableCell = new TableCell("Version description");
        $oTableCell->SetBold(true);
        $oTableCell->SetClass("color0");
        $oTableRow->AddCell($oTableCell);

        $oTableRow->AddTextCell('<p><textarea cols="80" rows="20" id="version_editor" name="shVersionDescription">'.
                                $this->sDescription.'</textarea></p>');

        $oTable->AddRow($oTableRow);

        // output the table
        echo $oTable->GetString();

        echo html_frame_end();

        if($this->sState == 'accepted' && $this->iVersionId)
        {
            /* Mark as obsolete */
            $oApp = new application($this->iAppId);
            $oVersionInDB = new version($this->iVersionId);

            if($oVersionInDB->iObsoleteBy)
            {
                $sObsoleteTxt = "<input type=\"checkbox\" name=\"bObsolete\" value=\"true\" checked=\"checked\">";
                $sObsoleteTxt .= " This version is obsolete";
                echo $this->makeObsoleteFrame($sObsoleteTxt);

                echo "<input type=\"hidden\" name=\"iObsoleteBy\" value=\"".
                     $oVersionInDB->iObsoleteBy."\" type=\"hidden\" >\n";
            } else if(sizeof($oApp->getVersions(TRUE, FALSE)) > 1)
            {
                if($this->iObsoleteBy)
                    $sObsolete = "checked=\"checked\"";
                else
                    $sObsolete = "";

                $sObsoleteTxt = "<input type=\"checkbox\" name=\"bObsolete\" value=\"true\"$sObsolete >";
                $sObsoleteTxt .= "Mark as obsolete and move votes to \n";
                $sObsoleteTxt .= $oApp->makeVersionDropDownList("iObsoleteBy", $this->iObsoleteBy, $this->iVersionId, FALSE);

                echo $this->makeObsoleteFrame($sObsoleteTxt);
            }
        } else
        {
            echo '<input type="hidden" name="sMaintainerRating" value="'.$this->sTestedRating.'">';
            echo '<input type="hidden" name="sMaintainerRelease" value="'.$this->sTestedRelease.'">';
        }
    }

    public function CheckOutputEditorInput($aValues)
    {
        $errors = "";

        if (empty($aValues['sVersionName']))
            $errors .= "<li>Please enter an application version.</li>\n";

        if (empty($aValues['shVersionDescription']))
            $errors .= "<li>Please enter a version description.</li>\n";

        return $errors;
    }

    /* retrieves values from $aValues that were output by outputEditor() */
    /* $aValues can be $_REQUEST or any array with the values from outputEditor() */
    public function GetOutputEditorValues($aValues)
    {
        if($aValues['iAppId'])
            $this->iAppId = $aValues['iAppId'];

        if($aValues['iVersionId'])
            $this->iVersionId = $aValues['iVersionId'];

        $this->sName = $aValues['sVersionName'];
        $this->sDescription = $aValues['shVersionDescription'];
        $this->sLicense = $aValues['sLicense'];
        $this->iMaintainerRequest = $aValues['iMaintainerRequest'];

        if($aValues['bObsolete'] == "true")
            $this->iObsoleteBy = $aValues['iObsoleteBy'];
        else
            $this->iObsoleteBy = 0;
    }

    public function objectGetCustomTitle($sAction)
    {
        switch($sAction)
        {
            case 'delete':
                return 'Delete '.version::fullName($this->iVersionId);
            case "view":
                return version::fullName($this->iVersionId);

            default:
                return null;
        }
    }

    public static function objectGetCustomVars($sAction)
    {
        switch($sAction)
        {
            case "view":
                /* Allow the user to select which test report is
                   shown in the version view */
                return array("iTestingId");

            default:
                return null;
        }
    }

    public function objectShowPreview()
    {
        return TRUE;
    }

    public function objectSetParent($iNewId, $sClass = '')
    {
        $this->iAppId = $iNewId;
    }

    /* Not standard OM function yet, but will be in the future */
    public function objectGetParent($sClass = '')
    {
        /* No id so we can't query the DB, but perhaps an entry is cached? */
        if(!$this->iAppId && $this->oApp)
            return $this->oApp;

        return new application($this->iAppId);
    }

    /* Only show children of (grand)parents in the Move Child Objects and Change Parent lists */
    public static function objectRestrictMoveObjectListsToParents()
    {
        return true;
    }

    public function getRatingInfo()
    {
        return testData::getRatingInfoForVersionId($this->iVersionId);
    }

    public function updateRatingInfo()
    {
        $aRatingInfo = $this->getRatingInfo();

        $hResult = query_parameters("UPDATE appVersion SET
                                    rating = '?',
                                    ratingRelease = '?'
                                    WHERE versionId = '?'",
                                    $aRatingInfo[0],
                                    $aRatingInfo[1],
                                    $this->iVersionId);

        if(!$hResult)
            return false;

        return true;
    }

    public function display($aVars = array())
    {
        /* is this user supposed to view this version? */
        if(!$_SESSION['current']->canViewVersion($this))
        {
            $sError = 'You do not have permission to view this entry';

            if($this->objectGetState() == 'deleted')
                $sError = 'This entry has been deleted; it\'s contents may have been moved to another entry';

            objectManager::error_exit($sError);
        }

        $iTestingId = $aVars['iTestingId'] ? $aVars['iTestingId'] : 0;

        $oApp = $this->objectGetParent();

        // cat
        $oCategory = new Category($oApp->iCatId);
        $oCategory->displayPath($oApp->iAppId, $this->iVersionId);
  
        // set URL
        $appLinkURL = ($oApp->sWebpage) ? trimmed_link($oApp->sWebpage,30) : "&nbsp;";

        // start version display
        echo html_frame_start("","98%","",0);
        echo '<tr><td class="color4" valign="top">',"\n";
        echo '<table width="250" border="0" cellpadding="3" cellspacing="1">',"\n";
        echo "<tr class=\"color0\" valign=\"top\"><td width=\"100\"> <b>Name</b></td><td width=\"100%\">".$oApp->sName."</td>\n";
        echo "<tr class=\"color1\" valign=\"top\"><td><b>Version</b></td><td>".$this->sName."</td></tr>\n";
        echo html_tr(array(
            "<b>License</b>",
            $this->sLicense),
            "color0");

        // main URL
        echo "        <tr class=\"color1\"><td><b>URL</b></td><td>".$appLinkURL."</td></tr>\n";

        // Votes
        if(!$this->iObsoleteBy)
        {
            $oM = new objectManager("voteManager", "Vote");
            $oM->setReturnTo($this->objectMakeUrl());

            if($_SESSION['current']->isLoggedIn())
                $shVoteLink = ' &nbsp; <a href="'.$oM->makeUrl("edit", $_SESSION['current']->iUserId).'&amp;iVersionId='.$this->iVersionId.'">Vote</a>';
            else
                $shVoteLink = '';

            // Allow admins to see which users have voted, in order to identify
            // bogus votes
            if($_SESSION['current']->hasPriv('admin'))
            {
                $oMVoteInspector = new objectManager('voteInspector', 'Vote inspector');
                $shVoteLink .= ' &nbsp; <a href="'.$oMVoteInspector->makeUrl('edit',$this->iVersionId).'">Inspect</a>';
            }

            $shVoteText = vote_count_version_total($this->iVersionId).$shVoteLink;
        } else
        {
            $shVoteText = 'Marked as obsolete';
        }

        echo html_tr(array('<b>Votes</b>', $shVoteText), 'color0');

        $sRating = $this->sTestedRating;
        $sRelease = $this->sTestedRelease;
        if($sRating != "/" && $sRating)
            $sRatingColor = $sRating;
        else
            $sRatingColor = 'color0';

        // URLs
        if($sUrls = url::display($this->iVersionId))
        {
            echo $sUrls;
        }

        // rating Area
                echo "<tr class=\"$sRatingColor\" valign=\"top\"><td><b>Rating</b></td><td>".$sRating."</td></tr>\n";
        echo "<tr class=\"$sRatingColor\" valign=\"top\"><td><b>Wine Version</b></td><td>".$sRelease."</td></tr>\n";

        // Download URLs
        if($sDownloadurls = downloadurl::display($this->iVersionId))
            echo $sDownloadurls;

        // image
        $img = Screenshot::get_random_screenshot_img($oApp->iAppId, $this->iVersionId, false);
        echo "<tr><td align=\"center\" colspan=\"2\">$img</td></tr>\n";

        // display all maintainers of this application
        echo "<tr class=\"color0\"><td align=\"left\" colspan=\"2\"><b>Maintainers of this version:</b>\n";
        echo "<table width=\"250\" border=\"0\">";
        $aMaintainers = $this->getMaintainersUserIds();
        if(sizeof($aMaintainers)>0)
        {
            echo "<tr class=\"color0\"><td align=\"left\" colspan=\"2\"><ul>";
            while(list($index, $userIdValue) = each($aMaintainers))
            {
                $oUser = new User($userIdValue);
                echo "<li>".$oUser->objectMakeLink()."</li>";
            }
            echo "</ul></td></tr>\n";
        } else
        {
            echo "<tr class=color0><td align=right colspan=2>";
            echo "No maintainers. Volunteer today!</td></tr>\n";
        }
        echo "</table></td></tr>\n";

        // display the app maintainer button
        echo '<tr><td colspan="2" align="center">'."\n";
        if($_SESSION['current']->isLoggedIn())
        {
            /* is this user a maintainer of this version by virtue of being a super maintainer */
            /* of this app family? */
            if($_SESSION['current']->isSuperMaintainer($oApp->iAppId))
            {
                echo '<form method="post" name="sMessage" action="maintainerdelete.php">'."\n";
                echo "\t".'<input type="submit" value="Remove yourself as a super maintainer" class="button">'."\n";
                echo "\t".'<input type="hidden" name="iSuperMaintainer" value="1">'."\n";
                echo "\t<input type=hidden name=\"iAppId\" value=\"".$oApp->iAppId."\">\n";
                echo "\t<input type=hidden name=\"iVersionId\" value=\"".$this->iVersionId."\">\n";
                echo "</form>\n";
            } else
            {
                /* are we already a maintainer? */
                if($_SESSION['current']->isMaintainer($this->iVersionId)) /* yep */
                {
                    echo '<form method="post" name="sMessage" action="maintainerdelete.php">'."\n";
                    echo "\t".'<input type="submit" value="Remove yourself as a maintainer" class=button>'."\n";
                    echo "\t".'<input type="hidden" name="iSuperMaintainer" value="0">'."\n";
                    echo "\t"."<input type=hidden name=\"iAppId\" value=\"".$oApp->iAppId."\">\n";
                    echo "\t"."<input type=hidden name=\"iVersionId\" value=\"".$this->iVersionId."\">\n";
                    echo "</form>\n";
                } else /* nope */
                {
                    echo '<form method="post" name="sMessage" action="objectManager.php?sClass=maintainer&amp;sAction=add&amp;iVersionId='.$this->iVersionId.'&amp;sTitle='.urlencode("Be a maintainer of ".version::fullName($this->iVersionId)).'&amp;sReturnTo='.urlencode($this->objectMakeUrl()).'">'."\n";
                    echo "\t".'<input type="submit" value="Be a maintainer of this version" class="button" title="Click here to know more about maintainers.">'."\n";
                    echo "\t"."<input type=hidden name=\"iAppId\" value=\"".$oApp->iAppId."\">\n";
                    echo "\t"."<input type=hidden name=\"iVersionId\" value=\"".$this->iVersionId."\">\n";
                    echo "</form>\n";
                    $oMonitor = new Monitor();
                    $oMonitor->find($_SESSION['current']->iUserId, $this->iVersionId);
                    if(!$oMonitor->iMonitorId)
                    {
                        echo '<form method="post" name="sMessage" action="'.
                                 APPDB_ROOT."objectManager.php\">\n";
                        echo "\t<input type=\"hidden\" name=\"iAppId\" value=\"".
                                $this->iAppId."\" >\n";
                        echo "\t<input type=\"hidden\" name=\"iVersionId\" value=\"".
                                $this->iVersionId."\" >\n";
                        echo "\t<input type=\"hidden\" name=\"sSubmit\" value=\"Submit\" >\n";
                        echo "\t<input type=\"hidden\" name=\"sClass\" value=\"monitor\" >\n";
                        echo "\t<input type=\"hidden\" name=\"sReturnTo\" value=\"".
                                $this->objectMakeUrl()."\" >\n";
                        echo "\t".'<input type=submit value="Monitor changes" class="button">'."\n";
                        echo "</form>\n";
                    }
                }
            }
            
        } else
        {
            echo '<form method="post" name="sMessage" action="account.php">'."\n";
            echo "\t".'<input type="hidden" name="sCmd" value="login">'."\n";
            echo "\t".'<input type=submit value="Log in to become an app maintainer" class="button">'."\n";
            echo '</form>'."\n";
        }
    
        echo "</td></tr>";

        if ($_SESSION['current']->hasPriv("admin") || $_SESSION['current']->isMaintainer($this->iVersionId) || $_SESSION['current']->isSuperMaintainer($this->iAppId))
        {
            $shAdd = '<form method="post" name="sMessage" action="objectManager.php?sClass=note&amp;sAction=add&amp;iVersionId='.$this->iVersionId.'&amp;sReturnTo='.urlencode($this->objectMakeUrl());
            echo '<tr><td colspan="2" align="center">'."\n";
            echo '<form method="post" name="sMessage" action="admin/editAppVersion.php">'."\n";
            echo "\t".'<input type="hidden" name="iAppId" value="'.$oApp->iAppId.'">'."\n";
            echo "\t".'<input type="hidden" name="iVersionId" value="'.$this->iVersionId.'">'."\n";
            echo "\t".'<input type=submit value="Edit version" class="button">'."\n";
            echo '</form>'."\n";
            $url = BASE."objectManager.php?sClass=version&amp;sAction=delete&amp;bQueued=false&amp;iId=".$this->iVersionId;
            echo "<form method=\"post\" name=\"sDelete\" action=\"javascript:self.location = '".$url."'\">\n";
            echo "\t".'<input type=submit value="Delete version" class="button">'."\n";
            echo '</form>'."\n";
            echo $shAdd.'" />';
            echo "\t".'<input type="submit" value="Add note/how-to" class="button">'."\n";
            echo '</form>'."\n";
            echo '<form method="post" action="'.BASE.'objectManager.php?sClass=tagNoteVersionAssignMgr&amp;sAction=edit&amp;iId='.$this->iVersionId.'&amp;sReturnTo='.urlencode($this->objectMakeUrl()).'">';
            echo '<input type="submit" value="Manage notes" />';
            echo '</form>';
            echo "</td></tr>";
        }
        $oMonitor = new Monitor();
        $oMonitor->find($_SESSION['current']->iUserId, $this->iVersionId);
        if($oMonitor->iMonitorId)
        {
            echo '<tr><td colspan="2" align="center">'."\n";
            echo '</form>'."\n";
            echo '<form method="post" name="sMessage" action="'.
                    APPDB_ROOT."objectManager.php\">\n";
            echo "\t<input type=\"hidden\" name=\"iId\" value=\"".
                    $oMonitor->iMonitorId."\">\n";
            echo "\t<input type=\"hidden\" name=\"sSubmit\" value=\"Delete\">\n";
            echo "\t<input type=\"hidden\" name=\"sClass\" value=\"monitor\">\n";
            echo "\t<input type=\"hidden\" name=\"sReturnTo\" value=\"".
                    $this->objectMakeUrl()."\">\n";
            echo '<input type=submit value="Stop monitoring version" class="button" >'."\n";
            echo "</form>\n";
            echo "</td></tr>\n";
        } 
        echo "</table>\n";

        // start of the right hand pane in the version display
        echo "<td class=color2 valign=top width='100%'>\n";
        echo "<div class='version_info_pane'>\n";

        /////////////////////////
        // output the description
        echo "<div class='info_container'>\n";

        // output the description title
        echo "\t<div class='title_class'>\n";
        echo "\t\tDescription\n";
        echo "\t</div>\n";

        // output the description
        echo "\t<div class='info_contents'>\n";
        echo "\t\t".$this->sDescription."\n";
        echo "\t</div>\n";

        echo "</div>\n"; // end the 'info_container' div
        // end description
        /////////////////////////


        //////////////////////
        // Show test data

        $iNewestId = 0;
        $oTest = null;

        /* Set if the use chose to display a particular test report */
        if($iTestingId)
	{
            $oTest = new testData($iTestingId);
	    $oTestParent = $oTest->objectGetParent();

	    /* Check that the test report doesn't belong to another version */
	    if($oTestParent->objectGetId() && $oTestParent->objectGetId() != $this->objectGetId())
		$oTest = null;
	}

        if(!$oTest && $this->iVersionId) /* Let's query for the latest rest report */
        {
            $iNewestId = testData::getNewestTestIdFromVersionId($this->iVersionId);
            $iTestingId = $iNewestId;

            if($iTestingId) /* We want all entries to have test data, but old versions might lack
                               it, or data may have been deleted */
                $oTest = new testData($iTestingId);
        } else if(!$oTest) /* Perhaps we have a cached entry? There should be */
        {
            $aTests = $this->getTestResults();

            if(sizeof($aTests)) /* ... but we cannot be certain */
                $oTest = $aTests[0];
        }

        if($oTest)
        {
            if($oTest->isOld())
            {
                if($iNewestId != $oTest->objectGetId())
                {
                    $sWarnOldText = 'The test results you have selected are very old and may not represent the current state of Wine.';
                } else
                {
                    $sWarnOldText = 'The test results for this version are very old, and as such they may not represent '.
                                    'the current state of Wine.  Please consider submitting a new test report.';
                }
                echo html_note('Old test results', $sWarnOldText);
            }

            echo "<div class='info_container'>\n";

            echo "\t<div class='title_class'>\n";
            echo "\t\tSelected Test Results <small><small>(selected in 'Test Results' table below)</small></small>\n";
            echo "\t</div>\n";

            echo "<div class='info_contents'>\n";

            $oTest->ShowTestResult();

            echo "</div>\n";

            echo "</div>\n";
        } else /* Show a note saying that no test results are present,
                  and encourage the user to take action */
        {
            echo html_note('No Test Results',
                           'This version has no test results, please consider submitting some.<br>'.
                           'They may be part of the '.
                           'version or application description. If they are, please '.
                           'consider becoming a maintainer and remove them, submitting '.
                           'a proper test report instead.');
        }

        // end the 'info_container' div
        // end show test data
        /////////////////////


        //////////////////////////////
        // show the test results table
	if($oTest)
	{
            if($oTest->iTestingId)
            {
                $oTest->ShowVersionsTestingTable($this->objectMakeUrl()."&amp;iTestingId=", 5);
            } else /* We are previewing the version */
            {
                $oTable = $oTest->CreateTestTable();
                $oTable->AddRow($oTest->CreateTestTableRow(0, ""));
                echo $oTable->GetString();
            }
        }

        if($_SESSION['current']->isLoggedIn())
        {
            echo '<form method=post name=sMessage action=objectManager.php?'.
                    'sClass=testData_queue&amp;sAction=add&amp;iVersionId='.$this->iVersionId.
                    '&amp;sTitle=Add+Test+Data&amp;sReturnTo='.
                    urlencode($this->objectMakeUrl()).'>'."\n";
            echo "\t".'<input type=submit value="Add test data" class="button" >'."\n";
            echo '</form>'."\n";
        } else
        {
            echo '<form method="post" name="sMessage" action="'.login_url().'">'."\n";
            echo "\t".'<input type="hidden" name="sCmd" value="login">'."\n";
            echo "\t".'<input type=submit value="Log in to add test data" class="button">'."\n";
            echo '</form>'."\n";
        }

        // end show test results table
        /////////////////////////////


        echo "</div>\n"; // end the version info pane, the right hand pane in the
                         // version display

        echo html_frame_end();

        view_version_bugs($this->iVersionId, $this->get_buglink_ids());    

        echo note::displayNotesForEntry($this->iVersionId);

        // Comments Section
        if($this->iVersionId)
            Comment::view_app_comments($this->iVersionId);
    }

    public static function lookup_name($versionId)
    {
        if(!$versionId) return null;
        $result = query_parameters("SELECT versionName FROM appVersion WHERE versionId = '?'",
                                   $versionId);
        if(!$result || query_num_rows($result) != 1)
            return null;
        $ob = query_fetch_object($result);
        return $ob->versionName;
    }

    public static function fullName($iVersionId)
    {
        if(!$iVersionId)
            return FALSE;

        $hResult = query_parameters(
            "SELECT appFamily.appName, appVersion.versionName
                FROM appVersion, appFamily WHERE appVersion.appId = appFamily.appId
                AND versionId = '?'",
                    $iVersionId);

        if(!$hResult || !query_num_rows($hResult))
            return FALSE;

        $oRow = query_fetch_object($hResult);
        return "$oRow->appName $oRow->versionName";
    }

    /* Creates a link to the version labelled with the full application name */
    public static function fullNameLink($iVersionId)
    {
        $oVersion = new version($iVersionId);
        $sLink = "<a href=\"".$oVersion->objectMakeUrl()."\">".
                $oVersion->fullName($iVersionId)."</a>";
        return $sLink;
    }

    // display the versions
    public static function displayList($aVersions)
    {
        if ($aVersions)
        {
            echo html_frame_start("","98%","",0);

            $oTable = new Table();
            $oTable->SetClass('whq-table');
            $oTableRow = new TableRow();

            $oTableCell = new TableCell("Version");
            $oTableCell->SetWidth("80");
            $oTableRow->AddCell($oTableCell);

            $oTableRow->AddTextCell("Description");

            $oTableCell = new TableCell("Rating");
            $oTableCell->SetWidth("80");
            $oTableRow->AddCell($oTableCell);

            $oTableCell = new TableCell("Wine version");
            $oTableCell->SetWidth("80");
            $oTableRow->AddCell($oTableCell);

            $oTableCell = new TableCell("Test results");
            $oTableCell->SetWidth("80");
            $oTableRow->AddCell($oTableCell);

            $oTableCell = new TableCell("Comments");
            $oTableCell->SetWidth("40");
            $oTableRow->AddCell($oTableCell);

            $oTable->SetHeader($oTableRow);

            $c = 0;
            foreach($aVersions as $oVersion)
            {
                $oApp = new application($oVersion->iAppId);

                if ($oVersion->sState == $oApp->objectGetState())
                {
                    // if we have a valid tested rating
                    if($oVersion->sTestedRating && ($oVersion->sTestedRating != "/") &&
                       ($oVersion->sTestedRating != " "))
                    {
                        $sClass = $oVersion->sTestedRating;
                    }

                    //display row
                    $oTableRowClick = new TableRowClick($oVersion->objectMakeUrl());

                    $oTableRow = new TableRow();
                    $oTableRow->SetRowClick($oTableRowClick); // make the row clickable
                    $oTableRow->AddTextCell($oVersion->objectMakeLink());
                    $oTableRow->SetClass($sClass);
                    $oTableRow->AddTextCell(util_trim_description($oVersion->sDescription));

                    $oTableCell = new TableCell($oVersion->sTestedRating);
                    $oTableCell->SetAlign("center");
                    $oTableRow->AddCell($oTableCell);

                    $oTableCell = new TableCell($oVersion->sTestedRelease);
                    $oTableCell->SetAlign("center");
                    $oTableRow->AddCell($oTableCell);

                    $oTableCell = new TableCell(testData::get_testdata_count_for_versionid($oVersion->iVersionId));
                    $oTableCell->SetAlign("center");
                    $oTableRow->AddCell($oTableCell);

                    $oTableCell = new TableCell(Comment::get_comment_count_for_versionid($oVersion->iVersionId));
                    $oTableCell->SetAlign("center");
                    $oTableRow->AddCell($oTableCell);

                    // add the row to the table
                    $oTable->AddRow($oTableRow);

                    $c++;   
                }
            }

            // output the table
            echo $oTable->GetString();

            echo html_frame_end("Click the Version Name to view the details of that Version");
        }
    }

    /* returns the maintainers of this version in an array */
    public function getMaintainersUserIds()
    {
        $aMaintainers = array();

        /* early out if the versionId isn't valid */
        if($this->iVersionId == 0)
            return $aMaintainers;
    
        $hResult = Maintainer::getMaintainersForAppIdVersionId(null, $this->iVersionId);
        $iCount = 0;
        while($oRow = query_fetch_object($hResult))
        {
            $aMaintainers[$iCount] = $oRow->userId;
            $iCount++;
        }

        return $aMaintainers;
    }

    /* List the versions submitted by a user.  Ignore versions for queued applications */
    public static function listSubmittedBy($iUserId, $bQueued = true)
    {
        $hResult = query_parameters("SELECT appFamily.appName, appVersion.versionName, appVersion.description, appVersion.versionId, appVersion.submitTime FROM appFamily, appVersion WHERE appFamily.appId = appVersion.appId AND appVersion.submitterId = '?' AND appVersion.state = '?' AND appFamily.state = '?'", $iUserId, $bQueued ? 'queued' : 'accepted', 'accepted');

        if(!$hResult || !query_num_rows($hResult))
            return false;

        $oTable = new Table();
        $oTable->SetWidth("100%");
        $oTable->SetAlign("center");

        // setup the table header
        $oTableRow = new TableRow();
        $oTableRow->AddTextCell("Name");
        $oTableRow->AddTextCell("Description");
        $oTableRow->AddTextCell("Submission Date");
        $oTableRow->SetClass("color4");
        $oTable->SetHeader($oTableRow);

        if($bQueued)
            $oTableRow->addTextCell('Action');

        for($i = 1; $oRow = query_fetch_object($hResult); $i++)
        {
            $oTableRow = new TableRow();
            $oTableRow->AddTextCell(version::fullNameLink($oRow->versionId));
            $oTableRow->AddTextCell($oRow->description);
            $oTableRow->AddTextCell(print_date(mysqldatetime_to_unixtimestamp($oRow->submitTime)));
            $oTableRow->SetClass(($i % 2) ? "color0" : "color1");

            if($bQueued)
            {
                $oM = new objectManager('version_queue');
                $oM->setReturnTo(array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : "");
                $shDeleteLink = '<a href="'.$oM->makeUrl('delete', $oRow->versionId, 'Delete entry').'">delete</a>';
                $shEditLink = '<a href="'.$oM->makeUrl('edit', $oRow->versionId, 'Edit entry').'">edit</a>';
                $oTableRow->addTextCell("[ $shEditLink ] &nbsp; [ $shDeleteLink ]");
            }

            $oTable->AddRow($oTableRow);
        }

        return $oTable->GetString();
    }

    /* Returns an array containg the different software licences */
    public static function getLicenses()
    {
        return array(LICENSE_RETAIL, LICENSE_OPENSOURCE, LICENSE_FREETOUSE,
                     LICENSE_FREETOSHARE, LICENSE_DEMO, LICENSE_SHAREWARE);
    }

    // returns a string containing the html for a selection list
    public function makeLicenseList($sLicense = NULL)
    {
        if(!$sLicense)
            $sLicense = $this->sLicense;

        $sReturn = "<select name=\"sLicense\">\n";
        $sReturn .= "<option value=\"\">Choose . . .</option>\n";
        $aLicense = version::getLicenses();
        $iMax = count($aLicense);

        for($i = 0; $i < $iMax; $i++)
        {
            if($aLicense[$i] == $sLicense)
                $sSelected = " selected=\"selected\"";
            else
                $sSelected = "";

            $sReturn .= "<option value=\"$aLicense[$i]\"$sSelected>".
              "$aLicense[$i]</option>\n";
        }

        $sReturn .= "</select>\n";

        return $sReturn;
    }

    /* In order to prevent MySQL injections.  Returns matched license */
    public static function checkLicense($sLicense)
    {
        $aLicense = array(LICENSE_RETAIL, LICENSE_OPENSOURCE, LICENSE_FREETOUSE,
                          LICENSE_FREETOSHARE, LICENSE_DEMO, LICENSE_SHAREWARE);

        foreach($aLicense as $sElement)
        {
            if($sLicense == $sElement)
                return $sElement;
        }

        return FALSE;
    }

    public function objectGetClassDisplayName()
    {
        return 'version';
    }

    public function objectMakeUrl()
    {
      return APPDB_ROOT."objectManager.php?sClass=version&iId=$this->iVersionId";
    }

    public function objectMakeLink()
    {
        $sLink = "<a href=\"".htmlentities($this->objectMakeUrl())."\">".
                 $this->sName."</a>";
        return $sLink;
    }

    public static function objectGetEntriesCount($sState, $oFilters = null)
    {
        $sExtraTables = '';
        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyWithoutMaintainers' => 'false', 'onlyMyMaintainedEntries' => 'false');
        $sWhereFilter = '';
        $bOnlyMyMaintainedEntries = false;

        $oVersion = new version();

        if(getInput('onlyMyMaintainedEntries', $aOptions) == 'true'
           || ($sState != 'accepted' && !$oVersion->canEdit()))
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
            $sExtraTables = ',appFamily';

            $sWhereFilter .= " AND appFamily.hasMaintainer = 'false' AND appFamily.appId = appVersion.appId";
        }

        if($bOnlyMyMaintainedEntries)
        {
            if(!$oVersion->canEdit() && $sState == 'rejected')
                $sQuery = "SELECT COUNT(DISTINCT appVersion.versionId) as count FROM
                        appVersion$sExtraTables WHERE
                        appVersion.submitterId = '?'
                        AND
                        appVersion.state = '?'$sWhereFilter";
            else
                $sQuery = "SELECT COUNT(DISTINCT appVersion.versionId) as count FROM
                        appVersion, appMaintainers$sExtraTables WHERE
                        appMaintainers.appId = appVersion.appId
                        AND
                        superMaintainer = '1'
                        AND
                        appMaintainers.userId = '?'
                        AND
                        appMaintainers.state = 'accepted'
                        AND
                        appVersion.state = '?'$sWhereFilter";

            $hResult = query_parameters($sQuery, $_SESSION['current']->iUserId, $sState);
        } else
        {
            $sQuery = "SELECT COUNT(DISTINCT versionId) as count
                    FROM appVersion$sExtraTables WHERE
                    appVersion.state = '?'$sWhereFilter";
            $hResult = query_parameters($sQuery, $sState);
        }

        if(!$hResult)
            return FALSE;

        if(!$oRow = query_fetch_object($hResult))
            return FALSE;

        return $oRow->count;
    }

    public function objectGetState()
    {
        return $this->sState;
    }

    public function objectSetState($sState)
    {
        $this->sState = $sState;
    }

    public function hasMaintainer()
    {
        return $this->bHasMaintainer;
    }

    public function updateMaintainerState()
    {
        $this->bHasMaintainer = maintainer::versionHasMaintainer($this->iVersionId);

        $hResult = query_parameters("UPDATE appVersion SET hasMaintainer = '?' WHERE versionId = '?'", $this->bHasMaintainer ? 'true' : 'false', $this->iVersionId);
    }

    public function canEdit()
    {
        if($_SESSION['current']->hasPriv("admin"))
            return TRUE;

        if(isset($this) && is_object($this) && $this->iVersionId)
        {
            if(maintainer::isUserMaintainer($_SESSION['current'], $this->iVersionId))
                return TRUE;

            if($this->sState != 'accepted' && $this->iSubmitterId == $_SESSION['current']->iUserId)
                return TRUE;

            return FALSE;
        } else
        {
            return FALSE;
        }
    }

    public function mustBeQueued()
    {
        if($_SESSION['current']->hasPriv("admin"))
            return FALSE;

        // if we have a valid iAppId or iVersionId we should
        // check the status of these objects to determine whether
        // we need to queue this version object
        if($this->iVersionId or $this->iAppId)
        {
            // if the user is the super maintainer of the application then
            // they are authorized to unqueue versions of this application
            // so the version doesn't have to be queued
            if($this->iAppId && 
               maintainer::isUserSuperMaintainer($_SESSION['current'], $this->iAppId))
                return FALSE;

            // if the user is a maintainer of this version then
            // this version doesn't have to be queued
            if($this->iVersionId && 
               maintainer::isUserMaintainer($_SESSION['current'], $this->iVersionId))
                return FALSE;

            return TRUE;
        } else
        {
            return TRUE;
        }
    }

    public static function objectGetHeader()
    {
        $oTableRow = new TableRow();
        $oTableRow->AddTextCell("Submission Date");
        $oTableRow->AddTextCell("Submitter");
        $oTableRow->AddTextCell("Developer");
        $oTableRow->AddTextCell("Application");
        $oTableRow->AddTextCell("Version");
        $oTableRow->AddTextCell("Has Maintainer");
        return $oTableRow;
    }

    public static function objectGetItemsPerPage($sState = 'accepted')
    {
        $aItemsPerPage = array(25, 50, 100, 200);
        $iDefaultPerPage = 25;
        return array($aItemsPerPage, $iDefaultPerPage);
    }

    public static function objectGetDefaultSort()
    {
        return 'versionId';
    }

    public static function objectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = "versionId", $bAscending = true, $oFilters = null)
    {
        $sExtraTables = '';
        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyWithoutMaintainers' => 'false', 'onlyMyMaintainedEntries' => 'false');
        $sWhereFilter = '';
        $bOnlyMyMaintainedEntries = false;

        $oVersion = new version();

        if(getInput('onlyMyMaintainedEntries', $aOptions) == 'true'
           || ($sState != 'accepted' && !$oVersion->canEdit()))
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
            $sExtraTables = ',appFamily';

            $sWhereFilter .= " AND appFamily.hasMaintainer = 'false' AND appFamily.appId = appVersion.appId";
        }

        $sLimit = "";

        /* Should we add a limit clause to the query? */
        if($iRows || $iStart)
        {
            $sLimit = " LIMIT ?,?";

            /* Selecting 0 rows makes no sense, so we assume the user wants to select all of them
               after an offset given by iStart */
            if(!$iRows)
                $iRows = version::objectGetEntriesCount($sState);
        }

        if($bOnlyMyMaintainedEntries)
        {
            if(!$oVersion->canEdit() && $sState == 'rejected')
                $sQuery = "SELECT * FROM appVersion$sExtraTables WHERE
                        appVersion.submitterId = '?'
                        AND
                        appVersion.state = '?'$sWhereFilter ORDER BY ?$sLimit";
            else
                $sQuery = "SELECT appVersion.* FROM
                        appVersion, appMaintainers$sExtraTables WHERE
                        appMaintainers.appId = appVersion.appId
                        and
                        superMaintainer = '1'
                        AND
                        appMaintainers.userId = '?'
                        AND
                        appMaintainers.state = 'accepted'
                        AND
                        appVersion.state = '?'$sWhereFilter ORDER BY ?$sLimit";

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
            $sQuery = "SELECT appVersion.* FROM appVersion$sExtraTables WHERE
                    appVersion.state = '?'$sWhereFilter ORDER BY ?$sLimit";

            if($sLimit)
            {
                $hResult = query_parameters($sQuery, $sState, $sOrderBy,
                                            $iStart, $iRows);
            } else
            {
                $hResult = query_parameters($sQuery, $sState, $sOrderBy);
            }
        }

        if(!$hResult)
            return FALSE;

        return $hResult;
    }

    public function objectGetTableRow()
    {
        $oUser = new user($this->iSubmitterId);
        $oApp = new application($this->iAppId);
        $oVendor = new vendor($oApp->iVendorId);

        $oTableRow = new TableRow();
        $oTableRow->AddTextCell(print_date(mysqldatetime_to_unixtimestamp($this->sSubmitTime)));
        $oTableRow->AddTextCell($oUser->objectMakeLink());
        $oTableRow->AddTextCell($oVendor->objectMakeLink());
        $oTableRow->AddTextCell($oApp->objectMakeLink());
        $oTableRow->AddTextCell($this->sName);
        $oTableRow->AddTextCell($oApp->bHasMaintainer ? "YES" : "No");

        $oOMTableRow = new OMTableRow($oTableRow);
        return $oOMTableRow;
    }

    public function objectDisplayQueueProcessingHelp()
    {
        echo "<p>This is the list of version entries waiting to be processed.</p>\n";
        echo "<p>To view and process an entry, use the links under &#8216;Action&#8217;</p>";
    }

    public function getTestResults($bIncludeDeleted = false)
    {
        /* If we don't have an id we can't query the database, but perhaps we
           have some cached entries? */
        if(!$this->iVersionId)
            return $this->aTestResults;

        $aTests = array();

        if($bIncludeDeleted)
            $sExcludeDeleted = "";
        else
            $sExcludeDeleted = " AND state != 'deleted'";

        /* Find test results */
        $sQuery = "SELECT * FROM testResults WHERE versionId = '?'$sExcludeDeleted";
        $hResult = query_parameters($sQuery, $this->iVersionId);

        if(!$hResult)
            return FALSE;

        while($oRow = query_fetch_object($hResult))
            $aTests[] = new testData(0, $oRow);

        return $aTests;
    }

    public function objectGetChildren($bIncludeDeleted = false, $bRecursive = true)
    {
        return $this->objectGetChildrenClassSpecific('', $bIncludeDeleted, $bRecursive);
    }

    public function objectGetChildrenClassSpecific($sClass = '', $bIncludeDeleted = false, $bRecursive = true)
    {
        $aChildren = array();

        foreach($this->getTestResults($bIncludeDeleted) as $oTest)
        {
            if($bRecursive)
                $aChildren = array_merge($aChildren, $oTest->objectGetChildren($bIncludeDeleted));
            $aChildren[] = $oTest;
        }

        /* Find maintainers */
        $sQuery = "SELECT * FROM appMaintainers WHERE versionId = '?'";
        $hResult = query_parameters($sQuery, $this->iVersionId);

        if(!$hResult)
            return FALSE;

        while($oRow = query_fetch_object($hResult))
        {
            $oMaintainer = new maintainer(0, $oRow);

            if($bRecursive)
                $aChildren = array_merge($aChildren, $oMaintainer->objectGetChildren($bIncludeDeleted));
            $aChildren[] = $oMaintainer;
        }

        /* Find monitors */
        $sQuery = "SELECT * FROM appMonitors WHERE versionId = '?'";
        $hResult = query_parameters($sQuery, $this->iVersionId);

        if(!$hResult)
            return FALSE;

        while($oRow = query_fetch_object($hResult))
        {
            $oMonitor = new monitor(0, $oRow);

            if($bRecursive)
                $aChildren = array_merge($aChildren, $oMonitor->objectGetChildren($bIncludeDeleted));
            $aChildren[] = $oMonitor;
        }

        /* Find notes */
        $sQuery = "SELECT * FROM appNotes WHERE versionId = '?' AND linkedWith = '0'";
        $hResult = query_parameters($sQuery, $this->iVersionId);

        if(!$hResult)
            return FALSE;

        while($oRow = query_fetch_object($hResult))
        {
            $oNote = new note(0, $oRow);

            // We always include notes' children, because they log the versionId
            $aChildren = array_merge($aChildren, $oNote->objectGetChildren($bIncludeDeleted));
            $aChildren[] = $oNote;
        }

        /* Find screenshots */
        $sQuery = "SELECT * FROM appData WHERE type = '?' AND versionId = '?'";
        $hResult = query_parameters($sQuery, "screenshot", $this->iVersionId);

        if(!$hResult)
            return FALSE;

        while($oRow = query_fetch_object($hResult))
        {
            $oScreenshot = new screenshot(0, $oRow);

            if($bRecursive)
                $aChildren = array_merge($aChildren, $oScreenshot->objectGetChildren($bIncludeDeleted));
            $aChildren[] = $oScreenshot;
        }

        /* Get bug links */
        foreach($this->get_buglink_ids() as $iBugId)
        {
            $oBug = new bug($iBugId);

            if($bRecursive)
                $aChildren = array_merge($aChildren, $oBug->objectGetChildren($bIncludeDeleted));
            $aChildren[] = $oBug;
        }

        /* Get comments */
        $sQuery = "SELECT * FROM appComments WHERE versionId = '?'";
        $hResult = query_parameters($sQuery, $this->iVersionId);

        if(!$hResult)
            return FALSE;

        while($oRow = query_fetch_object($hResult))
        {
            $oComment = new comment(0, $oRow);

            /* No need to grab child comments since they're all part of the SQL result */
            $aChildren[] = $oComment;
        }

        /* Get urls */
        $sQuery = "SELECT * FROM appData WHERE type = '?' AND versionId = '?'";
        $hResult = query_parameters($sQuery, "url", $this->iVersionId);

        if(!$hResult)
            return FALSE;

        while($oRow = query_fetch_object($hResult))
        {
            $oUrl = new url(0, $oRow);

            if($bRecursive)
                $aChildren = array_merge($aChildren, $oUrl->objectGetChildren($bIncludeDeleted));
            $aChildren[] = $oUrl;
        }

        /* Get downloadurls */
        $sQuery = "SELECT * FROM appData WHERE type = '?' AND versionId = '?'";
        $hResult = query_parameters($sQuery, "downloadurl", $this->iVersionId);

        if(!$hResult)
            return FALSE;

        while($oRow = query_fetch_object($hResult))
        {
            $oDownload = new downloadurl(0, $oRow);

            if($bRecursive)
                $aChildren = array_merge($aChildren, $oDownload->objectGetChildren($bIncludeDeleted));
            $aChildren[] = $oDownload;
        }

        /* Get votes, include deleted ones */
        $aChildren = array_merge($aChildren, vote::getVotesForVersion($this->iVersionId));

        return $aChildren;
    }

    public function objectMoveChildren($iNewId)
    {
        /* Keep track of how many items we have updated */
        $iCount = 0;

        /* We also move entries marked as deleted */
        $aChildren = $this->objectGetChildren(true, false);

        foreach($aChildren as $oChild)
        {
            $oChild->objectSetParent($iNewId, 'version');
            if($oChild->update())
                $iCount++;
            else
                return false;
        }

        /* Return the number of updated objects if everything was successful */
        return $iCount;
    }

    public static function allowAnonymousSubmissions()
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

    public function objectGetId()
    {
        return $this->iVersionId;
    }
}

?>
