<?php
/***************************************/
/* Monitor class and related functions */
/***************************************/


/**
 * Monitor class for handling Monitors
 */
class Monitor {
    var $iMonitorId;

    // variables necessary for creating a monitor
    var $iAppId;
    var $iVersionId;
    var $iUserId;

    /**
     * Constructor.
     * If $iMonitorId is provided, fetches Monitor.
     */
    function __construct($iMonitorId="", $oRow = null)
    {
        if(!$iMonitorId && !$oRow)
            return;

        if(!$oRow)
        {
            $sQuery = "SELECT *
                       FROM appMonitors
                       WHERE monitorId = '".$iMonitorId."'";
            $hResult = query_appdb($sQuery);
            $oRow = query_fetch_object($hResult);
        }

        if($oRow)
        {
            $this->iMonitorId = $oRow->monitorId;
            $this->iAppId = $oRow->appId;
            $this->iVersionId = $oRow->versionId;
            $this->iUserId = $oRow->userId;
        }
    }

    function find($iUserId, $iVersionId=0)
    {
        if($iUserId && $iVersionId)
        {
            $sQuery = "SELECT *
                          FROM appMonitors
                          WHERE userId = '".$iUserId."'
                          AND versionId = '".$iVersionId."'";
            $hResult = query_appdb($sQuery);
            if( $oRow = query_fetch_object($hResult) )
            {
                $this->iMonitorId = $oRow->monitorId;
                $this->iAppId = $oRow->appId;
                $this->iVersionId = $oRow->versionId;
                $this->iUserId = $oRow->userId;
            }
        }
    }

    public function objectGetParent($sClass = '')
    {
        if($this->iVersionId)
            return new version($this->iVersionId);
        else
            return new application($this->iAppId);
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
        /* We have none */
        return array();
    }

    /*
     * Creates a new Monitor.
     * Informs interested people about the creation.
     * Returns true on success, false on failure
     */
    function create()
    {
        /* Check for duplicate entries */
        $oMonitor = new monitor();
        $this->iUserId = $_SESSION['current']->iUserId;
        $oMonitor->find($this->iUserId, $this->iVersionId);
        if($oMonitor->iVersionId)
            return FALSE;

        // create the new monitor entry
        $hResult = query_parameters("INSERT INTO appMonitors (versionId, appId,".
                                    "submitTime, userId) ".
                                    "VALUES ('?', '?', ?, '?')",
                                    $this->iVersionId, $this->iAppId,
                                    "NOW()", $this->iUserId);

        if($hResult)
        {
            Monitor::__construct(query_appdb_insert_id());
            $sWhatChanged = "New monitor\n\n";
            $this->SendNotificationMail("add", $sWhatChanged);
            return true;
        } else
        {
            addmsg("Error while creating a new Monitor.", "red");
            return false;
        }
    }

    function update()
    {
        $oMonitor = new monitor($this->iMonitorId);

        if($this->iVersionId && $oMonitor->iVersionId != $this->iVersionId)
        {
            $hResult = query_parameters("UPDATE appMonitors SET versionId = '?' 
                                         WHERE monitorId = '?'",
                                         $this->iVersionId, $this->iMonitorId);

            if(!$hResult)
                return FALSE;
        }

        if($this->iAppId && $oMonitor->iAppId != $this->iAppId)
        {
            $hResult = query_parameters("UPDATE appMonitors SET appId = '?' 
                                         WHERE monitorId = '?'",
                                         $this->iAppId, $this->iMonitorId);

            if(!$hResult)
                return FALSE;
        }

        return TRUE;
    }

    function unQueue()
    {
        return true; // We don't queue monitors
    }

    function objectGetSubmitterId()
    {
        return $this->iUserId;
    }

    function objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction)
    {
        return new mailOptions();
    }

    function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        $sSubject = null;
        $sMsg = null;

        if($this->iVersionId)
        {
            $sWhat = "version";
            $sName = version::fullName($this->iVersionId);
            $oVersion = new version($this->iVersionId);
            $sUrl = $oVersion->objectMakeUrl();
        } else
        {
            $sWhat = "application";
            $oApp = new application($this->iAppId);
            $sName = $oApp->sName;
            $sUrl = $oApp->objectMakeUrl();
        }

        if($bMailSubmitter)
        {
            switch($sAction)
            {
                case "delete":
                    if($bParentActino)
                    {
                        $sSubject = "Monitored $sWhat deleted";
                        $sMsg = "The $sWhat $sName which you monitored has been ".
                                "deleted by ".$_SESSION['current']->iUserId.".";
                    }
                break;
            }
            $aMailTo = null;
        } else
        {
            $oUser = new user($this->iUserId);
            $sUser = $oUser->sName;
            switch($sAction)
            {
                case "delete":
                    if(!$bParentAction)
                    {
                        $sSubject = "Monitor for $sName removed: $sUser";
                        $sMsg = $sUrl;
                    }
                break;
            }
            User::get_notify_email_address_list(null, $this->iVersionId);
        }
        return array($sSubject, $sMsg, $aMailTo);
    }

    function purge()
    {
        return $this->delete();
    }

   /**
     * Removes the current Monitor from the database.
     * Informs interested people about the deletion.
     */
    function delete()
    {
        $hResult = query_parameters("DELETE FROM appMonitors WHERE monitorId = '?'", $this->iMonitorId);

        if(!$hResult)
            return FALSE;

        return TRUE;
    }

    function SendNotificationMail($sAction="add",$sMsg=null)
    {
        /* Set variables depending on whether it is an application or version monitor */
        if(isset($this->iVersionId))
        {
            $oVersion = new version($this->iVersionId);
            $sAppName = version::fullName($this->iVersionId);
            $sUrl = $oVersion->objectMakeUrl();
            $sVersion = " version";
        } else
        {
            $oApp = new application($this->iAppId);
            $sAppName = Application::lookup_name($this->iAppId);
            $sUrl = $oApp->objectMakeUrl();
        }

        switch($sAction)
        {
            case "add":
                $sSubject = "Monitor for ".$sAppName;
                $sSubject .= " added: ".$_SESSION['current']->sRealname;
                $sMsg .= "$sUrl\n";
                addmsg("You will now receive an email whenever changes are made ".
                "to this application$sVersion.", "green");
            break;
            case "delete":
                $sSubject = "Monitor for ".$sAppName;
                $sSubject .= " removed: ".$_SESSION['current']->sRealname;
                $sMsg .= "$sUrl\n";
                addmsg("You will no longer receive an email whenever changes ".
                "are made to this application$sVersion.", "green");
            break;
        }
        $sEmail = User::get_notify_email_address_list(null, $this->iVersionId);
        if($sEmail)
            mail_appdb($sEmail, $sSubject ,$sMsg);
    }

    function objectGetState()
    {
        // We don't queue monitors
        return 'accepted';
    }

    function canEdit()
    {
        if($_SESSION['current']->hasPriv("admin") ||
           ($this->iUserId == $_SESSION['current']->iUserId))
            return TRUE;

        return FALSE;
    }

    function mustBeQueued()
    {
        return FALSE;
    }

    /* Stub */
    function display()
    {
        return "";
    }

    /* Stub */
    function getOutputEditorValues($aClean)
    {
        $this->iVersionId = $aClean['iVersionId'];
        $this->iAppId = $aClean['iAppId'];
    }

    /* Stub */
    function objectGetHeader()
    {
        return null;
    }

    function objectGetId()
    {
        return $this->iMonitorId;
    }

        /* Stub */
    function objectGetTableRow()
    {
        return null;
    }

    /* Stub */
    function objectMakeLink()
    {
        return "";
    }

    /* Stub */
    function objectMakeUrl()
    {
        return "";
    }

    /* Stub */
    function outputEditor()
    {
        return "";
    }

    function objectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = '', $bAscending = true)
    {
        $hResult = query_parameters("SELECT * FROM appMonitors");

        if(!$hResult)
            return FALSE;

        return $hResult;
    }

    function objectGetEntriesCount($sState)
    {
        $hResult = query_parameters("SELECT COUNT(DISTINCT monitorId) as count
                                    FROM appMonitors");

        if(!$hResult)
            return FALSE;

        $oRow = query_fetch_object($hResult);

        if(!$oRow)
            return FALSE;

        return $oRow->count;
    }

    function allowAnonymousSubmissions()
    {
        /* That makes no sense */
        return FALSE;
    }

    /* Retrieve the user's monitored versions */
    public static function getVersionsMonitored($oUser)
    {
         $hResult = query_parameters("SELECT appId, versionId FROM appMonitors WHERE userId = '?'", $oUser->iUserId);

         if(!$hResult || query_num_rows($hResult) == 0)
             return NULL;

         $aVersionsMonitored = array();

         for($i = 0; $oRow = query_fetch_object($hResult); $i++)
             $aVersionsMonitored[$i] = array($oRow->appId, $oRow->versionId);

         return $aVersionsMonitored;
    }
}

