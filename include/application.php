<?php
/***********************************************************/
/* this class represents an application incl. all versions */
/***********************************************************/

require_once(BASE."include/version.php");
require_once(BASE."include/vendor.php");
require_once(BASE."include/category.php");
require_once(BASE."include/url.php");
require_once(BASE."include/util.php");
require_once(BASE."include/mail.php");
require_once(BASE."include/maintainer.php");
require_once(BASE."include/tableve.php");
require_once(BASE."include/db_filter_ui.php");

define("PLATINUM_RATING", "Platinum");
define("GOLD_RATING", "Gold");
define("SILVER_RATING", "Silver");
define("BRONZE_RATING", "Bronze");
define("GARBAGE_RATING", "Garbage");

define("MAINTAINER_REQUEST", 1);
define("SUPERMAINTAINER_REQUEST", 2);
define('MONITOR_REQUEST', 3);

/**
 * Application class for handling applications.
 */
class Application {

    var $iAppId;
    var $iVendorId;
    var $iCatId;
    var $sName;
    var $sKeywords;
    var $sDescription;
    var $sWebpage;
    private $sState;
    var $sSubmitTime;
    var $iSubmitterId;
    var $aVersionsIds;  // an array that contains the versionId of every version linked to this app.
    var $bHasMaintainer;
    var $aVersions; // Array of version objects belonging to this app
    var $iMaintainerRequest; /* Temporary variable for tracking maintainer
                                requests on app submission.  Value denotes type of request */

    /**    
     * constructor, fetches the data.
     */
    public function Application($iAppId = null, $oRow = null)
    {
        $this->aVersions = array(); // Should always be an array

        // we are working on an existing application
        if(!$iAppId && !$oRow)
            return;

        if(!$oRow)
        {
            /* fetch this applications information */
            $sQuery = "SELECT *
                    FROM appFamily 
                    WHERE appId = '?'";
            if($hResult = query_parameters($sQuery, $iAppId))
                $oRow = query_fetch_object($hResult);
        }

        if($oRow)
        {
            $this->iAppId = $oRow->appId;
            $this->iVendorId = $oRow->vendorId;
            $this->iCatId = $oRow->catId;
            $this->iSubmitterId = $oRow->submitterId;
            $this->sSubmitTime = $oRow->submitTime;
            $this->sName = $oRow->appName;
            $this->sKeywords = $oRow->keywords;
            $this->sDescription = $oRow->description;
            //TODO: we should move the url to the appData table
            // and return an id into the appData table here
            $this->sWebpage = Url::normalize($oRow->webPage);
            $this->sState = $oRow->state;
            $this->bHasMaintainer = $oRow->hasMaintainer == 'true' ? true : false;
        }

        /* fetch versions of this application, if there are any */
        $this->aVersionsIds = array();

        /* only admins can view all versions */
        //FIXME: it would be nice to move this permission into the user class as well as keep it generic
        if($_SESSION['current']->hasPriv("admin"))
        {
            $hResult = $this->_internal_retrieve_all_versions();
        } else
        {
            $hResult = $this->_internal_retrieve_unqueued_versions();
        }
        if($hResult)
        {
            while($oRow = query_fetch_object($hResult))
            {
                $this->aVersionsIds[] = $oRow->versionId;
            }
        }
    }

    private function _internal_retrieve_all_versions($bOnlyAccepted = false, $bIncludeObsolete = TRUE, $bIncludeDeleted = false)
    {
        $sExtraTerms = '';

        if(!$bIncludeObsolete)
            $sExtraTerms .= " AND obsoleteBy = '0'";

        if($bOnlyAccepted)
            $sExtraTerms .= " AND state = 'accepted'";
        else if(!$bIncludeDeleted)
            $sExtraTerms .= " AND state != 'deleted'";

        $sQuery = "SELECT versionId FROM appVersion WHERE
                appId = '?'$sExtraTerms ORDER by versionName";
        $hResult  = query_parameters($sQuery, $this->iAppId);
        return $hResult;
    }

    private function _internal_retrieve_unqueued_versions()
    {
        $sQuery = "SELECT versionId FROM appVersion WHERE
                        state = 'accepted' AND
                        appId = '?'";
        $hResult  = query_parameters($sQuery, $this->iAppId);
        return $hResult;
    }

    /**
     * Creates a new application.
     */
    public function create()
    {
        if(!$_SESSION['current']->canCreateApplication())
            return false;

        $hResult = query_parameters("INSERT INTO appFamily (appName, description, ".
                                    "keywords, webPage, vendorId, catId, ".
                                    "submitTime, submitterId, ".
                                    "state) VALUES (".
                                    "'?', '?', '?', '?', '?', '?', ?, '?', '?')",
                                    $this->sName, $this->sDescription, $this->sKeywords,
                                    $this->sWebpage, $this->iVendorId, $this->iCatId,
                                    "NOW()", $_SESSION['current']->iUserId,
                                    $this->mustBeQueued() ? 'queued' : 'accepted');
        if($hResult)
        {
            $this->iAppId = query_appdb_insert_id();
            $this->application($this->iAppId);
            $this->SendNotificationMail();  // Only administrators will be mailed as no supermaintainers exist for this app.

            /* Submit super maintainer request if asked to */
            if($this->iMaintainerRequest == SUPERMAINTAINER_REQUEST)
            {
                $oMaintainer = new Maintainer();
                $oMaintainer->iAppId = $this->iAppId;
                $oMaintainer->iUserId = $_SESSION['current']->iUserId;
                $oMaintainer->sMaintainReason = "This user submitted the application; auto-queued.";
                $oMaintainer->bSuperMaintainer = 1;
                $oMaintainer->create();
            }

            return true;
        } else
        {
            addmsg("Error while creating a new application.", "red");
            return false;
        }
    }


    /**
     * Update application.
     * Returns true on success and false on failure.
     */
    public function update($bSilent=false)
    {
        $sWhatChanged = "";

        /* if the user doesn't have permission to modify this application, don't let them */
        if(!$_SESSION['current']->canModifyApplication($this))
            return;

        /* create an instance of ourselves so we can see what has changed */
        $oApp = new Application($this->iAppId);

        if ($this->sName && ($this->sName!=$oApp->sName))
        {
            if (!query_parameters("UPDATE appFamily SET appName = '?' WHERE appId = '?'",
                                  $this->sName, $this->iAppId))
                return false;
            $sWhatChanged .= "Name was changed from ".$oApp->sName." to ".$this->sName.".\n\n";
        }     

        if ($this->sDescription && ($this->sDescription!=$oApp->sDescription))
        {
            if (!query_parameters("UPDATE appFamily SET description = '?' WHERE appId = '?'",
                                  $this->sDescription, $this->iAppId))
                return false;
            $sWhatChanged .= "Description was changed from\n ".$oApp->sDescription."\n to \n".$this->sDescription.".\n\n";
        }

        if ($this->sKeywords && ($this->sKeywords!=$oApp->sKeywords))
        {
            if (!query_parameters("UPDATE appFamily SET keywords = '?' WHERE appId = '?'",
                                  $this->sKeywords, $this->iAppId))
                return false;
            $sWhatChanged .= "Keywords were changed from\n ".$oApp->sKeywords."\n to \n".$this->sKeywords.".\n\n";
        }

        if ($this->sWebpage!=$oApp->sWebpage)
        {
            if (!query_parameters("UPDATE appFamily SET webPage = '?' WHERE appId = '?'",
                                  $this->sWebpage, $this->iAppId))
                return false;
            $sWhatChanged .= "Web page was changed from ".$oApp->sWebpage." to ".$this->sWebpage.".\n\n";
        }
     
        if ($this->iVendorId && ($this->iVendorId!=$oApp->iVendorId))
        {
            if (!query_parameters("UPDATE appFamily SET vendorId = '?' WHERE appId = '?'",
                                  $this->iVendorId, $this->iAppId))
                return false;
            $oVendorBefore = new Vendor($oApp->iVendorId);
            $oVendorAfter = new Vendor($this->iVendorId);
            $sWhatChanged .= "Developer was changed from ".$oVendorBefore->sName." to ".$oVendorAfter->sName.".\n\n";
        }

        if ($this->iCatId && ($this->iCatId!=$oApp->iCatId))
        {
            if (!query_parameters("UPDATE appFamily SET catId = '?' WHERE appId = '?'",
                                  $this->iCatId, $this->iAppId))
                return false;
            $oCatBefore = new Category($oApp->iCatId);
            $oCatAfter = new Category($this->iCatId);
            $sWhatChanged .= "Category was changed from ".$oCatBefore->sName." to ".$oCatAfter->sName.".\n\n";
        }
        if($sWhatChanged and !$bSilent)
            $this->SendNotificationMail("edit",$sWhatChanged);
        return true;
    }

    public function hasMaintainer()
    {
        return $this->bHasMaintainer;
    }

    public function updateMaintainerState()
    {
        $this->bHasMaintainer = maintainer::appHasMaintainer($this->iAppId);

        $hResult = query_parameters("UPDATE appFamily SET hasMaintainer = '?' WHERE appId = '?'", $this->bHasMaintainer ? 'true' : 'false', $this->iAppId);
    }

    /**
    * Deletes the application from the database. 
    * and request the deletion of linked elements.
    */
    public function purge()
    {
        $bSuccess = true;

        /* make sure the current user has the appropriate permission to delete
                this application */
                if(!$_SESSION['current']->canDeleteApplication($this))
                return false;

        foreach($this->objectGetChildren(true) as $oChild)
        {
            if(!$oChild->purge())
                $bSuccess = FALSE;
        }

        /* Flag the entry as deleted */
                $sQuery = "DELETE FROM appFamily
                WHERE appId = '?' 
                LIMIT 1";
        if(!($hResult = query_parameters($sQuery, $this->iAppId)))
            $bSuccess = false;

        return $bSuccess;
    }

    /**
     * Falgs the application as deleted
     * and request the deletion of linked elements.
     */
    public function delete()
    {
        $bSuccess = true;

        /* make sure the current user has the appropriate permission to delete
           this application */
        if(!$_SESSION['current']->canDeleteApplication($this))
            return false;

        foreach($this->objectGetChildren() as $oChild)
        {
            if(!$oChild->delete())
                $bSuccess = FALSE;
        }

        /* Flag the entry as deleted */
        $sQuery = "UPDATE appFamily SET state = 'deleted'
                   WHERE appId = '?' 
                   LIMIT 1";
        if(!($hResult = query_parameters($sQuery, $this->iAppId)))
            $bSuccess = false;

        return $bSuccess;
    }


    /**
     * Move application out of the queue.
     */
    public function unQueue()
    {
        if(!$_SESSION['current']->canUnQueueApplication())
            return;

        if(query_parameters("UPDATE appFamily SET state = '?', keywords = '?' WHERE appId = '?'",
                            'accepted',  str_replace(" *** ","",$this->sKeywords), $this->iAppId))
        {
            $this->sState = 'accepted';
            // we send an e-mail to interested people
            $this->mailSubmitter();
            $this->SendNotificationMail();

            /* Unqueue matching super maintainer request */
            $hResultMaint = query_parameters("SELECT maintainerId FROM appMaintainers WHERE userId = '?' AND appId = '?'", $this->iSubmitterId, $this->iAppId);
            if($hResultMaint && query_num_rows($hResultMaint))
            {
                $oMaintainerRow = query_fetch_object($hResultMaint);
                $oMaintainer = new Maintainer($oMaintainerRow->maintainerId);
                $oMaintainer->unQueue("OK");
            }
        }
    }

    public function Reject()
    {
        if(!$_SESSION['current']->canRejectApplication($this))
            return;

        // If we are not in the queue, we can't move the application out of the queue.
        if($this->sState != 'queued')
            return false;

        if(query_parameters("UPDATE appFamily SET state = '?' WHERE appId = '?'",
                            'rejected', $this->iAppId))
        {
            $this->sState = 'rejected';
            // we send an e-mail to interested people
            $this->mailSubmitter("reject");
            $this->SendNotificationMail("reject");

            // the application has been rejected
            addmsg("The application has been rejected.", "green");
        }
    }

    public static function objectGetItemsPerPage($sState = 'accepted')
    {
        $aItemsPerPage = array(25, 50, 100, 200);
        $iDefaultPerPage = 25;
        return array($aItemsPerPage, $iDefaultPerPage);
    }

    public function ReQueue()
    {
        if(!$_SESSION['current']->canRequeueApplication($this))
            return false;

        if(query_parameters("UPDATE appFamily SET state = '?' WHERE appId = '?'",
                            'queued', $this->iAppId))
        {
            $this->sState = 'queued';
            // we send an e-mail to interested people
            $this->SendNotificationMail();

            // the application has been re-queued
            addmsg("The application has been re-queued.", "green");
        }
    }

    public function objectGetSubmitterId()
    {
        return $this->iSubmitterId;
    }

    public function objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction)
    {
        return new mailOptions();
    }

    public function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        if($bMailSubmitter)
        {
            switch($sAction)
            {
                case "delete":
                    $sSubject = "Submitted application deleted";
                    $sMsg  = "The application you submitted (".$this->sName.
                             ") has been deleted.";
                break;
            }
            $aMailTo = null;
        } else
        {
            switch($sAction)
            {
                case "delete":
                    $sSubject = $this->sName." deleted";
                    $sMsg = "The application '".$this->sName."' has been deleted.";
                break;
            }
            $aMailTo = User::get_notify_email_address_list($this->iAppId);
        }
        return array($sSubject, $sMsg, $aMailTo);
    }

    private function mailSubmitter($sAction="add")
    {
        global $aClean;
        if(!isset($aClean['sReplyText']))
            $aClean['sReplyText'] = "";

        if($this->iSubmitterId)
        {
            $oSubmitter = new User($this->iSubmitterId);
            switch($sAction)
            {
            case "add":
                $sSubject =  "Submitted application accepted";
                $sMsg  = "The application you submitted (".$this->sName.") has been accepted by ".$_SESSION['current']->sRealname.".\n";
                $sMsg .= "Administrator's Response:\n";
            break;
            case "reject":
                $sSubject =  "Submitted application rejected";
                $sMsg  = "The application you submitted (".$this->sName.") has been rejected by ".$_SESSION['current']->sRealname.".";
                $sMsg .= "Clicking on the link in this email will allow you to modify and resubmit the application. ";
                $sMsg .= "A link to your queue of applications and versions will also show up on the left hand side of the Appdb site once you have logged in. ";
                $sMsg .= APPDB_ROOT."objectManager.php?sClass=application_queue".
                        "&amp;bIsQueue=true&amp;bIsRejected=true&amp;iId=".$this->iAppId."&amp;sTitle=".
                        "Edit+Application\n";
                $sMsg .= "Reason given:\n";
            break;
            }

            $sMsg .= $aClean['sReplyText']."\n";
            $sMsg .= "We appreciate your help in making the Application Database better for all users.";

            mail_appdb($oSubmitter->sEmail, $sSubject ,$sMsg);
        }
    }


    public static function countWithRating($sRating)
    {
        $sQuery = "SELECT DISTINCT count(appId) as total 
                       FROM appVersion
                       WHERE rating = '?'
                       AND state = 'accepted'";

        if($hResult = query_parameters($sQuery, $sRating))
        {
            $oRow = query_fetch_object($hResult);
        }
	     return $oRow->total;
    }

    public static function getWithRating($sRating, $iOffset, $iItemsPerPage)
    {
        $aApps = array();
        $sQuery = "SELECT DISTINCT appVersion.appId, appName 
                       FROM appVersion, appFamily WHERE
                           appVersion.appId = appFamily.appId
                       AND
                           rating = '?'
                       AND
                           appVersion.state = 'accepted'
                       ORDER BY appName ASC LIMIT ?, ?";
        
        if($hResult = query_parameters($sQuery, $sRating, $iOffset, $iItemsPerPage))
        {
            while($aRow = query_fetch_row($hResult))
            {
                array_push($aApps, $aRow[0]);
            }
        }
        return $aApps;
    }

    private function SendNotificationMail($sAction="add",$sMsg=null)
    {
        global $aClean;
        if(!isset($aClean['sReplyText']))
            $aClean['sReplyText'] = "";

        switch($sAction)
        {
            case "add":
                if($this->sState == 'accepted') // Has been accepted.
                {
                    $sSubject = $this->sName." has been added by ".$_SESSION['current']->sRealname;
                    $sMsg  = $this->objectMakeUrl()."\n";
                    if($this->iSubmitterId)
                    {
                        $oSubmitter = new User($this->iSubmitterId);
                        $sMsg .= "This application has been submitted by ".$oSubmitter->sRealname.".";
                        $sMsg .= "\n";
                    }
                    if($aClean['sReplyText'])
                    {
                        $sMsg .= "Appdb admin reply text:\n";
                        $sMsg .= $aClean['sReplyText']."\n"; // append the reply text, if there is any 
                    }

                    addmsg("The application was successfully added into the database.", "green");
                } else
                {
                    $sSubject = $this->sName." has been submitted by ".$_SESSION['current']->sRealname;
                    $sMsg .= "This application has been queued.";
                    $sMsg .= "\n";
                    addmsg("The application you submitted will be added to the database after being reviewed.", "green");
                }
            break;
            case "edit":
                $sSubject =  $this->sName." has been modified by ".$_SESSION['current']->sRealname;
                $sMsg  .= $this->objectMakeUrl()."\n";
                addmsg("Application modified.", "green");
            break;
            case "reject":
                $sSubject = $this->sName." has been rejected by ".$_SESSION['current']->sRealname;
                $sMsg .= APPDB_ROOT."objectManager.php?sClass=application_queue".
                        "&amp;bIsQueue=true&amp;bIsRejected=true&amp;iId=".$this->iAppId."&amp;sTitle=".
                        "Edit+Application\n";

                // if sReplyText is set we should report the reason the application was rejected 
                if($aClean['sReplyText'])
                {
                    $sMsg .= "Reason given:\n";
                    $sMsg .= $aClean['sReplyText']."\n"; // append the reply text, if there is any 
                }

                addmsg("Application rejected.", "green");
            break;
        }
        $sEmail = User::get_notify_email_address_list($this->iAppId);
        if($sEmail)
            mail_appdb($sEmail, $sSubject ,$sMsg);
    } 

    public function objectShowPreview()
    {
        return TRUE;
    }

    /* output a html table and this applications values to the fields for editing */
    public function outputEditor($sVendorName = "")
    {
        HtmlAreaLoaderScript(array("app_editor"));

        echo '<input type="hidden" name="iAppId" value="'.$this->iAppId.'">';

        /* Used to distinguish between the first step of entering an application
           name and the full editor displayed here */
        echo '<input type="hidden" name="bMainAppForm" value="true">'."\n";

        echo html_frame_start("Application Form", "90%", "", 0);
        echo "<table class='color0' width='100%' border=0 cellpadding=2 cellspacing=0>\n";
        echo '<tr valign=top><td class="color0"><b>Application name</b></td>',"\n";
        echo '<td><input size="20" type="text" name="sAppName" value="'.$this->sName.'"></td></tr>',"\n";

        // app Category
        echo '<tr valign=top><td class="color0"><b>Category</b></td><td>',"\n";

        $aCategories = category::getOrderedList(true);
        $aCatNames = array();
        $aCatIds = array();

        foreach($aCategories as $oCategory)
        {
            $aCatNames[] = $oCategory->sName;
            $aCatIds[] = $oCategory->objectGetId();
        }

        echo html_select("iAppCatId",$aCatIds,$this->iCatId, $aCatNames);

        echo '</td></tr>',"\n";

        $oVendor = new vendor($this->iVendorId);
        $sVendorHelp = "The developer of the application. ";
        if(!$this->iAppId || $oVendor->objectGetState() != 'accepted')
        {
            if(!$this->iAppId)
            {
                $sVendorHelp .= "If it is not on the list please add it ".
                                "using the form below.";
            } else
            {
                if($this->iSubmitterId != $_SESSION['current']->iUserId)
                {
                    $sVendorHelp .= "The user added a new one; review ".
                                    "it in the Developer form below or ". 
                                    "replace it with an existing one.";
                } else
                {
                    $sVendorHelp .= 'You added a new one; it can be edited '.
                                    'using the form below, or replaced with '.
                                    'one from this list.';
                }
            }
        }
        // vendor name
        echo '<tr valign=top><td class="color0"><b>Developer</b></td>',"\n";
        echo "<td>$sVendorHelp</td></tr>\n";

        // alt vendor
        $x = new TableVE("view");
        echo '<tr valign=top><td class="color0">&nbsp;</td><td>',"\n";
        echo $x->make_option_list("iAppVendorId",
                                  $this->iVendorId,"vendor","vendorId","vendorName",
                                  array('vendor.state', 'accepted'));
        echo '</td></tr>',"\n";

        // url
        echo '<tr valign=top><td class="color0"><b>URL</b></td>',"\n";
        echo '<td><input size="20" type=text name="sAppWebpage" value="'.$this->sWebpage.'"></td></tr>',"\n";

        echo '<tr valign=top><td class="color0"><b>Keywords</b></td>',"\n";
        echo '<td><input size="75%" type="text" name="sAppKeywords" value="'.$this->sKeywords.'"></td></tr>',"\n";

        echo '<tr valign=top><td class="color0"><b>Application description (In your own words)</b></td>',"\n";
        echo '<td><p><textarea cols="80" rows="20" id="app_editor" name="shAppDescription">';

        echo $this->sDescription.'</textarea></p></td></tr>',"\n";

        // Allow user to apply as super maintainer if this is a new app
        if(!$this->iAppId)
        {
            $sMaintainerOptions = 
                "<input type=\"radio\" name=\"iMaintainerRequest\" value=\"0\">".
                "I would not like to become a maintainer<br>\n".
                "<input type=\"radio\" name=\"iMaintainerRequest\" ". 
                "value=\"".MAINTAINER_REQUEST."\">".
                "I would like to be a maintainer of the new version only<br>\n".
                "<input type=\"radio\" name=\"iMaintainerRequest\" ". 
                "value=\"".SUPERMAINTAINER_REQUEST."\">".
                "I would like to be a maintainer of the entire application<br>\n";

            $sMaintainerOptionsSelected = str_replace(
                "value=\"$this->iMaintainerRequest\"",
                "value=\"$this->iMaintainerRequest\" checked=\"checked\"",
                $sMaintainerOptions);

            echo html_tr(array(
                array("<b>Maintainer options</b>", "class=\"color0\""),
                $sMaintainerOptionsSelected),
                "", "valign=\"top\"");
        }

        echo "</table>\n";

        echo html_frame_end();
    }

    public function CheckOutputEditorInput($aValues)
    {
        $errors = "";

        if (empty($aValues['iAppCatId']))
            $errors .= "<li>Please enter a category for your application.</li>\n";

        if (strlen($aValues['sAppName']) > 200 )
            $errors .= "<li>Your application name is too long.</li>\n";

        if (empty($aValues['sAppName']))
            $errors .= "<li>Please enter an application name.</li>\n";

        // No vendor entered, and nothing in the list is selected
        if (empty($aValues['sVendorName']) && !$aValues['iAppVendorId'])
            $errors .= "<li>Please enter a developer.</li>\n";

        if (empty($aValues['shAppDescription']))
            $errors .= "<li>Please enter a description of your application.</li>\n";

        return $errors;
    }

    /* retrieves values from $aValues that were output by outputEditor() */
    /* $aValues can be $_REQUEST or any array with the values from outputEditor() */
    public function GetOutputEditorValues($aValues)
    {
        if($aValues['iAppId'])
            $this->iAppId = $aValues['iAppId'];

        $this->sName = $aValues['sAppName'];
        $this->sDescription = $aValues['shAppDescription'];
        $this->iCatId = $aValues['iAppCatId'];
        $this->iVendorId = $aValues['iAppVendorId'];
        $this->sWebpage = $aValues['sAppWebpage'];
        $this->sKeywords = $aValues['sAppKeywords'];
        $this->iMaintainerRequest = $aValues['iMaintainerRequest'];
    }

    /**
     * Displays the SUB apps that belong to this application.
     */
    public function displayBundle()
    {
        $hResult = query_parameters("SELECT appFamily.appId, appName, description FROM appBundle, appFamily ".
                "WHERE appFamily.state = 'accepted' AND bundleId = '?' AND appBundle.appId = appFamily.appId",
                $this->iAppId);
        if(!$hResult || query_num_rows($hResult) == 0)
        {
            return; // do nothing
        }

        echo html_frame_start("","98%","",0);
        echo "<table width=\"100%\" border=\"0\" cellpadding=\"3\" cellspacing=\"1\">\n\n";

        echo "<tr class=\"color4\">\n";
        echo "    <td>Application Name</td>\n";
        echo "    <td>Description</td>\n";
        echo "</tr>\n\n";

        for($c = 0; $ob = query_fetch_object($hResult); $c++)
        {
            $oApp = new application($ob->appId);
        //set row color
                $bgcolor = ($c % 2 == 0) ? "color0" : "color1";

        //display row
                echo "<tr class=\"$bgcolor\">\n";
        echo "    <td>".$oApp->objectMakeLink()."</td>\n";
        echo "    <td>".util_trim_description($oApp->sDescription)."</td>\n";
        echo "</tr>\n\n";
        }

        echo "</table>\n\n";
        echo html_frame_end();
    }

    public function objectGetCustomTitle($sAction)
    {
        switch($sAction)
        {
            case 'delete':
                return 'Delete '.$this->sName;
            case "view":
                return $this->sName;

            default:
                return null;
        }
    }

    /* display this application */
    public function display()
    {
        /* is this user supposed to view this version? */
        if(!$_SESSION['current']->canViewApplication($this))
        {
            $sError = 'You do not have permission to view this entry';

            objectManager::error_exit($sError);
        }

        // cat display
        $oCategory = new Category($this->iCatId);
        $oCategory->displayPath($this->iAppId);

        // set developer
        $oVendor = new Vendor($this->iVendorId);

        // set URL
        $appLinkURL = ($this->sWebpage) ? trimmed_link($this->sWebpage,30) : "&nbsp;";
  
        // start display application
        echo html_frame_start("","98%","",0);
        echo "<tr><td class=color4 valign=top>\n";
        echo "  <table>\n";
        echo "    <tr><td>\n";

        echo '      <table width="250" border="0" cellpadding="3" cellspacing="1">',"\n";
        echo "        <tr class=color0 valign=top><td width=\"100\"><b>Name</b></td><td width='100%'> ".$this->sName." </td>\n";
        echo "        <tr class=\"color1\"><td><b>Developer</b></td><td> ".
            $oVendor->objectMakeLink()."&nbsp;\n";
        echo "        </td></tr>\n";
    
        // main URL
        echo "        <tr class=\"color0\"><td><b>URL</b></td><td>".$appLinkURL."</td></tr>\n";

        // optional links
        if($sUrls = url::display(NULL, $this->iAppId))
            echo $sUrls;

        // image
        $img = Screenshot::get_random_screenshot_img($this->iAppId, null, false);
        echo "<tr><td align=\"center\" colspan=\"2\">$img</td></tr>\n";

        echo "      </table>\n"; /* close of name/developer/bugs/url table */

        echo "    </td></tr>\n";
        echo "    <tr><td>\n";

        // Display all supermaintainers maintainers of this application
        echo "      <table class=\"color4\" width=\"250\" border=\"1\">\n";
        echo "        <tr><td align=\"left\"><b>Super maintainers:</b></td></tr>\n";
        $other_maintainers = Maintainer::getSuperMaintainersUserIdsFromAppId($this->iAppId);
        if($other_maintainers)
        {
            echo "        <tr><td align=\"left\"><ul>\n";
            while(list($index, $userIdValue) = each($other_maintainers))
            {
                $oUser = new User($userIdValue);
                echo "        <li>".$oUser->objectMakeLink()."</li>\n";
            }
            echo "</ul></td></tr>\n";
        } else
        {
            echo "        <tr><td align=right>No maintainers.Volunteer today!</td></tr>\n";
        }

        // Display the app maintainer button
        echo '        <tr><td align="center">';
        if($_SESSION['current']->isLoggedIn())
        {
            /* are we already a maintainer? */
            if($_SESSION['current']->isSuperMaintainer($this->iAppId)) /* yep */
            {
                echo '        <form method="post" name="sMessage" action="maintainerdelete.php"><input type=submit value="Remove yourself as a super maintainer" class="button">';
            } else /* nope */
            {
                echo '        <form method="post" name="sMessage" action="objectManager.php?sClass=maintainer&amp;iAppId='.$this->iAppId.'&amp;sAction=add&amp;sTitle='.urlencode("Be a Super Maintainer for ".$this->sName).'&sReturnTo='.urlencode($this->objectMakeUrl()).'"><input type="submit" value="Be a super maintainer of this app" class="button" title="Click here to know more about super maintainers.">';
            }

            echo "        <input type=\"hidden\" name=\"iAppId\" value=\"".$this->iAppId."\">";
            echo "        <input type=\"hidden\" name=\"iSuperMaintainer\" value=\"1\">"; /* set superMaintainer to 1 because we are at the appFamily level */
            echo "        </form>";
            
            if($_SESSION['current']->isSuperMaintainer($this->iAppId) || $_SESSION['current']->hasPriv("admin"))
            {
                echo '        <form method="post" name="sEdit" action="admin/editAppFamily.php"><input type="hidden" name="iAppId" value="'.$this->iAppId.'"><input type="submit" value="Edit application" class="button"></form>';
                echo '<form method="post" action="objectManager.php?sClass=note&sAction=add&sTitle=Add+note&iAppId='.$this->iAppId.'&sNoteTitle=HOWTO&sReturnTo='.urlencode($this->objectMakeUrl()).'">';
                echo '<input type="submit" value="Add note/how-to" />';
                echo '</form>';
            }
            if($_SESSION['current']->isLoggedIn())
            {
                echo '<form method="post" name="sMessage" action="'.
                        'objectManager.php?sClass=version_queue&amp;iAppId='.$this->iAppId
                        .'&amp;sTitle=Submit+New+Version&amp;sAction=add">';
                echo '<input type=submit value="Submit new version" class="button">';
                echo '</form>';
            }
            if($_SESSION['current']->hasPriv("admin"))
            {
                $url = BASE."objectManager.php?sClass=application&amp;bIsQueue=false&amp;sAction=delete&amp;iId=".$this->iAppId;
                echo "        <form method=\"post\" name=\"sEdit\" action=\"javascript:self.location = '".$url."'\"><input type=\"submit\" value=\"Delete app\" class=\"button\"></form>";
                echo '        <form method="post" name="sEdit" action="admin/editBundle.php"><input type="hidden" name="iBundleId" value="'.$this->iAppId.'"><input type="submit" value="Edit bundle" class="button"></form>';
            }
        } else
        {
            echo '<form method="post" action="account.php?sCmd=login"><input type="submit" value="Log in to become a super maintainer" class="button"></form>';
        }
        echo "        </td></tr>\n";
        echo "      </table>\n"; /* close of super maintainers table */
        echo "    </td></tr>\n";
        echo "  </table>\n"; /* close the table that contains the whole left hand side of the upper table */

        // description
        echo "  <td class=color2 valign=top width='100%'>\n";
        echo "<div class='info_container'>\n";
        echo "\t<div class='title_class'>\n";
        echo "\t\tDescription\n";
        echo "\t</div>\n";                      // close the 'title_class' div
        echo "\t<div class='info_contents'>\n";
        echo "\t\t".$this->sDescription."\n";
        echo "\t</div>\n";                      // close the 'info_contents' div
        echo "</div>\n";                        // close the 'info_container' div

        echo html_frame_end("For more details and user comments, view the versions of this application.");

        // display versions
        Version::displayList($this->getVersions());

        // display bundle
        $this->displayBundle();

        note::displayNotesForEntry(null, $this->iAppId);
    }

    public static function lookup_name($appId)
    {
        if(!$appId) return null;
        $result = query_parameters("SELECT appName FROM appFamily WHERE appId = '?'",
                                   $appId);
        if(!$result || query_num_rows($result) != 1)
            return null;
        $ob = query_fetch_object($result);
        return $ob->appName;
    }

    /* List applications submitted by a given user */
    public static function listSubmittedBy($iUserId, $bQueued = true)
    {
        $hResult = query_parameters("SELECT appId, appName, vendorId, description,
               submitTime FROM appFamily
                WHERE
                submitterId = '?'
                AND
                state = '?'
                    ORDER BY appId", $iUserId, $bQueued ? 'queued' : 'accepted');

        if(!$hResult || !query_num_rows($hResult))
            return false;

        $oTable = new Table();
        $oTable->SetWidth("100%");
        $oTable->SetAlign("center");

        $oTableRow = new TableRow();
        $oTableRow->AddTextCell("Application");
        $oTableRow->AddTextCell("Description");
        $oTableRow->AddTextCell("Developer");
        $oTableRow->AddTextCell("Submission Date");
        $oTableRow->SetClass("color4");
        $oTable->SetHeader($oTableRow);

        if($bQueued)
            $oTableRow->addTextCell("Action");

        for($i = 1; $oRow = query_fetch_object($hResult); $i++)
        {
          
            $oVendor = new vendor($oRow->vendorId);
            $oApp = new application($oRow->appId);
            
            $oTableRow = new TableRow();
            $oTableRow->AddTextCell($oApp->objectMakeLink());
            $oTableRow->AddTextCell($oRow->description);
            $oTableRow->AddTextCell($oVendor->objectMakeLink());
            $oTableRow->AddTextCell(print_date(mysqldatetime_to_unixtimestamp($oRow->submitTime)));
            $oTableRow->SetClass(($i % 2) ? "color0" : "color1");

            if($bQueued)
            {
                $oM = new objectManager('application_queue');
                $oM->setReturnTo(array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : "");
                $shDeleteLink = '<a href="'.$oM->makeUrl("delete", $oApp->iAppId, "Delete entry").'">delete</a>';
                $shEditLink = '<a href="'.$oM->makeUrl("edit", $oApp->iAppId, "Edit entry").'">edit</a>';
                $oTableRow->addTextCell("[ $shEditLink ] &nbsp; [ $shDeleteLink ]");
            }

            $oTable->AddRow($oTableRow);
        }

        return $oTable->GetString();
    }

    public function objectGetClassDisplayName()
    {
        return 'application';
    }

    public function objectMakeUrl()
    {
        $sUrl = APPDB_ROOT."objectManager.php?sClass=application&iId=$this->iAppId";
        return $sUrl;
    }

    public function objectMakeLink()
    {
        $sLink = "<a href=\"".htmlentities($this->objectMakeUrl())."\">".
                 $this->sName."</a>";
        return $sLink;
    }

    public static function objectGetDefaultSort()
    {
        return 'appId';
    }

    public static function objectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = 'default', $bAscending = TRUE, $oFilters = null)
    {
        $sLimit = "";
        $sOrdering = $bAscending ? "ASC" : "DESC";

        if($sOrderBy == 'default')
        {
            if($sState == 'queued')
                $sOrderBy = 'appId';
            else
                $sOrderBy = 'appName';
        }

        $sExtraTables = '';
        $sWhereFilter = $oFilters ? $oFilters->getWhereClause() : '';
        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyDownloadable' => 'false', 'appCategory' => null);

        if($sWhereFilter || $aOptions['onlyDownloadable'] == 'true')
        {
            $sExtraTables = ",appVersion";
            $sBugzillaQuery = '';

            /* We only query the bugzilla table when necessary; these queries
               will hide apps without test results */
            if(strstr($sWhereFilter, 'versions.id'))
            {
                $sExtraTables .= ','.BUGZILLA_DB.'.versions';
                $sBugzillaQuery = ' AND versions.value = appVersion.ratingRelease 
                                    AND versions.product_id = '.BUGZILLA_PRODUCT_ID.' ';
            }

            if($sWhereFilter)
                $sWhereFilter = " AND $sWhereFilter";
            $sWhereFilter = " AND appVersion.state = 'accepted'
                              AND appVersion.appId = appFamily.appId
                              $sBugzillaQuery
                              $sWhereFilter";
        }

        if($aOptions['onlyDownloadable'] == 'true')
        {
            $sExtraTables .= ',appData';
            $sWhereFilter .= " AND appData.type = 'downloadurl' AND appData.versionId = appVersion.versionId AND appData.state = 'accepted'";
        }
        if($aOptions['appCategory'])
        {
            $oCategory = new Category($aOptions['appCategory']);
            $sWhereFilter .= ' AND ' . $oCategory->getSqlQueryPart();
        }
        /* Should we add a limit clause to the query? */
        if($iRows || $iStart)
        {
            $sLimit = " LIMIT ?,?";

            /* Selecting 0 rows makes no sense, so we assume the user wants to select all of them
               after an offset given by iStart */
            if(!$iRows)
                $iRows = application::objectGetEntriesCount($sState);
        }

        $sQuery = "SELECT DISTINCT(appFamily.appId), appFamily.*, vendor.vendorName AS vendorName FROM appFamily, vendor$sExtraTables WHERE
                     appFamily.vendorId = vendor.vendorId
                     AND
                     appFamily.state = '?'$sWhereFilter";

        $oApp = new Application();
        if($sState != 'accepted' && !$oApp->canEdit())
        {
            /* Without global edit rights a user can only view his rejected apps */
            if($sState != 'rejected')
                return FALSE;

            $sQuery .= " AND appFamily.submitterId = '?' ORDER BY ? ?$sLimit";
            if($sLimit)
            {
                $hResult = query_parameters($sQuery, $sState,
                                            $_SESSION['current']->iUserId, $sOrderBy,
                                            $sOrdering, $iStart, $iRows);
            } else
            {
                $hResult = query_parameters($sQuery, $sState,
                                            $_SESSION['current']->iUserId, $sOrderBy,
                                            $sOrdering);
            }
        } else
        {
            $sQuery .= " ORDER BY ? ?$sLimit";
            if($sLimit)
            {
                $hResult = query_parameters($sQuery, $sState, $sOrderBy, $sOrdering,
                                            $iStart, $iRows);
            } else
            {
                $hResult = query_parameters($sQuery, $sState, $sOrderBy, $sOrdering);
            }
        }

        if(!$hResult)
            return FALSE;

        return $hResult;
    }

    public static function objectGetFilterInfo()
    {
        $oFilter = new FilterInterface();
        $aCategories = category::getOrderedList();
        $aCatNames = array();
        $aCatIds = array();

        foreach($aCategories as $oCategory)
        {
            $aCatNames[] = $oCategory->sName;
            $aCatIds[] = $oCategory->objectGetId();
        }

        $aLicenses = version::getLicenses();
        $aWineVersions = get_bugzilla_versions();
        $aWineVersionIds = get_bugzilla_version_ids();

        $oFilter->AddFilterInfo('appVersion.rating', 'Rating', array(FILTER_EQUALS), FILTER_VALUES_ENUM, array('Platinum', 'Gold', 'Silver', 'Bronze', 'Garbage'));
        $oFilter->AddFilterInfo('versions.id', 'Wine version', array(FILTER_EQUALS,FILTER_LESS_THAN,FILTER_GREATER_THAN), FILTER_VALUES_ENUM, $aWineVersionIds, $aWineVersions);
        $oFilter->AddFilterInfo('appCategory', 'Category', array(FILTER_OPTION_ENUM), FILTER_VALUES_OPTION_ENUM, $aCatIds, $aCatNames);
        $oFilter->AddFilterInfo('appVersion.license', 'License', array(FILTER_EQUALS), FILTER_VALUES_ENUM, $aLicenses);
        $oFilter->AddFilterInfo('appFamily.appName', 'Name', array(FILTER_CONTAINS, FILTER_STARTS_WITH, FILTER_ENDS_WITH), FILTER_VALUES_NORMAL);
        $oFilter->AddFilterInfo('onlyDownloadable', 'Only show downloadable apps', array(FILTER_OPTION_BOOL), FILTER_VALUES_OPTION_BOOL, array('false','true'));
        return $oFilter;
    }

    public static function objectGetSortableFields()
    {
        return array('submitTime', 'appName', 'appId', 'userName', 'vendorName');
    }

    public static function objectGetHeader($sState)
    {
        $oTableRow = new TableRowSortable();

        if($sState == 'accepted')
        {
            $oTableRow->AddSortableTextCell('Application', 'appName');
            $oTableRow->AddSortableTextCelL('Entry#', 'appId');
            $oTableRow->AddTextCell('Description');
        } else
        {
            $oTableRow->AddSortableTextCell('Submission Date', 'submitTime');

            /* Only show submitter when processing queued entries */
            $oTableRow->AddTextCell('Submitter');
            $oTableRow->AddSortableTextCell('Developer', 'vendorName');
            $oTableRow->AddSortableTextCell('Application', 'appName');
        }
        return $oTableRow;
    }

    public function objectGetTableRow()
    {
        $oUser = new user($this->iSubmitterId);
        $oVendor = new vendor($this->iVendorId);

        $sVendor = $oVendor->objectMakeLink();

        $oTableRow = new TableRow();

        if($this->sState == 'accepted')
        {
            $oTableRow->AddTextCell($this->objectMakeLink());
            $oTableRow->AddTextCell($this->iAppId);
            $oTableRow->AddTextCell(util_trim_description($this->sDescription));
        } else
        {
            $oTableRow->AddTextCell(print_date(mysqldatetime_to_unixtimestamp($this->sSubmitTime)));
            $oTableRow->AddTextCell($oUser->objectMakeLink());
            $oTableRow->AddTextCell($sVendor);
            $oTableRow->AddTextCell( $this->sName);
        }

        $oOMTableRow = new OMTableRow($oTableRow);
        return $oOMTableRow;
    }

    public function objectGetState()
    {
        return $this->sState;
    }

    public function canEdit()
    {
        if($_SESSION['current']->hasPriv("admin"))
            return TRUE;
        
        if(isset($this) && is_object($this) && $this->iAppId)
        {
            if(maintainer::isUserSuperMaintainer($_SESSION['current'],
                $this->iAppId))
                return TRUE;

            if($this->sState != 'accepted' && $this->iSubmitterId ==
               $_SESSION['current']->iUserId)
            {
                return TRUE;
            }

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
        else
            return TRUE;
    }

    public function objectDisplayQueueProcessingHelp()
    {
        echo "<p>This is the list of application entries waiting to be processed.</p>\n";
        echo "<p>To view and process an entry, use the links under &#8216;Action&#8217;</p>";
    }

    public function objectDisplayAddItemHelp()
    {
        /* We don't display the full help on the page where you only input the app name */
        if(!$this->sName)
        {
            echo "<p>First, please enter the name of the application you wish to add. ";
            echo "This will allow you to determine whether there is already ";
            echo "an entry for it in the database.</p>\n";
        } else
        {
            echo "<p>This page is for submitting new applications to be added to the\n";
            echo "database. The application will be reviewed by an AppDB Administrator,\n";
            echo "and you will be notified via e-mail if it is added to the database or rejected.</p>\n";
            echo "<p><h2>Before continuing, please ensure that you have</h2>\n";
            echo "<ul>\n";
            echo " <li>Entered a valid version for this application.  This is the application\n";
            echo "   version, NOT the Wine version (which goes in the test results section of the template)</li>\n";
            echo " <li>Tested this application under Wine.  There are tens of thousands of applications\n";
            echo "   for Windows, we do not need placeholder entries in the database.  Please enter as complete \n";
            echo "   as possible test results in the version template provided below</li>\n";
            echo "</ul></p>";
            echo "<p>Having app descriptions just sponsoring the app\n";
            echo "(yes, some vendors want to use the appdb for this) or saying &#8216;I haven't tried this app with Wine&#8217; ";
            echo "will not help Wine development or Wine users. Application descriptions should be exactly that and only that, \n";
            echo "they should not contain any information about how well the app works, just what the app is. The same applies to the \n";
            echo "version information, it should be only information on what is unique or different about that version of the application, \n";
            echo "not how well that version works or how great you think a new feature is.</p>\n";
            echo "<p>When you reach the \"Test Form\" part (What works, What doesn't work, etc) please be detailed \n";
            echo "about how well it worked and if any workarounds were needed but do NOT paste chunks of terminal output.</p>\n";
            echo "<p>Please write information in proper English with correct grammar and punctuation!</p>\n";            
            echo "<b><span style=\"color:red\">Please only submit applications/versions that you have tested.\n";
            echo "Submissions without test information or not using the provided template will be rejected.\n";
            echo "If you are unable to see the in-browser editors below, please try Firefox, Mozilla or Opera browsers.\n</span></b>";
            echo "<p>After your application has been added, you will be able to submit screenshots for it, post";
            echo " messages in its forums or become a maintainer to help others trying to run the application.</p>";
        }
    }

    public static function objectGetEntriesCount($sState, $oFilters = null)
    {
        $sExtraTables = '';
        $sWhereFilter = $oFilters ? $oFilters->getWhereClause() : '';
        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyDownloadable' => 'false', 'appCategory' => null);

        if($sWhereFilter || $aOptions['onlyDownloadable'] == 'true')
        {
            $sExtraTables = ',appVersion';
            $sBugzillaQuery = '';

            /* We only query the bugzilla table when necessary; these queries
               will hide apps without test results */
            if(strstr($sWhereFilter, 'versions.id'))
            {
                $sExtraTables .= ','.BUGZILLA_DB.'.versions';
                $sBugzillaQuery = ' AND versions.value = appVersion.ratingRelease 
                                    AND versions.product_id = '.BUGZILLA_PRODUCT_ID.' ';
            }

            if($sWhereFilter)
                $sWhereFilter = " AND $sWhereFilter";
            $sWhereFilter = " AND appVersion.appId = appFamily.appId 
                              $sBugzillaQuery
                              $sWhereFilter";
        }

        if($aOptions['onlyDownloadable'] == 'true')
        {
            $sExtraTables .= ',appData';
            $sWhereFilter .= " AND appData.type = 'downloadurl' AND appData.versionId = appVersion.versionId";
        }
        if($aOptions['appCategory'])
        {
            $oCategory = new Category($aOptions['appCategory']);
            $sWhereFilter .= ' AND ' . $oCategory->getSqlQueryPart();
        }

        $oApp = new Application();
        if($sState != 'accepted' && !$oApp->canEdit())
        {
            /* Without edit rights users can only resubmit their rejected entries */
            if($sState != 'rejected')
                return FALSE;

            $sQuery = "SELECT COUNT(DISTINCT(appFamily.appId)) as count FROM appFamily$sExtraTables WHERE
                    submitterId = '?'
                    AND
                    appFamily.state = '?'$sWhereFilter";
            $hResult = query_parameters($sQuery, $_SESSION['current']->iUserId,
                                        $sState);
        } else
        {
            $sQuery = "SELECT COUNT(DISTINCT(appFamily.appId)) as count FROM appFamily$sExtraTables WHERE appFamily.state = '?'$sWhereFilter";
            $hResult = query_parameters($sQuery, $sState);
        }

        if(!$hResult)
            return FALSE;

        if(!$oRow = query_fetch_object($hResult))
            return FALSE;

        return $oRow->count;
    }

    public function getVersions($bOnlyAccepted = false, $bIncludeObsolete = TRUE, $bIncludeDeleted = false)
    {
        /* If no id is set we cannot query for the versions, but perhaps objects are already cached? */
        if(!$this->iAppId)
            return $this->aVersions;

        $aVersions = array();

        $hResult = $this->_internal_retrieve_all_versions($bOnlyAccepted, $bIncludeObsolete, $bIncludeDeleted);

        while($oRow = mysql_fetch_object($hResult))
            $aVersions[] = new version($oRow->versionId);

        return $aVersions;
    }

    /* Make a drop-down list of this application's versions.  Optionally set the default
       versionId, a version to exclude and whether to not show obsolete versions */
    public function makeVersionDropDownList($sVarName, $iCurrentId = null, $iExclude = null, $bIncludeObsolete = TRUE)
    {
        $sMsg = "<select name=\"$sVarName\">\n";
        foreach($this->getVersions() as $oVersion)
        {
            if($oVersion->objectGetState() != 'accepted' || $oVersion->iVersionId == $iExclude ||
               (!$bIncludeObsolete && $oVersion->iObsoleteBy))
                continue;

            $sMsg .= "<option value=\"".$oVersion->iVersionId."\"";

            if($oVersion->iVersionId == $iCurrentId)
                $sMsg .= " selected=\"selected\"";

            $sMsg .= ">".$oVersion->sName."</option>\n";
        }
        $sMsg .= "</select>\n";

        return $sMsg;
    }

    public function objectAllowCreatingParents()
    {
        return true;
    }

    public function objectSetParent($iNewId, $sClass = '')
    {
        $this->iVendorId = $iNewId;
    }

    public function objectGetParent($sClass = '')
    {
        return new vendor($this->iVendorId);
    }

    public function objectGetChildren($bIncludeDeleted = false)
    {
        return $this->objectGetChildrenClassSpecific('', $IncludeDeleted);
    }

    public function objectGetChildrenClassSpecific($sClass = '', $bIncludeDeleted = false)
    {
        $aChildren = array();

        /* Get versions */
        foreach($this->getVersions(false, true, $bIncludeDeleted) as $oVersion)
        {
            if($sClass != 'version')
                $aChildren += $oVersion->objectGetChildren($bIncludeDeleted);
            $aChildren[] = $oVersion;
        }

        if($sClass == 'version')
            return $aChildren;

        /* Get urls */
        $sQuery = "SELECT * FROM appData WHERE type = '?' AND appId = '?'";
        $hResult = query_parameters($sQuery, "url", $this->iAppId);

        if(!$hResult)
            return FALSE;

        while($oRow = mysql_fetch_object($hResult))
        {
            $oUrl = new url(0, $oRow);
            $aChildren += $oUrl->objectGetChildren($bIncludeDeleted);
            $aChildren[] = $oUrl;
        }

        /* Get maintainers */
        $sQuery = "SELECT * FROM appMaintainers WHERE appId = '?' AND superMaintainer = '?'";
        $hResult = query_parameters($sQuery, $this->iAppId, '1');

        if(!$hResult)
            return FALSE;

        while($oRow = mysql_fetch_object($hResult))
        {
            $oMaintainer = new maintainer(0, $oRow);
            $aChildren += $oMaintainer->objectGetChildren($bIncludeDeleted);
            $aChildren[] = $oMaintainer;
        }

        return $aChildren;
    }

    public function objectMoveChildren($iNewId)
    {
        /* Keep track of how many children we have moved */
        $iCount = 0;

        foreach($this->aVersionsIds as $iVersionId)
        {
            $oVersion = new version($iVersionId);
            $oVersion->iAppId = $iNewId;
            if($oVersion->update())
                $iCount++;
            else
                return FALSE;
        }

        /* If no errors occurred we return the number of moved children */
        return $iCount;
    }

    public static function objectAllowMovingToNewParent()
    {
        return TRUE;
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
        return $this->iAppId;
    }

    public function objectShowAddEntry()
    {
        return TRUE;
    }
}

?>
