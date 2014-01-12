<?php
/***************************************/
/* url class and related functions */
/***************************************/
require_once(BASE."include/util.php");

/**
 * Class for handling URLs (aka Links) of applications and
 * of application versions.
 * 
 * Uses the appData table to store the URLs in rows with type='url'.
 */
class Url {
    var $iUrlId;
    var $iAppId;
    var $iVersionId;
    var $sDescription;
    var $sUrl;
    var $sSubmitTime;
    var $sSubmitterId;
    var $bQueued;


    /**    
     * Constructor, fetches the url $iUrlId is given.
     */
    function Url($iUrlId = null, $oRow = null)
    {
        if(!$iUrlId && !$oRow)
            return;

        if(!$oRow)
        {
            $sQuery = "SELECT appData.*
                       FROM appData
                       WHERE type = 'url'
                       AND id = '?'";
            $hResult = query_parameters($sQuery, $iUrlId);
            if(!$hResult)
                return;
            $oRow = query_fetch_object($hResult);
        }

        // we are working on an existing url
        if($oRow)
        {
            $this->iUrlId = $oRow->id;
            $this->sDescription = $oRow->description;
            $this->iAppId = $oRow->appId;
            $this->iVersionId = $oRow->versionId;
            $this->sUrl = Url::normalize($oRow->url);
            $this->bQueued = ($oRow->state == 'accepted') ? false : true;
            $this->sSubmitTime = $oRow->submitTime;
            $this->iSubmitterId = $oRow->submitterId;
        }
    }
 

    /**
     * Creates a new url.
     * Writes the url into the appData table, then updates the object.
     * 
     * @param bSilent Whether to send a notification email.
     * @return true if everything went fine.
     */
    function create($sDescription = null, $sUrl = null, $iVersionId = null,
                    $iAppId = null, $bSilent = false)
    {
        // Security, if we are not an administrator or a maintainer, the url must be queued.
        if(($iAppId && !url::canEdit(NULL, $iAppId)) ||
        ($iVersionId && !url::canEdit($iVersionId)))
            $this->bQueued = true;

        $hResult = query_parameters("INSERT INTO appData (appId, versionId, type,
            description, state, submitTime, submitterId, url)
                VALUES ('?', '?', '?', '?', '?', ?, '?', '?')",
                    $iAppId, $iVersionId, "url", $sDescription,
                    $this->bQueued ? 'queued' : 'accepted',
                    "NOW()", $_SESSION['current']->iUserId, $sUrl);

        if(!$hResult)
        {
            addmsg("Error while creating a new url.", "red");
            return false;
        }

        $this->iUrlId = query_appdb_insert_id();
        $this->Url($this->iUrlId);

        if(!$bSilent)
            $this->SendNotificationMail();

        return true;
    }

    function purge()
    {
        return $this->delete();
    }

    /**    
     * Deletes the url from the database. 
     * and request its deletion from the filesystem (including the thumbnail).
     */
    function delete()
    {
        $sQuery = "DELETE FROM appData 
                   WHERE id = '?' 
                   AND type = 'url' 
                   LIMIT 1";
        if(!$hResult = query_parameters($sQuery, $this->iUrlId))
            return false;

        return true;
    }


    /**
     * Move url out of the queue.
     */
    function unQueue()
    {
        // If we are not in the queue, we can't move the url out of the queue.
        if(!$this->bQueued)
            return false;

        if(query_parameters("UPDATE appData SET state '?' WHERE id='?'",
                       'accepted', $this->iUrlId))
        {
            // we send an e-mail to interested people
            $this->mailSubmitter();
            $this->SendNotificationMail();
            // the url has been unqueued
            addmsg("The url has been unqueued.", "green");
        }
    }


    /**
     * Update url.
     * Returns true on success and false on failure.
     */
    function update($sDescription = null, $sUrl = null, $iVersionId = null, $iAppId = null, $bSilent = false)
    {
        if(!$this->iUrlId)
            return FALSE;

        $oUrl = new url($this->iUrlId);

        if($this->iVersionId && !$iVersionId)
            $iVersionId = $this->iVersionId;

        $sWhatChanged = "";

        if ($sDescription && $sDescription!=$this->sDescription)
        {
            if (!query_parameters("UPDATE appData SET description = '?' WHERE id = '?'",
                                  $sDescription, $this->iUrlId))
                return false;
            $sWhatChanged .= "Description was changed from\n ".$this->sDescription."\n to \n".$sDescription.".\n\n";
            $this->sDescription = $sDescription;
        }

        if ($sUrl && $sUrl!=$this->sUrl)
        {
            if (!query_parameters("UPDATE appData SET url = '?' WHERE id = '?'",
                                  $sUrl, $this->iUrlId))
                return false;
            $sWhatChanged .= "Url was changed from ".$this->sUrl." to ".$sUrl.".\n\n";
            $this->sUrl = $sUrl;
        }

        if ($iVersionId && $iVersionId!=$oUrl->iVersionId)
        {
            if (!query_parameters("UPDATE appData SET versionId = '?' WHERE id = '?'",
                                  $iVersionId, $this->iUrlId))
                return false;
            $oVersionBefore = new Version($this->iVersionId);
            $oVersionAfter = new Version($iVersionId);
            $sWhatChanged .= "Version was changed from ".$oVersionBefore->sName." to ".$oVersionAfter->sName.".\n\n";
            $this->iVersionId = $iVersionId;
            $this->iAppId = $oVersionAfter->iAppId;
        }

        if ($iAppId && $iAppId!=$this->iAppId)
        {
            if (!query_parameters("UPDATE appData SET appId = '?' WHERE id = '?'",
                                  $iAppId, $this->iUrlId))
                return false;
            $oAppBefore = new Application($this->iAppId);
            $oAppAfter = new Application($iAppId);
            $sWhatChanged .= "Application was changed from ".$oAppBefore->sName." to ".$oAppAfter->sName.".\n\n";
            $this->iAppId = $iAppId;
        }
        if($sWhatChanged && !$bSilent)
            $this->SendNotificationMail("edit",$sWhatChanged);       
        return true;
    }

    
    function mailSubmitter($bRejected=false)
    {
        global $aClean;

        if($this->iSubmitterId)
        {
            $sAppName = Application::lookup_name($this->iAppId)." ".Version::lookup_name($this->iVersionId);
            $oSubmitter = new User($this->iSubmitterId);
            if(!$bRejected)
            {
                $sSubject =  "Submitted url accepted";
                $sMsg  = "The url you submitted for ".$sAppName." has been accepted.";
            } else
            {
                 $sSubject =  "Submitted url rejected";
                 $sMsg  = "The url you submitted for ".$sAppName." has been rejected.";
            }
            $sMsg .= $aClean['sReplyText']."\n";
            $sMsg .= "We appreciate your help in making the Application Database better for all users.";
                
            mail_appdb($oSubmitter->sEmail, $sSubject ,$sMsg);
        }
    }

 
    function SendNotificationMail($bDeleted=false)
    {
        /* Set variables depending on whether the url is for an app or version */
        if($this->iVersionId)
        {
            $oVersion = new version($this->iVersionId);
            $sAppName = version::fullName($this->iVersionId);
            $sUrl = $oVersion->objectMakeUrl();
        } else
        {
            $oApp = new application($this->iAppId);
            $sAppName = $oApp->sName;
            $sUrl = $oApp->objectMakeUrl();
        }

        if(!$bDeleted)
        {
            if(!$this->bQueued)
            {
                $sSubject = "Url for $sAppName added by ".$_SESSION['current']->sRealname;
                $sMsg  = "$sUrl\n";
                if($this->iSubmitterId)
                {
                    $oSubmitter = new User($this->iSubmitterId);
                    $sMsg .= "This url has been submitted by ".$oSubmitter->sRealname.".";
                    $sMsg .= "\n";
                }
                addmsg("The url was successfully added into the database.", "green");
            } else // Url queued.
            {
                $sSubject = "Url for $sAppName submitted by ".
                        $_SESSION['current']->sRealname;
                $sMsg  = "$sUrl\n";
                $sMsg .= "This url has been queued.";
                $sMsg .= "\n";
                addmsg("The url you submitted will be added to the database ".
                        "database after being reviewed.", "green");
            }
        }

        $sEmail = User::get_notify_email_address_list(null, $this->iVersionId);
        if($sEmail)
            mail_appdb($sEmail, $sSubject ,$sMsg);
    }

    /* Output an editor for URL fields */
    function outputEditor($sFormAction, $oVersion, $oApp = NULL)
    {
        /* Check for correct permissions */
        if(($oVersion && !url::canEdit($oVersion->iVersionId)) ||
            ($oApp && !url::canEdit(NULL, $oApp->iAppId)))
            return FALSE;

        if($oVersion)
            $hResult = appData::getData($oVersion->iVersionId, "url");
        else
            $hResult = appData::getData($oApp->iAppId, "url", FALSE);

        $sReturn = html_frame_start("URLs", "90%", "", 0);
        $sReturn .= "<form method=\"post\" action=\"$sFormAction\">\n";
        $sReturn .= html_table_begin("border=0 cellpadding=5 cellspacing=0 width=100%");
        $sReturn .= html_tr(array(
            array("<b>Remove</b>", "width=\"90\""),
            "<b>Description</b>",
            "<b>URL</b>"
            ),
            "color0");

            $sReturn .= html_tr(array(
                "&nbsp;",
                "<input type=\"text\" size=\"45\" name=\"".
                "sDescriptionNew\">",
                "<input type=\"text\" size=\"45\" name=\"sUrlNew\">"),
                "color4");

        if($hResult)
        {
            for($i = 1; $oRow = query_fetch_object($hResult); $i++)
            {
                $sReturn .= html_tr(array(
                    "<input type=\"checkbox\" name=\"bRemove$oRow->id\" ".
                    "value=\"true\">",
                    "<input type=\"text\" size=\"45\" name=\"".
                    "sDescription$oRow->id\" value=\"$oRow->description\">",
                    "<input type=\"text\" size=\"45\" name=\"sUrl$oRow->id\" ".
                    "value=\"$oRow->url\">"),
                    ($i % 2) ? "color0" : "color4");
            }
        }

        if($oVersion)
            $iAppId = $oVersion->iAppId;
        else
            $iAppId = $oApp->iAppId;

        $sReturn .= html_table_end();
        $sReturn .= "<div align=\"center\"><input type=\"submit\" value=\"".
                    "Update URLs\" name=\"sSubmit\"></div>\n";

        if($oVersion)
            $sReturn .=" <input type=\"hidden\" name=\"iVersionId\" ".
                    "value=\"$oVersion->iVersionId\">\n";

        $sReturn .= "<input type=\"hidden\" name=\"iAppId\" ".
                    "value=\"$iAppId\">\n";
        $sReturn .= "</form>\n";
        $sReturn .= html_frame_end("&nbsp;");

        return $sReturn;
    }

    /* Process data from a URL form */
    function ProcessForm($aValues)
    {

        /* Check that we are processing a Download URL form */
        if($aValues["sSubmit"] != "Update URLs")
            return FALSE;

        /* Check permissions */
        if(($aValues['iVersionId'] && !url::canEdit($aValues["iVersionId"])) ||
            (!$aValues['iVersionId'] && !url::canEdit(NULL, $aValues['iAppId'])))
            return FALSE;

        if($aValues["iVersionId"])
        {
            if(!($hResult = query_parameters("SELECT COUNT(*) as num FROM appData 
                WHERE TYPE = '?' AND versionId = '?'",
                    "url", $aValues["iVersionId"])))
                return FALSE;
        } else
        {
            if(!($hResult = query_parameters("SELECT COUNT(*) as num FROM appData 
                WHERE TYPE = '?' AND appId = '?'",
                    "url", $aValues["iAppId"])))
                return FALSE;
        }

        if(!($oRow = query_fetch_object($hResult)))
            return FALSE;

        $num = $oRow->num;

        /* Update URLs.  Nothing to do if none are present in the database */
        if($num)
        {
            if($aValues['iVersionId'])
            {
                if(!$hResult = appData::getData($aValues["iVersionId"], "url"))
                    return FALSE;
            } else
            {
                if(!$hResult = appData::getData($aValues['iAppId'], "url", FALSE))
                    return FALSE;
            }

            while($oRow = query_fetch_object($hResult))
            {
                $url = new url($oRow->id);

                /* Remove URL */
                if($aValues["bRemove$oRow->id"])
                {
                    if(!$url->delete(TRUE))
                        return FALSE;

                    $sWhatChangedRemove .= "Removed\nURL: $oRow->url\n".
                    "Description: $oRow->description\n\n";
                }

                /* Change description/URL */
                if(($aValues["sDescription$oRow->id"] != $oRow->description or 
                $aValues["sUrl$oRow->id"] != $oRow->url) && 
                $aValues["sDescription$oRow->id"] && $aValues["sUrl$oRow->id"])
                {
                    if(!$url->update($aValues["sDescription$oRow->id"],
                    $aValues["sUrl$oRow->id"], $aValues["iVersionId"],
                    $aValues["iVersionId"] ? 0 : $aValues["iAppId"], TRUE))
                        return FALSE;

                    $sWhatChangedModify .= "Modified\nOld URL: $oRow->url\nOld ".
                        "Description: $oRow->description\nNew URL: ".
                        $aValues["sUrl$oRow->id"]."\nNew Description: ".
                        $aValues["sDescription$oRow->id"]."\n\n";
                }
            }
        }

        /* Insert new URL */
        if($aValues["sDescriptionNew"] && $aValues["sUrlNew"])
        {
            $url = new Url();

            if(!$url->create($aValues["sDescriptionNew"], $aValues["sUrlNew"],
                $aValues["iVersionId"] ? $aValues["iVersionId"] : "0",
                $aValues["iVersionId"] ? "0" : $aValues["iAppId"], TRUE))
                return FALSE;

            $sWhatChanged = "Added\nURL: ".$aValues["sUrlNew"]."\nDescription: ".
                            $aValues["sDescriptionNew"]."\n\n";
        }

        $sWhatChanged .= "$sWhatChangedRemove$sWhatChangedModify";

        if($aValues["iVersionId"])
            $sEmail = User::get_notify_email_address_list($aValues['iVersionId']);
        else
            $sEmail = User::get_notify_email_address_list($aValues['iAppId']);
        if($sWhatChanged && $sEmail)
        {
            $oApp = new Application($aValues["iAppId"]);

            if($aValues["iVersionId"])
            {
                $oVersion = new Version($aValues["iVersionId"]);
                $sVersionName = " $oVersion->sName";
            }

            $sSubject = "Links for $oApp->sName$sVersionName updated by ". 
                        $_SESSION['current']->sRealname;

            $sMsg = $aValues["iVersionId"] ? 
                $oVersion->objectMakeUrl() :
                $oApp->objectMakeUrl();
            $sMsg .= "\n\n";
            $sMsg .= "The following changed were made\n\n";
            $sMsg .= "$sWhatChanged\n\n";

            mail_appdb($sEmail, $sSubject, $sMsg);
        }

        return TRUE;
    }

    function canEdit($iVersionId = NULL, $iAppId = NULL)
    {
        if($_SESSION['current']->hasPriv("admin"))
            return TRUE;
        else if($iVersionId)
        {
            $oVersion = new version($iVersionId);
            return $oVersion->canEdit();
        } else if($iAppId)
        {
            $oApp = new application($iAppId);
            return $oApp->canEdit();
        } else
            return FALSE;
    }

    /* Display links for a given version/application */
    public static function display($iVersionId, $iAppId = NULL)
    {
        if($iVersionId)
        {
            if(!($hResult = appData::getData($iVersionId, "url")))
                return FALSE;
        } else
        {
            if(!($hResult = appData::getData($iAppId, "url", FALSE)))
                return FALSE;
        }

        $sReturn = '';
        for($i = 0; $oRow = query_fetch_object($hResult); $i++)
        {
            // create a url object
            $oUrl = new Url(null, $oRow);

            $sReturn .= html_tr(array(
                "<b>Link</b>",
                "<a href=\"$oUrl->sUrl\">$oUrl->sDescription</a>"),
                "color1");
        }

        return $sReturn;
    }

    function objectGetId()
    {
        return $this->iUrlId;
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
        $oAppData = new appData($this->iUrlId, null, $this);
        return $oAppData->objectGetParent();
    }

    public function objectSetParent($iNewId, $sClass = '')
    {
        if($this->iVersionId)
            $this->iVersionId = $iNewId;
        else
            $this->iAppId = $iNewId;
    }

    function objectGetChildren($bIncludeDeleted = false)
    {
        return array();
    }

    // if a url lacks "://" assume the url is an http one
    // and prepend "http://"
    public static function normalize($sTheUrl)
    {
        // return if we have an empty string
        if($sTheUrl == "")
            return $sTheUrl;

        // if we already have "://" in the url
        // we can leave the url alone
        if(strpos($sTheUrl, "://") === false)
        {
            // assume this is a website and prepend "http://"
            return "http://".$sTheUrl;
        } else
        {
            // leave the url alone
            return $sTheUrl;
        }
    }
}
?>
