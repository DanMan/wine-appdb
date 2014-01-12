<?php
/***************************************/
/* comment class and related functions */
/***************************************/
require_once(BASE."include/user.php");

/**
 * Comment class for handling comments
 */
class Comment {
    var $iCommentId;

    // variables necessary for creating a comment
    var $iParentId;
    var $sSubject;
    var $sBody;
    var $iVersionId;


    var $iAppId;
    var $sDateCreated;
    var $oOwner;


    /**
     * Constructor.
     * If $iCommentId is provided, fetches comment.
     */
    function Comment($iCommentId = null, $oRow = null)
    {
        if(!$iCommentId && !$oRow)
            return;

        if(!$oRow)
        {
            $sQuery = "SELECT appComments.*, appVersion.appId AS appId
                       FROM appComments, appVersion
                       WHERE appComments.versionId = appVersion.versionId 
                       AND commentId = '?'";
            $hResult = query_parameters($sQuery, $iCommentId);
            $oRow = query_fetch_object($hResult);
        }

        if($oRow)
        {
            $this->iCommentId = $oRow->commentId;
            $this->iParentId = $oRow->parentId;

            $oVersion = new version($this->iVersionId);
            $this->iAppId = $oVersion->iAppId;

            $this->iVersionId = $oRow->versionId;
            $this->sSubject = $oRow->subject;
            $this->sBody = $oRow->body;
            $this->sDateCreated = $oRow->time;
            $this->oOwner = new User($oRow->userId);
        }
    }


    /*
     * Creates a new comment.
     * Informs interested people about the creation.
     * Returns true on success, false on failure
     */
    function create()
    {
        $hResult = query_parameters("INSERT INTO appComments
                (parentId, versionId, subject, ".
                                    "body, userId, time)
                VALUES ('?', '?', '?', '?', '?', ?)",
                                    $this->iParentId, $this->iVersionId,
                                    $this->sSubject, $this->sBody,
                                    $_SESSION['current']->iUserId,
                                    "NOW()");

        if($hResult)
        {
            $this->comment(query_appdb_insert_id());
            $sEmail = User::get_notify_email_address_list($this->iAppId, $this->iVersionId);
            $sEmail .= $this->oOwner->sEmail." ";

            // fetches e-mails from parent comments, all parents are notified that a
            // comment was added to the thread
            $iParentId = $this->iParentId;
            while($iParentId)
            {
                $oParent = new Comment($iParentId);
                $sEmail .= $oParent->oOwner->sEmail." ";
                $iParentId = $oParent->iParentId;
            }
            if($sEmail)
            {
                $aEmailArray = explode(" ", $sEmail);      /* break the long email string into parts by spaces */
                $aEmailArray = array_unique($aEmailArray); /* remove duplicates */

                /* build the single string of all emails up */
                $sEmail = "";
                foreach($aEmailArray as $key=>$value)
                {
                    $sEmail.="$value ";
                }

                $sSubject = "Comment for '".Application::lookup_name($this->iAppId)." ".Version::lookup_name($this->iVersionId)."' added by ".$_SESSION['current']->sRealname;
                $sMsg  = "To reply to this email please use the link provided below.\n";
                $sMsg .= "DO NOT reply via your email client as it will not reach the person who wrote the comment\n";
                $sMsg .= $this->objectMakeUrl()."\n";
                $sMsg .= "\n";
                $sMsg .= "Subject: ".$this->sSubject."\r\n";
                $sMsg .= "\n";
                $sMsg .= $this->sBody."\r\n";
                mail_appdb($sEmail, $sSubject ,$sMsg);
            } 
            addmsg("Comment created.", "green");
            return true;
        }
        else
        {
            addmsg("Error while creating a new comment", "red");
            return false;
        }
    }


    /**
     * Update comment.
     * FIXME: Informs interested people about the modification.
     * Returns true on success and false on failure.
     */
    function update($sSubject=null, $sBody=null, $iParentId=null, $iVersionId=null)
    {
        $oComment = new comment($this->iCommentId);

        if(!$iVersionId && $this->iVersionId != $oComment->iVersionId)
            $iVersionId = $this->iVersionId;
        if(!$iParentId && $this->iParentId != $oComment->iParentId)
            $iParentId = $this->iParentId;

        if ($iParentId)
        {
            if (!query_parameters("UPDATE appComments SET parentId = '?' WHERE commentId = '?'",
                                  $iParentId, $this->iCommentId))
                return false;
            $this->iParentId = $iParentId;
        }

        if ($iVersionId)
        {
            if (!query_parameters("UPDATE appComments SET versionId = '?' WHERE commentId = '?'",
                                  $iVersionId, $this->iCommentId))
                return false;
            $this->iVersionId = $iVersionId;
            // FIXME: we need to refetch $this->iAppId.
        }

        if ($sSubject)
        {
            if (!query_parameters("UPDATE appComments SET subject = '?' WHERE commentId = '?'",
                                  $sSubject, $this->iCommentId))
                return false;
            $this->sSubject = $sSubject;
        }

        if ($sBody)
        {
            if (!query_parameters("UPDATE appComments SET body = '?' WHERE commentId = '?'",
                                  $sBody, $this->iCommentId))
                return false;
            $this->sBody = $sBody;
        }
        return true;
    }

    function purge()
    {
        return $this->delete();
    }

    /**
     * Removes the current comment from the database.
     * Informs interested people about the deletion.
     * Returns true on success and false on failure.
     */
    function delete()
    {
        $hResult = query_parameters("DELETE FROM appComments WHERE commentId = '?'", $this->iCommentId);
        if ($hResult)
        {
            $aChildren = $this->objectGetChildren();

            foreach($aChildren as $oComment)
                $oComment->delete();

            return true;
        }

        return false;
    }

    public static function get_comment_count_for_versionid($iVersionId)
    {
        $sQuery = "SELECT count(*) as cnt from appComments where versionId = '?'";
        $hResult = query_parameters($sQuery, $iVersionId);
        if(!$hResult) return 0;
        
        $oRow = query_fetch_object($hResult);
        return $oRow->cnt;
    }

    function getOutputEditorValues($aClean)
    {
        $this->sSubject = $aClean['sSubject'];
        $this->sBody = $aClean['sBody'];
        $this->iParentId = $aClean['iThread'];

        if($aClean['iVersionId'])
            $this->iVersionId = $aClean['iVersionId'];

        if(!$this->oOwner)
            $this->oOwner = $_SESSION['current'];

        if(!$this->sDateCreated)
            $this->sDateCreated = date("l F jS Y, H:i");
    }

    /**
     * Displays the body of one comment.
     */
    public static function view_comment_body($iCommentId)
    {
        $hResult = Comment::grab_comment($iCommentId);

        if ($hResult)
        {
            $oRow = query_fetch_object($hResult);
            Comment::view_app_comment($oRow);
        }     
    }

    /**
     * display a single comment (in $oRow)
     */
    public static function view_app_comment($oRow, $bShowAppName = false)
    {
        $oComment = new comment(null, $oRow);
        $oComment->output_comment($bShowAppName);
    }

    private function output_comment($bShowAppName = false)
    {
        echo html_frame_start('','98%');
        echo '<table width="100%" border="0" cellpadding="2" cellspacing="1">',"\n";

        // message header
        echo "<tr bgcolor=\"#E0E0E0\"><td><a name=Comment-".$this->iCommentId."></a>\n";
        echo " <b>".$this->sSubject."</b><br>\n";

        if($bShowAppName)
        {
            $oVersion = new version($this->iVersionId);
            $sMaintainerText = $oVersion->bHasMaintainer ? 'has maintainer' : 'no maintainers';
            echo 'Application: ' . version::fullNameLink($this->iVersionId);
            echo " ($sMaintainerText)<br>\n";
        }

        echo " by  ".forum_lookup_user($this->oOwner->iUserId)." on ".$this->sDateCreated."<br>\n";
        echo "</td></tr><tr><td>\n";
    
        // body
        echo htmlify_urls($this->sBody), "<br><br>\n";
    
        $oVersion = new version($this->iVersionId);
        $oM = new objectManager("comment", "Post new comment");
        $oM->setReturnTo($oVersion->objectMakeUrl());
        // reply post buttons
        echo " [<a href=\"".$oM->makeUrl("add")."&iVersionId=$this->iVersionId\"><small>post new</small></a>] \n";
        echo " [<a href=\"".$oM->makeUrl("add")."&iVersionId=$this->iVersionId".
                "&iThread=$this->iCommentId\"><small>reply to this</small></a>] \n";

        echo "</td></tr>\n";

        // delete message button, for admins
        if ($this->canEdit())
        {
            echo "<tr>";
            echo "<td><form method=\"post\" name=\"sMessage\" action=\"".BASE."objectManager.php\"><input type=\"submit\" value=\"Delete\" class=\"button\">\n";
            echo "<input type=\"hidden\" name=\"iId\" value=\"$this->iCommentId\">";
            echo "<input type=\"hidden\" name=\"sClass\" value=\"comment\">";
            echo "<input type=\"hidden\" name=\"bQueued\" value=\"false\">";
            echo "<input type=\"hidden\" name=\"sAction\" value=\"delete\">";
            echo "<input type=\"hidden\" name=\"sTitle\" value=\"Delete comment\">";
            echo "<input type=\"hidden\" name=\"sReturnTo\" value=\"".$oVersion->objectMakeUrl()."\">";
            echo "</form>\n";
            echo "</td></tr>";
        }

        echo "</table>\n";

        echo html_frame_end();   
    }

    public function objectWantCustomDraw($sWhat, $sQueued)
    {
        switch($sWhat)
        {
            case 'table':
                return true;
        }

        return false;
    }

    public function objectGetFilterInfo()
    {
        $oFilter = new FilterInterface();
        $oFilter->AddFilterInfo('onlyWithoutMaintainers', 'Only show comments for versions without maintainers', array(FILTER_OPTION_BOOL), FILTER_VALUES_OPTION_BOOL, array('false','true'));
        return $oFilter;
    }


    public static function objectGetEntries($sState, $iNumRows = 0, $iStart = 0, $sOrderBy = 'commentId', $bAscending = true, $oFilters = null)
    {
        $sExtraTables = '';
        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyWithoutMaintainers' => 'false');
        $sWhereFilter = '';

        if($aOptions['onlyWithoutMaintainers'] == 'true')
        {
            $sExtraTables = ',appVersion';

            $sWhereFilter .= " WHERE appVersion.hasMaintainer = 'false' AND appVersion.versionId = appComments.versionId";
        }

        $sLimit = '';

        if($iNumRows)
        {
            $iStart = mysql_real_escape_string($iStart);
            $iNumRows = mysql_real_escape_string($iNumRows);
            $sLimit = " LIMIT $iStart,$iNumRows";
        }

        if($sOrderBy)
            $sOrderBy = " ORDER BY ".mysql_real_escape_string($sOrderBy);

        $hResult = query_parameters("SELECT * FROM appComments$sExtraTables$sWhereFilter$sOrderBy$sLimit");

        return $hResult;
    }

    public function objectGetDefaultSort()
    {
        return 'commentId';
    }

    public static function objectGetEntriesCount($sState, $oFilters = null)
    {
        $sExtraTables = '';
        $aOptions = $oFilters ? $oFilters->getOptions() : array('onlyWithoutMaintainers' => 'false');
        $sWhereFilter = '';

        if($aOptions['onlyWithoutMaintainers'] == 'true')
        {
            $sExtraTables = ',appVersion';

            $sWhereFilter .= " WHERE appVersion.hasMaintainer = 'false' AND appVersion.versionId = appComments.versionId";
        }

        $hResult = query_parameters("SELECT COUNT(commentId) as count FROM appComments$sExtraTables$sWhereFilter");

        if(!$hResult)
            return null;

        $oRow = mysql_fetch_object($hResult);

        return $oRow->count;
    }

    public function objectGetItemsPerPage()
    {
        $aItemsPerPage = array(10, 20, 50, 100, 500);
        $iDefaultPerPage = 10;

        return array($aItemsPerPage, $iDefaultPerPage);
    }

    public function objectDrawCustomTable($hResult, $sQueued)
    {
        while($oRow = mysql_fetch_object($hResult))
            comment::view_app_comment($oRow, true);
    }

    public function objectGetHeader()
    {
        return new TableRow();
    }

    public function objectGetTableRow()
    {
        $oTableRow = new TableRow();
        $oOMRow = new OMTableRow($oTableRow);

        return $oOMRow;
    }

    function display()
    {
        $this->output_comment();
    }

    /**
     * grab single comment for commentId
     */
    public static function grab_comment($iCommentId)
    {
        $iCommentId = query_escape_string($iCommentId);

        if($iCommentId)
        {
          $sQuery = "SELECT from_unixtime(unix_timestamp(appComments.time), \"%W %M %D %Y, %k:%i\") as time, ".
              "appComments.commentId, appComments.parentId, appComments.versionId, appComments.userId, appComments.subject, appComments.body, appVersion.appId ".
              "FROM appComments, appVersion WHERE appComments.commentId = '$iCommentId'";

          $hResult = query_appdb($sQuery);

          return $hResult;
        } 

        return null;
    }

    /**
     * grab comments for appId / versionId
     * if parentId is not -1 only comments for that thread are returned
     */
    public static function grab_comments($iVersionId, $iParentId = null)
    {
        /* TODO: remove the logging when we figure out where the */
        /* invalid $iVersionId is coming */
        /* if $iVersionId is invalid we should log where we came from */
        /* so we can debug the problem */
        if($iVersionId == "")
        {
            error_log::logBackTrace("logging iVersionId oddity");
            return NULL;
        }

        /* escape input so we can use query_appdb() without concern */
        $iVersionId = query_escape_string($iVersionId);
        $iParentId = query_escape_string($iParentId);

        /* NOTE: we must compare against NULL here because $iParentId of 0 is valid */
        if($iParentId)
        {
            $sExtra = "AND parentId = '".$iParentId."' ";
            $sOrderingMode = "ASC";
        } else
        {
            $sExtra = "AND parentId = '0'";
            $sOrderingMode = "DESC";
        }

        $sQuery = "SELECT from_unixtime(unix_timestamp(appComments.time), \"%W %M %D %Y, %k:%i\") as time, ".
            "appComments.commentId, appComments.parentId, appComments.versionId, appComments.userId, appComments.subject, appComments.body, appVersion.appId ".
            "FROM appComments, appVersion WHERE appComments.versionId = appVersion.versionId AND appComments.versionId = '".$iVersionId."' ".
            $sExtra.
            "ORDER BY appComments.time $sOrderingMode";

        $hResult = query_appdb($sQuery);

        return $hResult;
    }

    /**
     * display nested comments
     * handle is a db result set
     */
    public static function do_display_comments_nested($hResult)
    {
        while($oRow = query_fetch_object($hResult))
        {
            Comment::view_app_comment($oRow);
            $hResult2 = Comment::grab_comments($oRow->versionId, $oRow->commentId);
            if($hResult && query_num_rows($hResult2))
            {
                echo "<blockquote>\n";
                Comment::do_display_comments_nested($hResult2);
                echo "</blockquote>\n";
            }
        }
    }

    public static function display_comments_nested($versionId, $threadId)
    {
        $hResult = Comment::grab_comments($versionId, $threadId);
        Comment::do_display_comments_nested($hResult);
    }

    /**
     * Generates the link to show the comment.
     */
    public static function comment_link($oRow)
    {
        $sLink = "commentview.php?iAppId={$oRow->appId}&iVersionId=".
            "{$oRow->versionId}&iThreadId={$oRow->parentId}";

        $sOnClick = "showComment('{$oRow->commentId}');";

        /**
         * The return false line in the onClick is used to handle javascript 
         * being disabled so we can fail gracefully to the old style.
         */
        return "<li><a href=\"$sLink\" onclick=\"$sOnClick return false;\">$oRow->subject</a>". 
            ' by '.forum_lookup_user($oRow->userId)." on 
             {$oRow->time}<div id=\"{$oRow->commentId}\"></div></li>\n";
    }

    /**
     * display threaded comments
     * handle is a db result set
     */
    public static function do_display_comments_threaded($hResult, $is_main)
    {
        if (!$is_main)
            echo "<ul>\n";

        while ($oRow = query_fetch_object($hResult))
        {
            if ($is_main)
            {
                Comment::view_app_comment($oRow);
            } else
            {
               $link = Comment::comment_link($oRow);
               echo "$link";
            }

            $hResult2 = Comment::grab_comments($oRow->versionId, $oRow->commentId);
            if ($hResult2 && query_num_rows($hResult2))
            { 
                echo "<blockquote>\n";
                Comment::do_display_comments_threaded($hResult2, 0);
                echo "</blockquote>\n";
            }
        }

        if (!$is_main)
            echo "</ul>\n";
    }

    function canEdit()
    {
        if($_SESSION['current']->hasPriv("admin"))
            return TRUE;

        $oVersion = new version($this->iVersionId);
        return $oVersion->canEdit();
    }

    function objectGetId()
    {
        return $this->iCommentId;
    }

    function objectGetSubmitterId()
    {
        return $this->oOwner->iUserId;
    }

    function objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction)
    {
        $oOptions = new mailOptions();

        if($sAction == "delete" && $bParentAction)
            $oOptions->bMailOnce = TRUE;

        return $oOptions;
    }

    function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        $sSubject = "";
        $sMessage = "";
        $aRecipients = null;

        $oVersion = new version($this->iVersionId);
        $sVerName = version::fullName($this->iVersionId);

        if($bMailSubmitter)
        {
            switch($sAction)
            {
                case "delete":
                    if($bParentAction)
                    {
                        $sSubject = "Comments for $sVerName deleted";
                        $sMessage = "Your comments for $sVerName were deleted because the";
                        $sMessage .= "version was removed from the database";
                    } else
                    {
                        $sSubject = "Comment for $sVerName deleted";
                        $sMessage  = $oVersion->objectMakeUrl()."\n";
                        $sMessage .= "\n";
                        $sMessage .= "This comment was made on ".substr($this->sDateCreated,0,10)."\n";
                        $sMessage .= "\n";
                        $sMessage .= "Subject: ".$this->sSubject."\r\n";
                        $sMessage .= "\n";
                        $sMessage .= $this->sBody."\r\n";
                    }
                break;
            }
        } else
        {
            switch($sAction)
            {
                case "delete":
                    if(!$bParentAction)
                    {
                        $sSubject = "Comment for $sVerName deleted";
                        $sMessage  = $oVersion->objectMakeUrl()."\n";
                        $sMessage .= "\n";
                        $sMessage .= "This comment was made on ".substr($this->sDateCreated,0,10)." by ".$this->oOwner->sRealname."\n";
                        $sMessage .= "\n";
                        $sMessage .= "Subject: ".$this->sSubject."\r\n";
                        $sMessage .= "\n";
                        $sMessage .= $this->sBody."\r\n";
                    }
                    break;
            }
            $aRecipients = User::get_notify_email_address_list($this->iAppId, $this->iVersionId);
        }
        return array($sSubject, $sMessage, $aRecipients);
    }

    public function objectGetParent($sClass = '')
    {
        switch($sClass)
        {
            case 'version':
                return new version($this->iVersionId);

            case 'comment':
                return new comment($this->iParentId);
        }
    }

    public function objectSetParent($iNewId, $sClass = '')
    {
        switch($sClass)
        {
            case 'version':
                $this->iVersionId = $iNewId;
                break;

            case 'comment':
                $this->iParentId = $iNewId;
                break;
        }
    }

    function objectGetChildren($bIncludeDeleted = false)
    {
        $aObjects = array();
        $hResult = comment::grab_comments($this->iVersionId, $this->iCommentId);

        if(!$hResult)
            return $aObjects;

        while($oRow = mysql_fetch_object($hResult))
        {
            $oComment = new comment(null, $oRow);
            $aObjects += $oComment->objectGetChildren();
            $aObjects[] = $oComment;
        }

        return $aObjects;
    }

    public static function display_comments_threaded($versionId, $threadId = 0)
    {
        $hResult = Comment::grab_comments($versionId, $threadId);

        Comment::do_display_comments_threaded($hResult, 1);
    }

    /**
     * display flat comments
     */
    public static function display_comments_flat($versionId)
    {
        $hResult = Comment::grab_comments($versionId);
        if ($hResult)
        {
            while($oRow = query_fetch_object($hResult))
            {
                Comment::view_app_comment($oRow);
            }
        }
    }

    public static function view_app_comments($versionId, $threadId = 0)
    {
        global $aClean;

        // count posts
        $hResult = query_parameters("SELECT commentId FROM appComments WHERE versionId = '?'", $versionId);
        $messageCount = query_num_rows($hResult);
    
        //start comment format table
        echo html_frame_start("","98%",'',0);
        echo '<table width="100%" border="0" cellpadding="1" cellspacing="0">',"\n";
    
        echo '<tr><td bgcolor="#C0C0C0" align="center"><table border="0" cellpadding="0" cellspacing="0"><tr bgcolor="#C0C0C0">',"\n";

        $oVersion = new version($versionId);

        // message display mode changer
        if ($_SESSION['current']->isLoggedIn())
        {
            // FIXME we need to change this so not logged in users can change current view as well
            if (!empty($aClean['sCmode']))
                $_SESSION['current']->setPref("comments:mode", $aClean['sCmode']);

            $sel[$_SESSION['current']->getPref("comments:mode", "threaded")] = 'selected';
            echo '<td><form method="post" name="sMode" action="'.
                    $oVersion->objectMakeUrl().'">',"\n";
            echo "<b>Application comments:</b> $messageCount total comments ";
            echo '<b>Mode:</b> <select name="sCmode" onchange="document.sMode.submit();">',"\n";
            echo '   <option value="flat" '.$sel['flat'].'>Flat</option>',"\n";
            echo '   <option value="threaded" '.$sel['threaded'].'>Threaded</option>',"\n";
            echo '   <option value="nested" '.$sel['nested'].'>Nested</option>',"\n";
            echo '   <option value="off" '.$sel['off'].'>No Comments</option>',"\n";
            echo '</select>',"\n";
            echo '</form></td>',"\n";
        }
    
        // blank space
        echo '<td> &nbsp; </td>',"\n";

        $oM = new objectManager("comment", "Add comment");
        $oM->setReturnTo($oVersion->objectMakeUrl());

        // post new message button
        echo '<td><form method="post" name="sMessage" action="objectManager.php">';
        echo '<input type="hidden" name="sAction" value="add">';
        echo $oM->makeUrlFormData();
        echo '<input type="submit" value="Post new comment" class="button"> ',"\n";
        echo '<input type="hidden" name="iVersionId" value="'.$versionId.'"></form></td>',"\n";
        
        //end comment format table
        echo '</tr></table></td></tr>',"\n";  
        echo '</table>',"\n";
        echo html_frame_end();

        if( $messageCount > 0 )
        {
            echo '<p align="center">The following comments are owned by whoever posted them. WineHQ is not responsible for what they say.</p>'."\n";
        }

        //start comments
        echo '<table width="100%" border="0" cellpadding="2" cellspacing="1"><tr><td>',"\n";
    
        //hide or display depending on pref
        if ($_SESSION['current']->isLoggedIn())
            $mode = $_SESSION['current']->getPref("comments:mode", "threaded");
        else
            $mode = "threaded"; /* default non-logged in users to threaded comment display mode */

        if ( isset($aClean['sMode']) && $aClean['sMode']=="nested")
            $mode = "nested";

        switch ($mode)
        {
        case "flat":
            Comment::display_comments_flat($versionId);
            break;
        case "nested":
            Comment::display_comments_nested($versionId, $threadId);
            break;
        case "threaded":
            Comment::display_comments_threaded($versionId, $threadId);
            break;
        }

        echo '</td></tr></table>',"\n";
    }

    function allowAnonymousSubmissions()
    {
        return FALSE;
    }

    function objectGetCustomVars($sAction)
    {
        switch($sAction)
        {
            case "add":
                return array("iThread", "iVersionId");

            default:
                return null;
        }
    }

    function checkOutputEditorInput($aClean)
    {
        $sErrors = "";

        if(!$aClean['iVersionId'])
            $sErrors .= "<li>No version id defined; something may have gone wrong with the URL</li>\n";

        if(!$aClean['sBody'])
            $sErrors .= "<li>You need to enter a message!</li>\n";

        return $sErrors;
    }

    function outputEditor($aClean)
    {
        $sMesTitle = "<b>Post New Comment</b>";

        if($aClean['iThread'] > 0)
        {
            $hResult = query_parameters("SELECT * FROM appComments WHERE commentId = '?'",
                                    $aClean['iThread']);
            $oRow = query_fetch_object($hResult);
            if($oRow)
            {
                $sMesTitle = "<b>Replying To ...</b> $oRow->subject\n";
                echo html_frame_start($oRow->subject,500);
                echo htmlify_urls($oRow->body), "<br><br>\n";
                echo html_frame_end();

                /* Set default reply subject */
                if(!$this->sSubject)
                {
                    // Only add RE: once
                    if(stripos($oRow->subject, "RE:") !== false)
                        $this->sSubject = $oRow->subject;
                    else
                        $this->sSubject = "RE: ".$oRow->subject;
                }

            }
        }

        echo "<p align=\"center\">Enter your comment in the box below.";
        echo "</br>Please do not paste large terminal or debug outputs here.</p>";

        echo html_frame_start($sMesTitle,500,"",0);

        echo '<table width="100%" border=0 cellpadding=0 cellspacing=1>',"\n";
        echo "<tr class=\"color0\"><td align=right><b>From:</b>&nbsp;</td>\n";
        echo "	<td>&nbsp;".$_SESSION['current']->sRealname."</td></tr>\n";
        echo "<tr class=\"color0\"><td align=right><b>Subject:</b>&nbsp;</td>\n";
        echo "	<td>&nbsp;<input type=\"text\" size=\"35\" name=\"sSubject\" value=\"".$this->sSubject."\"> </td></tr>\n";
        echo "<tr class=\"color1\"><td colspan=2><textarea name=\"sBody\" cols=\"70\" rows=\"15\" wrap=\"virtual\">".$this->sBody."</textarea></td></tr>\n";

        echo "</table>\n";

        echo html_frame_end();

        echo "<input type=\"hidden\" name=\"iThread\" value=\"".$aClean['iThread']."\">\n";
        echo "<input type=\"hidden\" name=\"iVersionId\" value=\"".$aClean['iVersionId']."\">\n";
    }

    function objectShowPreview()
    {
        return TRUE;
    }

    function objectMakeUrl()
    {
        $oVersion = new version($this->iVersionId);
        $sUrl = $oVersion->objectMakeUrl()."#Comment-".$this->iCommentId;
        return $sUrl;
    }
}


/*
 * Comment functions that are not part of the class
 */

function forum_lookup_user($iUserId)
{
    if ($iUserId > 0)
    {
        $oUser = new User($iUserId);
        if($_SESSION['current']->isLoggedIn())
            $sMailto = '<a href="'.BASE.'contact.php?iRecipientId='.
                    $oUser->iUserId.'">' .$oUser->sRealname . '</a>';
        else
            $sMailto = $oUser->sRealname;
    }
    if (!$iUserId || !$oUser->isLoggedIn())
    {
        $sMailto = 'Anonymous';
    }
    return $sMailto;
}

?>
