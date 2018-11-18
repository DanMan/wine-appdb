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
    function __construct($iCommentId = null, $oRow = null)
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
            Comment::__construct(query_appdb_insert_id());
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
    public static function view_comment_body($iCommentId, $bShowAppName = false, $bShowForm = true)
    {
        $hResult = Comment::grab_comment($iCommentId);

        if ($hResult)
        {
            $oRow = query_fetch_object($hResult);
            Comment::view_app_comment($oRow, $bShowAppName, $bShowForm);
        }
    }

    /**
     * display a single comment (in $oRow)
     */
    public static function view_app_comment($oRow, $bShowAppName = false, $bShowForm = true)
    {
        $oComment = new comment(null, $oRow);
        $oComment->output_comment($bShowAppName, $bShowForm);
    }

    private function output_comment($bShowAppName = false, $bShowForm = true)
    {
        // by line
        $sBy = " by <i>".forum_lookup_user($this->oOwner->iUserId)."</i> on <i>".$this->sDateCreated."</i><br>\n";
       
        if ($bShowAppName)
        {
            $oVersion = new version($this->iVersionId);
            $sBy .= "Application: ".version::fullNameLink($this->iVersionId);
            $sBy .= ($oVersion->bHasMaintainer ? ' (has maintainer)' : ' (no maintainers)');   
        }          
 
        $sFooter = "";
        if ($bShowForm)
        {
            // reply post buttons
            $oVersion = new version($this->iVersionId);
            $oM = new objectManager("comment", "Post new comment");
            $oM->setReturnTo($oVersion->objectMakeUrl());
            $sFooter = " <a href=\"".$oM->makeUrl("add")."&iVersionId={$this->iVersionId}\" class=\"btn btn-default btn-xs\">".
                       "<i class=\"fa fa-comment\"></i> Comment</a> \n".
                       "<a href=\"".$oM->makeUrl("add")."&iVersionId={$this->iVersionId}&iThread={$this->iCommentId}\" class=\"btn btn-default btn-xs\">".
                       "<i class=\"fa fa-reply\"></i> Reply</a> ";

            // delete message button, for admins
            if ($this->canEdit())
            {
                $sFooter .= "<form method=\"post\" name=\"sMessage\" action=\"".BASE."objectManager.php\" class=\"inline\">\n".
                            "<button type=\"submit\" class=\"btn btn-default btn-xs\"><i class=\"fa fa-trash-o\"></i></button>\n".
                            "<input type=\"hidden\" name=\"iId\" value=\"$this->iCommentId\">".
                            "<input type=\"hidden\" name=\"sClass\" value=\"comment\">".
                            "<input type=\"hidden\" name=\"bQueued\" value=\"false\">".
                            "<input type=\"hidden\" name=\"sAction\" value=\"delete\">".
                            "<input type=\"hidden\" name=\"sTitle\" value=\"Delete comment\">".
                            "<input type=\"hidden\" name=\"sReturnTo\" value=\"".$oVersion->objectMakeUrl()."\">".
                            "</form>\n";
            }
        }

        echo "<div id=\"Comment-{$this->iCommentId}\" class=\"panel panel-default panel-forum\">\n".
               "<div class=\"panel-heading\"><b>{$this->sSubject}</b><br>{$sBy}</div>\n".
               "<div class=\"panel-body\">".htmlify_urls($this->sBody)."</div>\n".
               ($sFooter ? "<div class=\"panel-footer\">{$sFooter}</div>\n" : '').
               "</div>\n";
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
            $iStart = query_escape_string($iStart);
            $iNumRows = query_escape_string($iNumRows);
            $sLimit = " LIMIT $iStart,$iNumRows";
        }

        if($sOrderBy)
            $sOrderBy = " ORDER BY ".query_escape_string($sOrderBy);

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

        $oRow = query_fetch_object($hResult);

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
        while($oRow = query_fetch_object($hResult))
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
        return "<li><a href=\"$sLink\" class=\"showComment\" ".
               "data-id=\"{$oRow->commentId}\">$oRow->subject</a>". 
               ' by '.forum_lookup_user($oRow->userId)." on {$oRow->time}".
               "<div id=\"comment-{$oRow->commentId}\"></div></li>\n";
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

        while($oRow = query_fetch_object($hResult))
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
    public static function do_display_comments_flat($hResult)
    {
        while($oRow = query_fetch_object($hResult))
        {
            Comment::view_app_comment($oRow);
            $hResult2 = Comment::grab_comments($oRow->versionId, $oRow->commentId);
            if($hResult && query_num_rows($hResult2))
            {
                Comment::do_display_comments_flat($hResult2);
            }
        }
    }
    
    public static function display_comments_flat($versionId, $threadId)
    {
        $hResult = Comment::grab_comments($versionId, $threadId);
        Comment::do_display_comments_flat($hResult);
    }

    public static function view_app_comments($versionId, $threadId = 0)
    {
        global $aClean;

        // count posts
        $hResult = query_parameters("SELECT commentId FROM appComments WHERE versionId = '?'", $versionId);
        $messageCount = query_num_rows($hResult);

        $oVersion = new version($versionId);

        // message display mode changer
        if ($_SESSION['current']->isLoggedIn())
        {
            // FIXME we need to change this so not logged in users can change current view as well
            if (!empty($aClean['sCmode']))
                $_SESSION['current']->setPref("comments:mode", $aClean['sCmode']);

            $sel[$_SESSION['current']->getPref("comments:mode", "threaded")] = 'selected';
            echo '<form method="post" name="sMode" action="'.$oVersion->objectMakeUrl().'">',"\n";
            echo "<b>Application comments:</b> $messageCount total comments ";
            echo '<b>Mode:</b> <select name="sCmode" onchange="document.sMode.submit();" class="form-control form-control-inline input-sm">',"\n";
            echo '   <option value="flat" '.$sel['flat'].'>Flat</option>',"\n";
            echo '   <option value="threaded" '.$sel['threaded'].'>Threaded</option>',"\n";
            echo '   <option value="nested" '.$sel['nested'].'>Nested</option>',"\n";
            echo '   <option value="off" '.$sel['off'].'>No Comments</option>',"\n";
            echo '</select>',"\n";
            echo '</form>',"\n";
        }

        $oM = new objectManager("comment", "Add comment");
        $oM->setReturnTo($oVersion->objectMakeUrl());

        // post new message button
        echo '<form method="post" name="sMessage" action="objectManager.php">';
        echo '<input type="hidden" name="sAction" value="add">';
        echo $oM->makeUrlFormData();
        echo '<button type="submit" class="btn btn-default"><i class="fa fa-comment"></i> Post new comment</button> ',"\n";
        echo '<input type="hidden" name="iVersionId" value="'.$versionId.'"></form>',"\n";

        if( $messageCount > 0 )
        {
            echo '<p class="margin-top-md text-muted">The following comments are owned by whoever posted them. WineHQ is not responsible for what they say.</p>'."\n";
        }

        //hide or display depending on pref
        if ($_SESSION['current']->isLoggedIn())
            $mode = $_SESSION['current']->getPref("comments:mode", "flat");
        else
            $mode = "flat"; /* default non-logged in users to flat comment display mode */

        if ( isset($aClean['sMode']) && $aClean['sMode']=="nested")
            $mode = "nested";

        switch ($mode)
        {
        case "flat":
            Comment::display_comments_flat($versionId, $threadId);
            break;
        case "nested":
            Comment::display_comments_nested($versionId, $threadId);
            break;
        case "threaded":
            Comment::display_comments_threaded($versionId, $threadId);
            break;
        }
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
        echo "<h1 class=\"whq-app-title\">New Comment</h1>\n";
        if($aClean['iThread'] > 0)
        {
            $hResult = query_parameters("SELECT * FROM appComments WHERE commentId = '?'",
                                    $aClean['iThread']);
            $oRow = query_fetch_object($hResult);
            if($oRow)
            {
                // display post reply
                echo "<p><b>Replying To ...</b></p>\n";
                $this->view_comment_body($aClean['iThread'], true, false);

                // Set default reply subject
                if(!$this->sSubject)
                {
                    // Only add RE: once
                    if(stripos($oRow->subject, "RE:") !== false)
                        $this->sSubject = $oRow->subject;
                    else
                        $this->sSubject = "RE: {$oRow->subject}";
                }
            }
        }
        // help pane
        echo html_note(
                       "<i class=\"fa fa-exclamation-circle\"></i> Enter your comment in the box below<br>\n".
                       "Please <b>DO NOT</b> paste large terminal or debug outputs here. ".
                       "If you need to post debug output, please file a <a href=\"https://bugs.winehq.org/\"><i class=\"fa fa-bug\"></i> bug</a>",
                       "","","warning"
                      );
        // post form
        $form = "";
        $form .= "<div class=\"form-group\">\n";
        $form .= "  <label for=\"inputEmail3\" class=\"col-sm-2 control-label\">From:</label>\n";
        $form .= "  <div class=\"col-sm-10\"><p class=\"form-control-static\">".$_SESSION['current']->sRealname."</p></div>\n";
        $form .= "</div>\n";
        $form .= "<div class=\"form-group\">\n";
        $form .= "  <label for=\"inputPassword3\" class=\"col-sm-2 control-label\">Subject</label>\n";
        $form .= "  <div class=\"col-sm-10\">\n";
        $form .= "    <input type=\"text\" class=\"form-control\" id=\"sSubject\" name=\"sSubject\" value=\"{$this->sSubject}\">\n";
        $form .= "  </div>\n";
        $form .= "</div>\n";
        $form .= "<div class=\"form-group\">\n";
        $form .= "  <div class=\"col-sm-offset-2 col-sm-10\">\n";
        $form .= "    <textarea name=\"sBody\" rows=\"15\" class=\"form-control\">{$this->sBody}</textarea>\n";
        $form .= "  </div>\n";
        $form .= "</div>\n";
        $form .= "<input type=\"hidden\" name=\"iThread\" value=\"{$aClean['iThread']}\">\n";
        $form .= "<input type=\"hidden\" name=\"iVersionId\" value=\"{$aClean['iVersionId']}\">\n";
        echo $form;
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


/**
 * Comment functions that are not part of the class
 * @param int $iUserId
 * @return string
 */
function forum_lookup_user($iUserId)
{
    $sMailto = '';
    if ($iUserId > 0)
    {
        $oUser = new User($iUserId);
        if($_SESSION['current']->isLoggedIn())
            $sMailto = '<a href="'.BASE.'contact.php?iRecipientId='.
                    $oUser->iUserId.'">' .$oUser->sRealname . '</a>';
        else
            $sMailto = $oUser->sRealname;
    }
    if ( !$iUserId || (isset($oUser) && !$oUser->isLoggedIn()) )
    {
        $sMailto = 'Anonymous';
    }
    return $sMailto;
}


