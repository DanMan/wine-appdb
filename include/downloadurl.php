<?php

/**
 * Functions related to download links
 */

require_once(BASE."include/appData.php");

class downloadurl
{
    var $iId;
    var $iVersionId;
    var $sDescription;
    var $sUrl;
    var $sSubmitTime;
    var $iSubmitterId;
    var $bQueued;

    /* Fetch the data if id is given */
    function downloadurl($iId = NULL, $oRow = NULL)
    {
        if(!$iId && !$oRow)
            return;

        if(!$oRow)
        {
            $hResult = query_parameters("SELECT id, versionId, description, url,
                submitTime, submitterId, queued FROM appData WHERE id = '?'",
                    $iId);

            if($hResult && mysql_num_rows($hResult))
                $oRow = mysql_fetch_object($hResult);
        }

        if($oRow)
        {
            $this->iId = $oRow->id;
            $this->iVersionId = $oRow->versionId;
            $this->sDescription = $oRow->description;
            $this->sUrl = $oRow->url;
            $this->sSubmitTime = $oRow->submitTime;
            $this->iSubmitterId = $oRow->submitterId;
            $this->bQueued = ($oRow->queued == "true") ? TRUE : FALSE;
        }
    }

    /* Display download links for a given version */
    function display($iVersionId)
    {
        if(!($hResult = appData::getData($iVersionId, "downloadurl")))
            return FALSE;

        for($i = 0; $oRow = mysql_fetch_object($hResult); $i++)
        {
            $sReturn .= html_tr(array(
                "<b>Free Download</b>",
                "<a href=\"$oRow->url\">$oRow->description</a>"),
                "color1");
        }

        return $sReturn;
    }

    /* Output an editor for Download URL fields */
    function outputEditor($oVersion = null, $sFormAction = null)
    {
        /* If we do not get any parameters we try to behave like a proper objectManager
           object, by only showing an editor for one entry instead of several.  This
           code is sort of hacky, and should be fixed once the rest of the downloadurl
           code is fully adapted to the objectManager */
        if(!$oVersion && !$sFormAction)
        {
            global $aClean;
            echo downloadurl::outputEditorSingle($this->iVersionId);
        }

        if(!$oVersion || !$sFormAction)
            return FALSE;

        /* Check for correct permissions */
        if(!downloadurl::canEdit($oVersion->iVersionId))
            return FALSE;

        $hResult = appData::getData($oVersion->iVersionId, "downloadurl");

        $sReturn .= html_frame_start("Download URL", "90%", "", 0);
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
                "sDescriptionNew\" />",
                "<input type=\"text\" size=\"45\" name=\"sUrlNew\" />"),
                "color4");

        if($hResult)
        {
            for($i = 1; $oRow = mysql_fetch_object($hResult); $i++)
            {
                $sReturn .= html_tr(array(
                    "<input type=\"checkbox\" name=\"bRemove$oRow->id\" ".
                    "value=\"true\" />",
                    "<input type=\"text\" size=\"45\" name=\"".
                    "sDescription$oRow->id\" value=\"$oRow->description\" />",
                    "<input type=\"text\" size=\"45\" name=\"sUrl$oRow->id\" ".
                    "value=\"$oRow->url\" />"),
                    ($i % 2) ? "color0" : "color4");
            }
        }

        $sReturn .= html_table_end();
        $sReturn .= "<div align=\"center\"><input type=\"submit\" value=\"".
                    "Update Download URLs\" name=\"sSubmit\" /></div>\n";
        $sReturn .= "<input type=\"hidden\" name=\"iVersionId\" ".
                    "value=\"$oVersion->iVersionId\" />\n";
        $sReturn .= "<input type=\"hidden\" name=\"iAppId\" ".
                    "value=\"$oVersion->iAppId\" />\n";
        $sReturn .= "</form>\n";
        $sReturn .= html_frame_end("&nbsp;");

        return $sReturn;
    }

    /* Process data from a Download URL form */
    function ProcessForm($aValues)
    {
        /* Check that we are processing a Download URL form */
        if($aValues["sSubmit"] != "Update Download URLs")
            return FALSE;

        /* Check permissions */
        if(!downloadurl::canEdit($aValues["iVersionId"]))
            return FALSE;

        if(!($hResult = query_parameters("SELECT COUNT(*) as num FROM appData 
            WHERE TYPE = '?' AND versionId = '?'",
                "downloadurl", $aValues["iVersionId"])))
            return FALSE;

        if(!($oRow = mysql_fetch_object($hResult)))
            return FALSE;

        $num = $oRow->num;

        /* Update URLs.  Nothing to do if none are present in the database */
        if($num)
        {
            if(!$hResult = appData::getData($aValues["iVersionId"], "downloadurl"))
                return FALSE;

            while($oRow = mysql_fetch_object($hResult))
            {
                $oDownloadurl = new downloadurl($oRow->id);

                /* Remove URL */
                if($aValues["bRemove$oRow->id"])
                {
                    if(!$oDownloadurl->delete())
                        return FALSE;

                    $sWhatChangedRemove .= "Removed\nURL: $oRow->url\n".
                    "Description: $oRow->description\n\n";
                }

                /* Change description/URL */
                if(($aValues["sDescription$oRow->id"] != $oRow->description or 
                $aValues["sUrl$oRow->id"] != $oRow->url) && 
                $aValues["sDescription$oRow->id"] && $aValues["sUrl$oRow->id"])
                {
                    $oDownloadurl->sDescription =
                        $aValues["sDescription$oRow->id"];
                    $oDownloadurl->sUrl = $aValues["sUrl$oRow->id"];

                    if(!$oDownloadurl->update())
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
            $oDownloadurl = new downloadurl();
            $oDownloadurl->iVersionId = $aValues["iVersionId"];
            $oDownloadurl->sUrl = $aValues["sUrlNew"];
            $oDownloadurl->sDescription = $aValues["sDescriptionNew"];

            if(!$oDownloadurl->create())
                return FALSE;

            $sWhatChanged = "Added\nURL: ".$aValues["sUrlNew"]."\nDescription: ".
                            $aValues["sDescriptionNew"]."\n\n";
        }

        $sWhatChanged .= "$sWhatChangedRemove$sWhatChangedModify";

        if($sWhatChanged && $sEmail =
           User::get_notify_email_address_list($aValues['iVersionId']))
        {
            $oVersion = new Version($aValues["iVersionId"]);

            $sSubject = "Download URLs for ".version::fullName($oVersion->iVersionId).
            " updated by ".$_SESSION['current']->sRealname;

            $sMsg = $oVersion->objectMakeUrl();
            $sMsg .= "\n\n";
            $sMsg .= "The following changed were made\n\n";
            $sMsg .= "$sWhatChanged\n\n";

            mail_appdb($sEmail, $sSubject, $sMsg);
        }

        return TRUE;
    }

    function canEdit($iVersionId = NULL)
    {
        $oUser = new User($_SESSION['current']->iUserId);

        if($oUser->hasPriv("admin") || ($iVersionId &&
           maintainer::isUserMaintainer($oUser, $iVersionId)))
        {
            return TRUE;
        } else
        {
            return FALSE;
        }
    }

    /* Output an editor for a single new URL */
    function outputEditorSingle($iVersionId = NULL, $aValues = NULL)
    {
        if($aValues["sDownloadUrlUrl"] &&
           $aValues["sDownloadUrlDescription"])
        {
            $sDownloadUrlUrl = $aValues["sDownloadUrlUrl"];
            $sDownloadUrlDescription = $aValues["sDownloadUrlDescription"];
        } else if($iVersionId)
        {
            /* This illustrates the importance of converting downloadurl completely
               to the objectManager model.  If we don't get  a match searching for
               a queued entry, try finding a rejected one. */
            if(($hResult = appData::getData($iVersionId, "downloadurl",
                                           TRUE, TRUE, FALSE)) ||
               $hResult = appData::getData($iVersionId, "downloadurl",
                                           TRUE, TRUE, TRUE))
            {
                $oRow = mysql_fetch_object($hResult);
                $sDownloadUrlUrl = $oRow->url;
                $sDownloadUrlDescription = $oRow->description;
            }
        }


        $sReturn .= html_frame_start("Download URL","90%");
        $sReturn .= html_table_begin("width='100%' border=0 cellpadding=2 cellspacing=0");

        $sReturn .= "A place where this version can be downloaded for free".
                    " (if applicable). You can add more links later.<br />";

        $sReturn .= html_tr(array(
            array("<b>Download URL:</b>", "align=right"),
            array("<input type=\"text\" name=\"sDownloadUrlUrl\" ".
            "value=\"$sDownloadUrlUrl\" size=\"60\" />",
            "class=\"color4\"")),
            "color0");

        $sReturn .= html_tr(array(
            array("<b>Download URL Description:</b>", "align=right"),
            array("<input type=\"text\" name=\"sDownloadUrlDescription\" ".
            "value=\"$sDownloadUrlDescription\" size=\"60\" />",
            "class=\"color4\"")),
            "color0");

        $sReturn .= html_table_end();
        $sReturn .= html_frame_end("nbsp;");

        return $sReturn;
    }

    function create()
    {
        if(!$this->sUrl or !$this->sDescription or !$this->iVersionId)
            return FALSE;

        $hResult = query_parameters("INSERT INTO appData (versionId, type,
            description, url, queued, submitterId)
                VALUES('?','?','?','?','?','?')",
                    $this->iVersionId, "downloadurl", $this->sDescription,
                    $this->sUrl, downloadurl::canEdit($this->iVersionId) ?
                    "false" : "true", $_SESSION['current']->iUserId);

        if(!$hResult)
            return FALSE;

        return TRUE;
    }

    function update()
    {
        if(!$this->bQueued && !$this->canEdit($this->iVersionId))
            return FALSE;

        $hResult = query_parameters("UPDATE appData SET
                   description = '?', url = '?' WHERE id = '?'",
                       $this->sDescription, $this->sUrl, $this->iId);

        if(!$hResult)
            return FALSE;

        return TRUE;
    }

    /* Process a form made only for submitting one URL */
    function processFormSingle($iVersionId, $aValues, $bUnQueue = FALSE)
    {
        /* Calling this function without suitable input data is perfectly valid,
           but in that case there is nothing to do here */
        if(empty($aValues['sDownloadUrlDescription']) || 
                empty($aValues['sDownloadUrlUrl']))
            return;

        $iId = null;
        if($hResult = appData::getData($iVersionId, "downloadurl", TRUE, TRUE))
        {
            $oObject = mysql_fetch_object($hResult);
            $iId = $oObject->id;
        }

        $oDownloadurl = new downloadurl($iId);

        $oDownloadurl->sDescription = $aValues['sDownloadUrlDescription'];
        $oDownloadurl->sUrl = $aValues['sDownloadUrlUrl'];
        $oDownloadurl->iVersionId = $iVersionId;

        if($iId)
            $oDownloadurl->update();
        else
            $oDownloadurl->create();

        if($bUnQueue)
            $oDownloadurl->unQueue();
    }

    function unQueue()
    {
        if(!$this->canEdit($this->iVersionId))
            return FALSE;

        $hResult = query_parameters("UPDATE appData SET queued = '?'
                   WHERE id = '?'",
                       "false", $this->iId);

        if(!$hResult)
            return FALSE;

        return TRUE;
    }

    function delete()
    {
        if(!downloadurl::canEdit($this->iVersionId))
            return FALSE;

        if(!query_parameters("DELETE FROM appData WHERE id = '?'", $this->iId))
            return FALSE;

        return TRUE;
    }

    function reQueue()
    {
        $oAppData = new AppData($this->iId);

        return $oAppData->reQueue();
    }

    function reject()
    {
        $oAppData = new AppData($this->iId);

        return $oAppData->reject();
    }

    function objectGetEntries($bQueued, $bRejected, $iRows = 0, $iStart = 0)
    {
        return appData::objectGetEntries($bQueued, $bRejected, $iRows, $iStart,
                                         "downloadurl");
    }

    function objectGetHeader()
    {
        return appData::objectGetHeader("downloadurl");
    }

    /* arg1 = OM object, arg2 = CSS style, arg3 = text for edit link */
    function objectOutputTableRow($oObject, $sClass, $sEditLinkLabel)
    {
        $oAppData = new AppData();
        $oAppData->objectOutputTableRow($oObject, $sClass, $sEditLinkLabel);
    }

    function getOutputEditorValues($aClean)
    {
        $this->sUrl = $aClean['sDownloadUrlUrl'];
        $this->sDescription = $aClean['sDownloadUrlDescription'];
    }

    function mustBeQueued()
    {
        if($this)
        {
            $oAppData = new appData();
            $oAppData->iVersionId = $this->iVersionId;
            $oAppData->iAppId = NULL;
            return $oAppData->mustBeQueued();
        } else
            return appData::mustBeQueued();
    }

    function allowAnonymousSubmissions()
    {
        return FALSE;
    }

    function objectMakeLink()
    {
        /* FIXME: not implemented */
        return TRUE;
    }

    function objectMakeUrl()
    {
        /* FIXME: not implemented */
        return TRUE;
    }
}

?>
