<?php
/************************************/
/* this class represents an version */
/************************************/

require_once(BASE."include/note.php");
require_once(BASE."include/comment.php");
require_once(BASE."include/url.php");
require_once(BASE."include/screenshot.php");
require_once(BASE."include/bugs.php");

/**
 * Version class for handling versions.
 */
class Version {
    var $iVersionId;
    var $iAppId;
    var $sName;
    var $sDescription;
    var $sTestedRelease;
    var $sTestedRating;
    var $sSubmitTime;
    var $iSubmitterId;
    var $sDate;
    var $sQueued;
    var $aNotesIds;           // an array that contains the noteId of every note linked to this version
    var $aCommentsIds;        // an array that contains the commentId of every comment linked to this version
    var $aScreenshotsIds;     // an array that contains the screenshotId of every screenshot linked to this version
    var $aUrlsIds;            // an array that contains the screenshotId of every url linked to this version
    var $aBuglinkIds;         // an array that contains the buglinkId of every bug linked to this version

    /**    
     * constructor, fetches the data.
     */
    function Version($iVersionId = null)
    {
        // we are working on an existing version
        if(is_numeric($iVersionId))
        {
            /*
             * We fetch the data related to this version.
             */
            if(!$this->versionId)
            {
                $sQuery = "SELECT *
                           FROM appVersion
                           WHERE versionId = ".$iVersionId;
                if($hResult = query_appdb($sQuery))
                {
                    $oRow = mysql_fetch_object($hResult);
                    $this->iVersionId = $iVersionId;
                    $this->iAppId = $oRow->appId;
                    $this->iCatId = $oRow->catId;
                    $this->iSubmitterId = $oRow->submitterId;
                    $this->sSubmitTime = $oRow->submitTime;
                    $this->sDate = $oRow->submitTime;
                    $this->sName = $oRow->versionName;
                    $this->sKeywords = $oRow->keywords;
                    $this->sDescription = $oRow->description;
                    $this->sTestedRelease = $oRow->maintainer_release;
                    $this->sTestedRating = $oRow->maintainer_rating;
                    $this->sWebpage = $oRow->webPage;
                    $this->sQueued = $oRow->queued;
                }
            }

            /*
             * We fetch notesIds. 
             */
            $this->aNotesIds = array();
            $sQuery = "SELECT noteId
                       FROM appNotes
                       WHERE versionId = ".$iVersionId;
            if($hResult = query_appdb($sQuery))
            {
                while($oRow = mysql_fetch_object($hResult))
                {
                    $this->aNotesIds[] = $oRow->noteId;
                }
            }

            /*
             * We fetch commentsIds. 
             */
            $this->aCommentsIds = array();
            $sQuery = "SELECT commentId
                       FROM appComments
                       WHERE versionId = ".$iVersionId;
            if($hResult = query_appdb($sQuery))
            {
                while($oRow = mysql_fetch_object($hResult))
                {
                    $this->aCommentsIds[] = $oRow->commentId;
                }
            }


            /*
             * We fetch screenshotsIds and urlsIds. 
             */
            $this->aScreenshotsIds = array();
            $this->aUrlsIds = array();
            $sQuery = "SELECT id, type
                       FROM appData
                       WHERE versionId = ".$iVersionId;

            if($hResult = query_appdb($sQuery))
            {
                while($oRow = mysql_fetch_object($hResult))
                {
                    if($oRow->type="image")
                        $this->aScreenshotsIds[] = $oRow->id;
                    else
                        $this->aUrlsIds[] = $oRow->id;
                }
            }

            /*
             * We fetch Bug linkIds. 
             */
            $this->aBuglinkIds = array();
            $sQuery = "SELECT *
                       FROM buglinks
                       WHERE versionId = ".$iVersionId."
                       ORDER BY bug_id";
            if($hResult = query_appdb($sQuery))
            {
                while($oRow = mysql_fetch_object($hResult))
                {
                    $this->aBuglinkIds[] = $oRow->linkId;
                }
            }
        }
    }


    /**
     * Creates a new version.
     */
    function create()
    {
        // Security, if we are not an administrator or an appmaintainer the version must be queued.
        if(!($_SESSION['current']->hasPriv("admin") || $_SESSION['current']->isSupermaintainer($iAppId)))
            $this->sQueued = 'true';
        else
            $this->sQueued = 'false';

        $aInsert = compile_insert_string(array( 'versionName'       => $this->sName,
                                                'description'       => $this->sDescription,
                                                'maintainer_release'=> $this->sTestedRelease,
                                                'maintainer_rating' => $this->sTestedRating,
                                                'appId'             => $this->iAppId,
                                                'submitterId'       => $_SESSION['current']->iUserId,
                                                'queued'            => $this->sQueued ));
        $sFields = "({$aInsert['FIELDS']})";
        $sValues = "({$aInsert['VALUES']})";

        if(query_appdb("INSERT INTO appVersion $sFields VALUES $sValues", "Error while creating a new version."))
        {
            $this->iVersionId = mysql_insert_id();
            $this->version($this->iVersionId);
            $this->SendNotificationMail();
            return true;
        }
        else
        {
            return false;
        }
    }


    /**
     * Update version.
     * FIXME: Use compile_update_string instead of addslashes.
     * Returns true on success and false on failure.
     */
    function update()
    {
        $sWhatChanged = "";

        $oVersion = new Version($this->iVersionId);

        if ($this->sName && ($this->sName!=$oVersion->sName))
        {
            $sUpdate = compile_update_string(array('versionName'    => $this->sName));
            if (!query_appdb("UPDATE appVersion SET ".$sUpdate." WHERE versionId = ".$this->iVersionId))
                return false;
            $sWhatChanged .= "Name was changed from:\n\t'".$oVersion->sName."'\nto:\n\t'".$this->sName."'\n\n";
        }     

        if ($this->sDescription && ($this->sDescription!=$oVersion->sDescription))
        {
            $sUpdate = compile_update_string(array('description'    => $this->sDescription));
            if (!query_appdb("UPDATE appVersion SET ".$sUpdate." WHERE versionId = ".$this->iVersionId))
                return false;

            if($oVersion->sDescription != "")
                $sWhatChanged .= "Description was changed from\n ".$oVersion->sDescription."\n to \n".$this->sDescription.".\n\n";
            else
                $sWhatChanged .= "Description was changed to \n".$this->sDescription.".\n\n";
        }

        if ($this->sTestedRelease && ($this->sTestedRelease!=$oVersion->sTestedRelease))
        {
            $sUpdate = compile_update_string(array('maintainer_release'    => $sTestedRelease));
            if (!query_appdb("UPDATE appVersion SET ".$sUpdate." WHERE versionId = ".$this->iVersionId))
                return false;

            if($oVersion->sTestedRelease != "")
                $sWhatChanged .= "Last tested release was changed from ".$oVersion->sTestedRelease." to ".$this->sTestedRelease.".\n\n";
            else
                $sWhatChanged .= "Last tested release was changed to ".$this->sTestedRelease.".\n\n";
        }

        if ($this->sTestedRating && ($this->sTestedRating!=$oVersion->sTestedRating))
        {
            $sUpdate = compile_update_string(array('maintainer_rating'    => $this->sTestedRating));
            if (!query_appdb("UPDATE appVersion SET ".$sUpdate." WHERE versionId = ".$this->iVersionId))
                return false;

            if($this->sTestedRating != "")
                $sWhatChanged .= "Rating was changed from ".$oVersion->sTestedRating." to ".$this->sTestedRating.".\n\n";
            else
                $sWhatChanged .= "Rating was changed to ".$this->sTestedRating.".\n\n";
        }
     
        if ($this->iAppId && ($this->iAppId!=$oVersion->iAppId))
        {
            $sUpdate = compile_update_string(array('appId'    => $this->iAppId));
            if (!query_appdb("UPDATE appVersion SET ".$sUpdate." WHERE versionId = ".$this->iVersionId))
                return false;
            $oAppBefore = new Application($oVersion->iAppId);
            $oAppAfter = new Application($this->iAppId);
            $sWhatChanged .= "Version was moved from application ".$oAppBefore->sName." to application ".$oAppAfter->sName.".\n\n";
        }

        if($sWhatChanged)
            $this->SendNotificationMail("edit",$sWhatChanged);
        return true;
    }


    /**    
     * Deletes the version from the database. 
     * and request the deletion of linked elements.
     */
    function delete($bSilent=false)
    {
        /* is the current user allowed to delete this version? */
        if(!$_SESSION['current']->hasPriv("admin") && 
           !$_SESSION['current']->hasAppVersionModifyPermission($this->iVersionId) &&
           !(($_SESSION['current']->iUserId == $this->iSubmitterId) && ($this->sQueued == 'rejected')))
        {
            return;
        }

        /* remove all of the items this version contains */
        foreach($this->aNotesIds as $iNoteId)
        {
            $oNote = new Note($iNoteId);
            $oNote->delete($bSilent);
        }
        foreach($this->aCommentsIds as $iCommentId)
        {
            $oComment = new Comment($iCommentId);
            $oComment->delete($bSilent);
        }
        foreach($this->aScreenshotsIds as $iScreenshotId)
        {
            $oScreenshot = new Screenshot($iScreenshotId);
            $oScreenshot->delete($bSilent);
        }
        foreach($this->aUrlsIds as $iUrlId)
        {
            $oUrl = new Url($iUrlId);
            $oUrl->delete($bSilent);
        }
        foreach($this->$aBuglinkIds as $iBug_id)
        {
            $oBug = new bug($iBug_id);
            $oBug->delete($bSilent);
        }

        // remove any maintainers for this version so we don't orphan them
        $sQuery = "DELETE from appMaintainers WHERE versionId='".$this->iVersionId."';";
        if(!($hResult = query_appdb($sQuery)))
        {
            addmsg("Error removing version maintainers for the deleted version!", "red");
        }

        /* now delete the version */
        $sQuery = "DELETE FROM appVersion 
                   WHERE versionId = ".$this->iVersionId." 
                   LIMIT 1";
        if(!($hResult = query_appdb($sQuery)))
        {
            addmsg("Error removing the deleted version!", "red");
        }

        if(!$bSilent)
            $this->SendNotificationMail("delete");

        $this->mailSubmitter("delete");
    }


    /**
     * Move version out of the queue.
     */
    function unQueue()
    {
        /* is the current user allowed to delete this version? */
        if(!$_SESSION['current']->hasPriv("admin") && !$_SESSION['current']->hasAppVersionModifyPermission($this->iVersionId))
        {
            return;
        }

        // If we are not in the queue, we can't move the version out of the queue.
        if(!$this->sQueued == 'true')
            return false;

        $sUpdate = compile_update_string(array('queued'    => "false"));
        if(query_appdb("UPDATE appVersion SET ".$sUpdate." WHERE versionId = ".$this->iVersionId))
        {
            $this->sQueued = 'false';
            // we send an e-mail to intersted people
            $this->mailSubmitter("unQueue");
            $this->SendNotificationMail();

            // the version has been unqueued
            addmsg("The version has been unqueued.", "green");
        }
    }

    function Reject($bSilent=false)
    {
        /* is the current user allowed to delete this version? */
        if(!$_SESSION['current']->hasPriv("admin") && !$_SESSION['current']->hasAppVersionModifyPermission($this->iVersionId))
        {
            return;
        }

        // If we are not in the queue, we can't move the version out of the queue.
        if(!$this->sQueued == 'true')
            return false;

        $sUpdate = compile_update_string(array('queued'    => "rejected"));
        if(query_appdb("UPDATE appVersion SET ".$sUpdate." WHERE versionId = ".$this->iVersionId))
        {
            $this->sQueued = 'rejected';
            // we send an e-mail to intersted people
            if(!$bSilent)
            {
                $this->mailSubmitter("reject");
                $this->SendNotificationMail("reject");
            }
            // the version has been unqueued
            addmsg("The version has been rejected.", "green");
        }
    }

    function ReQueue()
    {
        /* is the current user allowed to delete this version? */
        if(!$_SESSION['current']->hasPriv("admin") &&
           !$_SESSION['current']->hasAppVersionModifyPermission($this->iVersionId) &&
           !$_SESSION['current']->iUserId == $this->iSubmitterId)
        {
            return;
        }

        $sUpdate = compile_update_string(array('queued'    => "true"));
        if(query_appdb("UPDATE appVersion SET ".$sUpdate." WHERE versionId = ".$this->iVersionId))
        {
            $this->sQueued = 'true';
            // we send an e-mail to intersted people
            $this->SendNotificationMail();

            // the version has been unqueued
            addmsg("The version has been re-submitted", "green");
        }
    }

    function mailSubmitter($sAction="add")
    {
        if($this->iSubmitterId)
        {
            $oApp = new Application($this->appId);
            $oSubmitter = new User($this->iSubmitterId);
            switch($sAction)
            {
            case "add":
               {
                   $sSubject =  "Submitted version accepted";
                   $sMsg  = "The version you submitted (".$oApp->sName." ".$this->sName.") has been accepted.";
               }
            break;
            case "reject":
                {
                    $sSubject =  "Submitted version rejected";
                    $sMsg  = "The version you submitted (".$oApp->sName." ".$this->sName.") has been rejected. ";
                    $sMsg .= "Clicking on the link in this email will allow you to modify and resubmit the version. ";
                    $sMsg .= "A link to your queue of applications and versions will also show up on the left hand side of the Appdb site once you have logged in. ";
                    $sMsg .= APPDB_ROOT."admin/resubmitRejectedApps.php?sub=view&versionId=".$this->iVersionId."\n";
                    $sMsg .= "Reason given:\n";
                    $sMsg .= $_REQUEST['replyText']."\n"; /* append the reply text, if there is any */
                }

            break;
            case "delete":
                {
                    $sSubject =  "Submitted version deleted";
                    $sMsg  = "The version you submitted (".$oApp->sName." ".$this->sName.") has been deleted.";
                    $sMsg .= "Reason given:\n";
                    $sMsg .= $_REQUEST['replyText']."\n"; /* append the reply text, if there is any */
                }
            break;
            }
            $sMsg .= $_REQUEST['replyText']."\n";
            $sMsg .= "We appreciate your help in making the Version Database better for all users.";
        
            mail_appdb($oSubmitter->sEmail, $sSubject ,$sMsg);
        }
    }

 
    function SendNotificationMail($sAction="add",$sMsg=null)
    {
        $oApp = new Application($this->iAppId);
        switch($sAction)
        {
            case "add":
                if($this->sQueued == "false")
                {
                    $sSubject = "Version ".$this->sName." of ".$oApp->sName." added by ".$_SESSION['current']->sRealname;
                    $sMsg  = APPDB_ROOT."appview.php?versionId=".$this->iVersionId."\n";
                    if($this->iSubmitterId)
                    {
                        $oSubmitter = new User($this->iSubmitterId);
                        $sMsg .= "This version has been submitted by ".$oSubmitter->sRealname.".";
                        $sMsg .= "\n";
                        $sMsg .= "Appdb admin reply text:\n";
                        $sMsg .= $_REQUEST['replyText']."\n"; /* append the reply text, if there is any */
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
                $sMsg  = APPDB_ROOT."appview.php?versionId=".$this->iVersionId."\n";
                addmsg("Version modified.", "green");
            break;
            case "delete":
                $sSubject = "Version '".$this->sName."' of '".$oApp->sName."' has been deleted by ".$_SESSION['current']->sRealname;

                /* if replyText is set we should report the reason the application was deleted */
                if($_REQUEST['replyText'])
                {
                    $sMsg .= "Reason given:\n";
                    $sMsg .= $_REQUEST['replyText']."\n"; /* append the reply text, if there is any */
                }

                addmsg("Version deleted.", "green");
            break;
            case "reject":
                $sSubject = "Version '".$this->sName."' of '".$oApp->sName."' has been rejected by ".$_SESSION['current']->sRealname;
                $sMsg .= APPDB_ROOT."admin/resubmitRejectedApps.php?sub=view&versionId=".$this->iVersionId."\n";

                 /* if replyText is set we should report the reason the application was rejected */
                if($_REQUEST['replyText'])
                {
                    $sMsg .= "Reason given:\n";
                    $sMsg .= $_REQUEST['replyText']."\n"; /* append the reply text, if there is any */
                }

                addmsg("Version rejected.", "green");
            break;
        }
        $sEmail = get_notify_email_address_list(null, $this->iVersionId);
        if($sEmail)
            mail_appdb($sEmail, $sSubject ,$sMsg);
    } 

    /* output html and the current versions information for editing */
    /* if $editParentApplication is true that means we need to display fields */
    /* to let the user change the parent application of this version */
    /* otherwise, if $editParentAppliation is false, we leave them out */
    function OutputEditor($editParentApplication, $editRatingAndRelease)
    {
        HtmlAreaLoaderScript(array("version_editor"));
        echo html_frame_start("Version Form", "90%", "", 0);
        echo "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";

        echo '<input type="hidden" name="versionId" value='.$this->iVersionId.' />';

        if($editParentApplication)
        {
            // app parent
            $x = new TableVE("view");
            echo '<tr valign=top><td class=color0><b>Application</b></td>', "\n";
            echo '<td>',"\n";
            $x->make_option_list("appId",$this->iAppId,"appFamily","appId","appName");
            echo '</td></tr>',"\n";
        } else
        {
            echo '<input type="hidden" name="appId" value='.$this->iAppId.' />';
        }

        // version name
        echo '<tr valign=top><td class="color0"><b>Version name</b></td>',"\n";
        echo '<td><input size="20" type="text" name="versionName" value="'.$this->sName.'"></td></tr>',"\n";

        // version description
        echo '<tr valign=top><td class=color0><b>Version description</b></td>',"\n";
        echo '<td><p><textarea cols="80" rows="20" id="version_editor" name="versionDescription">',"\n";

        /* if magic quotes are enabled we need to strip them before we output the 'versionDescription' */
        /* again.  Otherwise we will stack up magic quotes each time the user resubmits after having */
        /* an error */
        if(get_magic_quotes_gpc())
            echo stripslashes($this->sDescription).'</textarea></p></td></tr>',"\n";
        else
            echo $this->sDescription.'</textarea></p></td></tr>',"\n";

        echo '</table>',"\n";

        echo html_frame_end();

        if($editRatingAndRelease)
        {
            echo html_frame_start("Info", "90%", "", 0);
            echo "<table border=0 cellpadding=2 cellspacing=0>\n";
            echo '<tr><td class="color4">Rating</td><td class="color0">',"\n";
            make_maintainer_rating_list("maintainer_rating", $this->sTestedRating);
            echo '</td></tr>',"\n";
            echo '<tr><td class=color1>Release</td><td class=color0>',"\n";
            make_bugzilla_version_list("maintainer_release", $this->sTestedRelease);
            echo '</td></tr>',"\n";
            echo html_table_end();
            echo html_frame_end();
        } else
        {
            echo '<input type="hidden" name="maintainer_rating" value='.$this->sTestedRating.' />';
            echo '<input type="hidden" name="maintainer_release" value='.$this->sTestedRelease.' />';
        }
    }

    function CheckOutputEditorInput()
    {
        $errors = "";

        if (empty($_REQUEST['versionName']))
            $errors .= "<li>Please enter an application version.</li>\n";

        if (empty($_REQUEST['versionDescription']))
            $errors .= "<li>Please enter a version description.</li>\n";

        return $errors;
    }

    /* retrieves values from $_REQUEST that were output by OutputEditor() */
    function GetOutputEditorValues()
    {
        if(get_magic_quotes_gpc())
        {
            $this->iAppId = stripslashes($_REQUEST['appId']);
            $this->iVersionId = stripslashes($_REQUEST['versionId']);
            $this->sName = stripslashes($_REQUEST['versionName']);
            $this->sDescription = stripslashes($_REQUEST['versionDescription']);

            $this->sTestedRating = stripslashes($_REQUEST['maintainer_rating']);
            $this->sTestedRelease = stripslashes($_REQUEST['maintainer_release']);
        } else
        {
            $this->iAppId = $_REQUEST['appId'];
            $this->iVersionId = $_REQUEST['versionId'];
            $this->sName = $_REQUEST['versionName'];
            $this->sDescription = $_REQUEST['versionDescription'];

            $this->sTestedRating = $_REQUEST['maintainer_rating'];
            $this->sTestedRelease = $_REQUEST['maintainer_release'];
        }
    }
}

function GetDefaultVersionDescription()
{
    return "<p>This is a template; enter version-specific description here</p>
                            <p>
                               <span class=\"title\">Wine compatibility</span><br />
                               <span class=\"subtitle\">What works:</span><br />
                               - settings<br />
                               - help<br />
                               <br /><span class=\"subtitle\">What doesn't work:</span><br />
                               - erasing<br />
                               <br /><span class=\"subtitle\">What was not tested:</span><br />
                               - burning<br />
                               </p>
                               <p><span class=\"title\">Tested versions</span><br /><table class=\"historyTable\" width=\"90%\" border=\"1\">
                            <thead class=\"historyHeader\"><tr>
                            <td>App. version</td><td>Wine version</td><td>Installs?</td><td>Runs?</td><td>Rating</td>
                            </tr></thead>
                            <tbody><tr>
                            <td class=\"gold\">3.23</td><td class=\"gold\">20050111</td><td class=\"gold\">yes</td><td class=\"gold\">yes</td><td class=\"gold\">Gold</td>
                            </tr><tr>
                            <td class=\"silver\">3.23</td><td class=\"silver\">20041201</td><td class=\"silver\">yes</td><td class=\"silver\">yes</td><td class=\"silver\">Silver</td>
                            </tr><tr>
                            <td class=\"bronze\">3.21</td><td class=\"bronze\">20040615</td><td class=\"bronze\">yes</td><td class=\"bronze\">yes</td><td class=\"bronze\">Bronze</td>
                            </tr></tbody></table></p><p><br /></p>";
}
?>
