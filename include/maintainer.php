<?php
/*****************************/
/* functions for maintainers */
/*****************************/
require_once(BASE."include/application.php");
require_once(BASE."include/version.php");
require_once(BASE."include/user.php");

//FIXME: when we have php5 move this into the maintainer class as a constant var
define('iNotificationIntervalDays', 2); // days between each notification level

// class that can retrieve the queued entries for a given maintainer instance
class queuedEntries
{
  var $aVersionIds;
  var $aScreenshotIds;
  var $aTestDataIds;

  var $oMaintainer; // the maintainer we are retrieving entries for

  function queuedEntries($oMaintainer)
  {
    $this->oMaintainer = $oMaintainer;

    $this->aVersionIds = array();
    $this->aScreenshotIds = array();
    $this->aTestDataIds = array();
  }

  // returns true if none of the arrays have any entries in them
  function isEmpty()
  {
    if((count($this->aVersionIds) == 0) &&
       (count($this->aScreenshotIds) == 0) &&
       (count($this->aTestDataIds) == 0))
      {
        return true;
      }

    return false;
  }

  function retrieveQueuedEntries()
  {
    $bDebugOutputEnabled = false;

    if($bDebugOutputEnabled)
      echo "retrieveQueuedEntries() starting\n";

    ////////////////////////////////////
    // retrieve a list of versions to check for queued data
    $aVersionsToCheck = array();
    if($this->oMaintainer->bSuperMaintainer)
    {
      if($bDebugOutputEnabled)
        echo "maintainer is super maintainer\n";

      $oApp = new Application($this->oMaintainer->iAppId);

      //TODO: would like to rely on the constructor but we might be a user with 'admin'
      // privileges and that would mean we would end up retrieved queued versions for
      // this application or unqueued versions depending on which user we were
      $aVersions = $oApp->getVersions();

      foreach($aVersions as $oVersion)
      {
        if($bDebugOutputEnabled)
        {
          echo "Processing version: ";
          print_r($oVersion);
        }

        if($oVersion->objectGetState() == 'queued')
        {
          $this->aVersions[] = $oVersion->objectGetId();
        } else if($oVersion->objectGetState() == 'accepted') // version isn't queued
        {
          // add the unqueued version to the list of versions to check for queued data
          $aVersionsToCheck[] = $oVersion->iVersionId;
        }
      }
    } else // just a normal maintainer
    {
      $aVersionsToCheck[] = $this->oMaintainer->iVersionId;
      if($bDebugOutputEnabled)
        echo "Normal maintainer of version ".$this->oMaintainer->iVersionId."\n";
    }

    // go through all of the verions to see what queued data they have
    foreach($aVersionsToCheck as $iVersionId)
    {
      if($bDebugOutputEnabled)
        echo "Processing iVersionId of ".$iVersionId."\n";
        
      //////////////////
      // queued testdata

      // retrieve queued testdata for this version
      $sQuery = "select * from testResults where versionId = '?' and state = '?'";
      $hResult = query_parameters($sQuery, $iVersionId, 'queued');

      // go through the test results looking for the oldest queued data
      while($oTestingRow = query_fetch_object($hResult))
      {
        if($bDebugOutputEnabled)
          echo "\tQueued TestData found\n";
        $oTestData = new TestData(null, $oTestingRow);

        $this->aTestDataIds[] = $oTestData->objectGetId();
      }
      // queued testdata
      //////////////////


      ////////////////////
      // queued screenshots
      $sQuery = "select * from appData where type = 'screenshot' and versionId = '?' and state = '?'";
      $hResult = query_parameters($sQuery, $iVersionId, 'queued');
      while($oScreenshotRow = query_fetch_object($hResult))
      {
        $oScreenshot = new Screenshot(null, $oScreenshotRow);

        $this->aScreenshotIds[] = $oScreenshot->objectGetId();
      }
      // queued screenshots
      //////////////////////
    }
  }
}

// contains the results of a notification update so other logic
// can act on these results
class notificationUpdate
{
  var $sEmail;
  var $sSubject;
  var $sMsg; // contents of the email we will send to the maintainer
  var $iTargetLevel; // the target notification level based upon the
                     // maintiners queued entries

  function notificationUpdate($sEmail, $sSubject, $sMsg, $iTargetLevel)
  {
    $this->sEmail = $sEmail;
    $this->sSubject = $sSubject;
    $this->sMsg = $sMsg;
    $this->iTargetLevel = $iTargetLevel;
  }
}


class maintainer
{
    var $iMaintainerId;
    var $iAppId;
    var $iVersionId;
    var $iUserId;
    var $sMaintainReason;
    var $bSuperMaintainer;
    var $aSubmitTime; //FIXME: should be 'sSubmitTime'
    var $sState;
    var $sReplyText;

    // parameters used in the queued data notification system
    // that lets maintainers know that their applications/versions have
    // queued data for them to process
    var $iNotificationLevel; // the current warning level of this maintainer
    var $sNotificationTime; // the time when we last warned this maintainer

    function maintainer($iMaintainerId = null, $oRow = null)
    {
        // set a default notification level of 0
        $this->iNotificationLevel = 0;
      
        if(!$iMaintainerId && !$oRow)
            return;

        if(!$oRow)
        {
            $sQuery = "SELECT * FROM appMaintainers WHERE maintainerId = '?'";
            $hResult = query_parameters($sQuery, $iMaintainerId);
            if($hResult)
                $oRow = query_fetch_object($hResult);
        }

        if($oRow)
        {
            $this->iMaintainerId = $oRow->maintainerId;
            $this->iAppId = $oRow->appId;
            $this->iVersionId = $oRow->versionId;
            $this->iUserId = $oRow->userId;
            $this->sMaintainReason = $oRow->maintainReason;
            $this->bSuperMaintainer = $oRow->superMaintainer;
            $this->aSubmitTime = $oRow->submitTime;
            $this->sState = $oRow->state;

            $this->iNotificationLevel = $oRow->notificationLevel;
            $this->sNotificationTime = $oRow->notificationTime;
        }
    }

    function create()
    {
        /* user id, appid, and maintain reason must be valid to continue */
        if(!$this->iAppId || !$this->sMaintainReason)
        {
            return NULL;
        }

        if(!$this->iUserId)
            $this->iUserId = $_SESSION['current']->iUserId;

        $oApp = new application($this->iAppId);
        if(!$this->bSuperMaintainer)
            $oVersion = new version($this->iVersionId);

        if($oApp->objectGetState() != 'accepted' ||
           (!$this->bSuperMaintainer && $oVersion->objectGetState() != 'accepted'))
            $this->sState = "pending";
        else
            $this->sState = $this->mustBeQueued() ? 'queued' : 'accepted';

        $hResult = query_parameters("INSERT INTO appMaintainers (appId, versionId, ".
                                    "userId, maintainReason, superMaintainer, submitTime, state) ".
                                    "VALUES ('?', '?', '?', '?', '?', ?, '?')",
                                    $this->iAppId, $this->iVersionId,
                                    $this->iUserId, $this->sMaintainReason,
                                    $this->bSuperMaintainer, "NOW()", $this->sState);

        /* this objects id is the insert id returned by the database */
        $this->iMaintainerId = query_appdb_insert_id();

        if(!$this->mustBeQueued())
            $this->updateMaintainerState();

        /* If this is a non-queued maintainer submission, remove the user's non-
           super maintainer entries for the application's versions.  This check is
           also done in unQueue() */
        if(!$this->mustBeQueued() && $this->bSuperMaintainer)
            $this->removeUserFromAppVersions();

        return $hResult;
    }

    function unQueue()
    {
        /* if the user isn't already a supermaintainer of the application and */
        /* if they are trying to become a maintainer and aren't already a maintainer of */
        /* the version, then continue processing the request */

        $oUser = new User($this->iUserId);
        
        if(!$oUser->isSuperMaintainer($this->iAppId) &&
           ((!$this->bSuperMaintainer && !$oUser->isMaintainer($this->iVersionId)) || $this->bSuperMaintainer))
        {
            /* unqueue the maintainer entry */
            $hResult = query_parameters("UPDATE appMaintainers SET state='accepted' WHERE userId = '?' AND maintainerId = '?'",
                                        $this->iUserId, $this->iMaintainerId);

            if($hResult)
            {
                $sStatusMessage = "<p>The maintainer was successfully added into the database</p>\n";

                $this->updateMaintainerState();

                //Send Status Email
                $sEmail = $oUser->sEmail;
                if ($sEmail)
                {
                    if($this->iVersionId)
                    {
                        $oVersion = new Version($this->iVersionId);
                        $sURL = $oVersion->objectMakeUrl();
                        $sName = version::fullName($this->iVersionId);
                    } else
                    {
                        $oApp = new Application($this->iAppId);
                        $sURL = $oApp->objectMakeUrl();
                        $sName = $oApp->sName;
                    }

                    $sSubject =  "Application Maintainer Request Report";
                    $sMsg  = "Your application to be the maintainer of $sName has been accepted.\n";
                    $sMsg .= "$sURL\n";
                    $sMsg .= "$this->sReplyText\n";
                    $sMsg .= "We appreciate your help in making the Application Database better for all users.\n\n";

                    mail_appdb($sEmail, $sSubject ,$sMsg);
                }
                $this->sState = 'accepted';
            }
        } else
        {
            /* Delete entry, but only if queued */
            query_parameters("DELETE from appMaintainers WHERE userId = '?' AND maintainerId = '?' AND state = 'queued'", $this->iUserId, $this->iMaintainerId);

            if($oUser->isSuperMaintainer($this->iAppId) && !$this->bSuperMaintainer)
                $sStatusMessage = "<p>User is already a super maintainer of this application</p>\n";
            else
                $sStatusMessage = "<p>User is already a maintainer/super maintainer of this application/version</p>\n";
        }

        /* Delete any maintainer entries the user had for the application's versions,
           if this is a super maintainer request */
        if($this->bSuperMaintainer)
            $this->removeUserFromAppVersions();

        return $sStatusMessage;
    }

    function reject()
    {
        $oUser = new User($this->iUserId);
        $sEmail = $oUser->sEmail;
        if ($sEmail)
        {
            $oApp = new Application($oRow->appId);
            $oVersion = new Version($oRow->versionId);
            $sSubject =  "Application Maintainer Request Report";
            $sMsg  = "Your application to be the maintainer of $oApp->sName $oVersion->sName was rejected. ";
            $sMsg .= $this->sReplyText;
            $sMsg .= "";
            $sMsg .= "-The AppDB admins\n";

           mail_appdb($sEmail, $sSubject ,$sMsg);
        }

        //delete main item
        $sQuery = "DELETE from appMaintainers where maintainerId = '?'";
        $hResult = query_parameters($sQuery, $this->iMaintainerId);

        return $hResult;
    }

    function mustBeQueued()
    {
        /* In place for future fine-grained permisson system, only allow admins for now */
        if($_SESSION['current']->hasPriv("admin"))
            return FALSE;
        else
            return TRUE;
    }

    function purge()
    {
        return $this->delete();
    }

    function delete()
    {
        $sQuery = "DELETE from appMaintainers where maintainerId = '?'";
        $hResult = query_parameters($sQuery, $this->iMaintainerId);

        if(!$hResult)
            return FALSE;

        $this->updateMaintainerState();

        return TRUE;
    }

    public function findAppMaintainer($iUserId, $iAppId)
    {
        $hResult = query_parameters("SELECT * FROM appMaintainers WHERE userId = '?' AND appId = '?' AND superMaintainer = '1'", $iUserId, $iAppId);

        if(!$hResult)
            return null;

        $oRow = query_fetch_object($hResult);
        return new maintainer(null, $oRow);
    }

    public function findVersionMaintainer($iUserId, $iVersionId)
    {
        $hResult = query_parameters("SELECT * FROM appMaintainers WHERE userId = '?' AND versionId = '?'", $iUserId, $iVersionId);

        if(!$hResult)
            return null;

        $oRow = query_fetch_object($hResult);
        return new maintainer(null, $oRow);
    }

    function deleteMaintainer($oUser, $iAppId = null, $iVersionId = null)
    {
        /* remove supermaintainer */
        if($iAppId && ($iVersionId == null))
        {
            $superMaintainer = 1;
            $hResult = query_parameters("DELETE FROM appMaintainers WHERE userId = '?'
                                         AND appId = '?' AND superMaintainer = '?'",
                                        $oUser->iUserId, $iAppId, $superMaintainer);
        } else if($iAppId && $iVersionId) /* remove a normal maintainer */
        {
            $superMaintainer = 0;
            $hResult = query_parameters("DELETE FROM appMaintainers WHERE userId = '?'
                                         AND appId = '?' AND versionId = '?' AND superMaintainer = '?'",
                                        $oUser->iUserId, $iAppId, $iVersionId, $superMaintainer);
        } else if(($iAppId == null) && ($iVersionId == null)) /* remove all maintainership by this user */
        {
            $hResult = query_parameters("DELETE FROM appMaintainers WHERE userId = '?'",
                                        $oUser->iUserId);
        }

        if($hResult)
            return true;
        
        return false;
    }

    function deleteMaintainersForVersion($oVersion)
    {
        if(!$oVersion->iVersionId)
            return FALSE;

        $hResult = query_parameters("DELETE from appMaintainers WHERE versionId='?'",
                                    $oVersion->iVersionId);
        return $hResult;
    }

    function deleteMaintainersForApplication($oApp)
    {
        $sQuery = "DELETE from appMaintainers WHERE appId='?'";
        $hResult = query_parameters($sQuery, $oApp->iAppId);
        return $hResult;
    }

    public function updateMaintainerState()
    {
        if($this->bSuperMaintainer)
        {
            $this->updateAppMaintainerState($this->iAppId);
            $oApp = new application($this->iAppId);
            $aVersions = $oApp->objectGetChildrenClassSpecific('version');
            foreach($aVersions as $oVersion)
                $this->updateVersionMaintainerState($oVersion->objectGetId());
        } else
        {
            $this->updateVersionMaintainerState($this->iVersionId);
        }
    }

    public function updateAppMaintainerState($iAppId)
    {
        $oApp = new application($iAppId);
        $oApp->updateMaintainerState();
    }

    public function updateVersionMaintainerState($iVersionId)
    {
        $oVersion = new version($iVersionId);
        $oVersion->updateMaintainerState();
    }

    function getSubmitterEmails()
    {
        $sRecipients = '';
        $sQuery = "SELECT DISTINCT(user_list.email) FROM appMaintainers, user_list WHERE
                appMaintainers.userId = user_list.userId
                AND
                appMaintainers.state = 'accepted'";
        $hResult = query_parameters($sQuery);
        if(!$hResult)
            return FALSE;
        for($i = 0; $oRow = query_fetch_object($hResult); $i++)
        {
            if($i)
                $sRecipients .= ' ';
            $sRecipients .= $oRow->email;
        }
        return $sRecipients;
    }

    public static function ObjectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = '', $bAscending = true)
    {
        /* Not implemented */
        if($sState == 'rejected')
            return FALSE;

        $sLimit = "";

        /* Should we add a limit clause to the query? */
        if($iRows || $iStart)
        {
            $sLimit = " LIMIT ?,?";

            /* Selecting 0 rows makes no sense, so we assume the user wants to select all of them
               after an offset given by iStart */
            if(!$iRows)
                $iRows = maintainer::objectGetEntriesCount($sState);
        }

        /* Excluding requests for queued apps and versions, as these will be
           handled automatically */
        $sQuery = "SELECT * FROM appMaintainers WHERE
            appMaintainers.state = '?'$sLimit";

        if($sState != 'accepted')
        {
            if($_SESSION['current']->hasPriv("admin"))
            {
                if($sLimit)
                {
                    return query_parameters($sQuery, $sState, $iStart, $iRows);
                } else
                {
                    return query_parameters($sQuery, $sState);
                }
            } else
            {
                return NULL;
            }
        } else
        {
            if($sLimit)
            {
                return query_parameters($sQuery, $sState, $iStart, $iRows);
            } else
            {
                return query_parameters($sQuery, $sState);
            }
        }
    }

    /* retrieve a maintainer object from a row returned by */
    /* ObjectGetEntries() */
    function ObjectGetObjectFromObjectGetEntriesRow($oRow)
    {
        return new maintainer($oRow->maintainerId);
    }

    // returns the number of applications/versions a particular user maintains
    public static function getMaintainerCountForUserId($iUserId, $bSuperMaintainer)
    {
        $sQuery = "SELECT count(*) as cnt from appMaintainers WHERE userid = '?' AND superMaintainer = '?'".
                  " AND state ='?'";
        $hResult = query_parameters($sQuery, $iUserId, $bSuperMaintainer ? "1" : "0", 'accepted');
        if(!$hResult)
            return 0;
        $oRow = query_fetch_object($hResult);
        return $oRow->cnt;
    }

    // returns the number of applications/versions a particular user maintains
    public static function getMaintainerCountForUser($oUser, $bSuperMaintainer)
    {
        return self::getMaintainerCountForUserId($oUser->iUserId, $bSuperMaintainer);
    }
    
    // gets maintainers who have not logged in in the specified number of months 
    public static function getInactiveMaintainers($iMonths)
    {
       $sQuery = "SELECT * FROM appMaintainers, user_list WHERE appMaintainers.userId = user_list.userid 
           AND stamp <= DATE_SUB(CURDATE(), INTERVAL $iMonths MONTH)";       
        return query_parameters($sQuery);
    }

    /**
     * get the applications and versions that this user maintains 
     */
    public static function getAppsMaintained($oUser)
    {
        /* retrieve the list of application and order them by application name */
        $hResult = query_parameters("SELECT appMaintainers.appId, versionId, superMaintainer, appName FROM ".
                                    "appFamily, appMaintainers WHERE appFamily.appId = appMaintainers.appId ".
                                    "AND userId = '?' AND appMaintainers.state = '?' ORDER BY appName",
                                    $oUser->iUserId, 'accepted');
        if(!$hResult || query_num_rows($hResult) == 0)
            return NULL;

        $aAppsMaintained = array();
        $c = 0;
        while($oRow = query_fetch_object($hResult))
        {
            $aAppsMaintained[$c] = array($oRow->appId, $oRow->versionId, $oRow->superMaintainer);
            $c++;
        }

        return $aAppsMaintained;
    }

    public static function objectGetEntriesCount($sState)
    {
        /* Not implemented */
        if($sState == 'rejected')
            return FALSE;

        /* Excluding requests for queued apps and versions, as these are handled 
           automatically.  One SELECT for super maintainers, one for maintainers. */
       $sQuery = "SELECT COUNT(maintainerId) as count FROM appMaintainers WHERE
            appMaintainers.state = '?'";

        if(!($hResult = query_parameters($sQuery, $sState)))
            return FALSE;

        if($oRow = query_fetch_object($hResult))
            $iCount = $oRow->count;
        else
            $iCount = 0;

        return $iCount;
    }

    /* see how many unique maintainers we actually have */
    public static function getNumberOfMaintainers()
    {
        $hResult = query_parameters("SELECT DISTINCT userId FROM appMaintainers WHERE state='accepted';");
        return query_num_rows($hResult);
    }

    public static function isUserMaintainer($oUser, $iVersionId = null)
    {
        /* if we are a super maintainer, we are a maintainer of this version as well */
        $oVersion = new Version($iVersionId);
        if($oUser->isSuperMaintainer($oVersion->iAppId))
            return true;

        /* otherwise check if we maintain this specific version */
        if($iVersionId)
        {
            $sQuery = "SELECT * FROM appMaintainers WHERE userId = '?' AND versionId = '?' AND state = '?'";
            $hResult = query_parameters($sQuery, $oUser->iUserId, $iVersionId, 'accepted');
        } else // are we maintaining any version ?
        {
            $sQuery = "SELECT * FROM appMaintainers WHERE userId = '?' AND state = '?'";
            $hResult = query_parameters($sQuery, $oUser->iUserId, 'accepted');
        }
        if(!$hResult)
            return false;
        
        return query_num_rows($hResult);
    }

    public static function isUserSuperMaintainer($oUser, $iAppId = null)
    {
        if($iAppId)
        {
            $sQuery = "SELECT * FROM appMaintainers WHERE userid = '?' AND appId = '?' AND superMaintainer = '1' AND state = '?'";
            $hResult = query_parameters($sQuery, $oUser->iUserId, $iAppId, 'accepted');
        } else /* are we super maintainer of any applications? */
        {
            $sQuery = "SELECT * FROM appMaintainers WHERE userid = '?' AND superMaintainer = '1' AND state = '?'";
            $hResult = query_parameters($sQuery, $oUser->iUserId, 'accepted');
        }
        if(!$hResult)
            return false;
        return query_num_rows($hResult);
    }

    /* Returns true if the given app has a maintainer, false otherwise */
    public function appHasMaintainer($iAppId)
    {
        $hResult = query_parameters("SELECT COUNT(maintainerId) as count FROM appMaintainers WHERE appId = '?' AND superMaintainer = '1' AND state = 'accepted'", $iAppId);

        if(!$hResult)
            return false;

        $oRow = query_fetch_object($hResult);
        return $oRow->count > 0;
    }

    /* Returns true if the given version has a maintainer, false otherwise */
    public function versionHasMaintainer($iVersionId)
    {
        $oVersion = new version($iVersionId);
        $hResult = query_parameters("SELECT COUNT(maintainerId) as count FROM appMaintainers WHERE (appId = '?' AND superMaintainer = '1') OR (versionId = '?') AND state = 'accepted'", $oVersion->iAppId, $iVersionId);

        if(!$hResult)
            return false;

        $oRow = query_fetch_object($hResult);
        return $oRow->count > 0;
    }

    /* if given an appid or a version id return a handle for a query that has */
    /* the user ids that are maintainers for this particular appid or version id */
    public static function getMaintainersForAppIdVersionId($iAppId = null, $iVersionId = null)
    {
        $hResult = null;

        if($iVersionId)
        {
            $hResult = query_parameters("SELECT userId from appMaintainers, appVersion
                WHERE
                    appMaintainers.state = 'accepted'
                    AND
                    appVersion.versionId = '?'
                    AND
                    (
                        appMaintainers.versionId = appVersion.versionId
                        OR
                        (
                            appMaintainers.appId = appVersion.appId
                            AND superMaintainer = '1'
                        )
                    )", $iVersionId);
        } 
        /*
         * If versionId was not supplied we fetch supermaintainers of application and maintainer of all versions.
         */
        elseif($iAppId)
        {
            $hResult = query_parameters("SELECT userId 
                                 FROM appMaintainers
                                 WHERE appId = '?' AND state = 'accepted'",
                                        $iAppId);
        }

        return $hResult;
    }

    /*
     * get the userIds of super maintainers for this appId
     */
    public static function getSuperMaintainersUserIdsFromAppId($iAppId)
    {
        $sQuery = "SELECT userId FROM ".
            "appMaintainers WHERE appId = '?' " .
            "AND superMaintainer = '1' AND state='?';";
        $hResult = query_parameters($sQuery, $iAppId, 'accepted');
        $aUserIds = array();
        $c = 0;
        while($oRow = query_fetch_object($hResult))
        {
            $aUserIds[$c] = $oRow->userId;
            $c++;
        }

        return $aUserIds;
    }

    function ObjectGetHeader()
    {
        $oTableRow = new TableRow();
        $oTableRow->AddTextCell("Submission Date");
        $oTableRow->AddTextCell("Application Name");
        $oTableRow->AddTextCell("Version");
        $oTableRow->AddTextCell("Super maintainer?");
        $oTableRow->AddTextCell("Submitter");
        return $oTableRow;
    }

    function ObjectGetTableRow()
    {
        $oUser = new User($this->iUserId);
        $oApp = new Application($this->iAppId);
        $oVersion = new Version($this->iVersionId);

        $oTableRow = new TableRow();

        $oTableRow->AddTextCell(print_date(mysqldatetime_to_unixtimestamp($this->aSubmitTime)));
        $oTableRow->AddTextCell($oApp->objectMakeLink());
        $oTableRow->AddTextCell(($this->bSuperMaintainer) ? "N/A" : $oVersion->objectMakeLink());
        $oTableRow->AddTextCell(($this->bSuperMaintainer) ? "Yes" : "No");
        $oTableRow->AddTextCell($oUser->objectMakeLink());

        $oOMTableRow = new OMTableRow($oTableRow);
        return $oOMTableRow;
    }

    function objectDisplayAddItemHelp($aClean)
    {
        echo "<p>This page is for submitting a request to become an application maintainer.\n";
        echo "An application maintainer is someone who runs the application \n";
        echo "regularly and who is willing to be active in reporting regressions with newer \n";
        echo "versions of Wine and to help other users run this application under Wine.</p>";
        echo "<p>Being an application maintainer comes with new rights and new responsibilities; please be sure to read the <a href=\"https://wiki.winehq.org/AppDB_Maintainer_Guidelines\" target=\"_blank\">maintainer's guidelines</a> before to proceed.</p> ";
        echo "<p>We ask that all maintainers explain why they want to be an application maintainer,\n";
        echo "why they think they will do a good job and a little about their experience\n";
        echo "with Wine.  This is both to give you time to\n";
        echo "think about whether you really want to be an application maintainer and also for the\n";
        echo "appdb admins to identify people that are best suited for the job.  Your request\n";
        echo "may be denied if there are already a handful of maintainers for this application or if you\n";
        echo "don't have the experience with Wine that is necessary to help other users out.</p>\n";

        if(!$aClean['iVersionId'])
        {
            echo "<p>Super maintainers are just like normal maintainers but they can modify EVERY version of\n";
            echo "this application (and the application itself).  We don't expect you to run every version but at least to help keep\n";
            echo "the forums clean of stale and out-of-date information.</p>\n";

        }
    }

    function ObjectDisplayQueueProcessingHelp()
    {
        echo "<div align=center><table width='90%' border=0 cellpadding=3 cellspacing=0><tr><td>\n\n";
        echo "Please enter an accurate and personalized reply anytime a maintainer request is rejected.\n";
        echo "Its not polite to reject someones attempt at trying to help out without explaining why.\n";
        echo "</td></tr></table></div>\n\n";    
    }

    function objectGetState()
    {
        return $this->sState;
    }

    function canEdit()
    {
        if($_SESSION['current']->hasPriv("admin") || $this->iUserId == $_SESSION['current']->iUserId)
            return TRUE;

        return FALSE;
    }

    function objectGetCustomVars($sAction)
    {
        switch($sAction)
        {
            case "add":
                return array("iAppId","iVersionId");

            case "addHelp":
                return array("iVersionId");

            default:
                return null;
        }
    }

    function outputEditor($aClean = null)
    {
        if(!$this->iMaintainerId)
        {
            if($aClean['iVersionId'])
            {
                $oVersion = new version($aClean['iVersionId']);
                $iAppId = $oVersion->iAppId;
            } else
            {
                $iAppId = $aClean['iAppId'];
            }

            $oApp = new application($iAppId);

            echo "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";
            echo "<tr valign=top><td class=color0>";
            echo '<b>Application</b></td><td>'.$oApp->sName;
            echo '</td></tr>',"\n";
            if($aClean['iVersionId'])
            {
                echo "<tr valign=top><td class=color0>";
                echo '<b>Version</b></td><td>'.$oVersion->sName;
                echo '</td></tr>',"\n";
            }

            $iSuperMaintainer = $aClean['iVersionId'] ? 0 : 1;
            echo "<input type='hidden' name='iAppId' value='{$aClean['iAppId']}'>";
            echo "<input type='hidden' name='iVersionId' value='{$aClean['iVersionId']}'>";
            echo "<input type='hidden' name='iSuperMaintainer' value='$iSuperMaintainer'>";

            if($iSuperMaintainer)
                echo '<tr valign="top"><td class="color0"><b>Why you want to and should<br>be an application super maintainer</b></td><td><textarea name="sMaintainReason" rows="15" cols="70"></textarea></td></tr>',"\n";
            else
                echo '<tr valign="top"><td class="color0"><b>Why you want to and should<br>be an application maintainer</b></td><td><textarea name="sMaintainReason" rows="15" cols="70"></textarea></td></tr>',"\n";

            echo '</table>',"\n";
        } else
        {

            //view application details
            echo html_frame_start("New Maintainer Form",600,"",0);
            echo "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";
            echo "<input type=\"hidden\" name=\"iMaintainerId\" ".
                "value=\"$this->iMaintainerId\">";

            /* User name */
            $oSubmitter = new user($this->iUserId);
            echo html_tr(array(
                    array("<b>User name</b>", 'style="text-align:right" class="color0"'),
                    $oSubmitter->objectMakeLink()
                            ));

            /**
            * Show the other maintainers of this application, if there are any
            */
            echo '<tr valign=top><td class=color0 style=\'text-align:right\'><b>Other maintainers</b>';
            /* Fetch maintainers and super maintainers */
            $oVersion = new Version($this->iVersionId);
            $aOtherMaintainers = $oVersion->getMaintainersUserIds();
            $aOtherSuperMaintainers =
                Maintainer::getSuperMaintainersUserIdsFromAppId($this->iAppId);

            if($aOtherMaintainers || $aOtherSuperMaintainers)
                $bFoundMaintainers = true;
            else
                $bFoundMaintainers = false;

            echo "<td>\n";
            /* display maintainers for the version */
            if($aOtherMaintainers)
            {
                while(list($index, $iUserId) = each($aOtherMaintainers))
                {
                    $oUser = new User($iUserId);

                    // because Version::getMaintainersUserIds() includes super maintainers
                    // we need to exclude these from the list of maintainers that we are
                    // building
                    if(!maintainer::isUserSuperMaintainer($oUser, $oVersion->iAppId))
                        echo "$oUser->sRealname<br>\n";
                }
            }

            /* display super maintainers for the given app */

            if($aOtherSuperMaintainers)
            {
                while(list($index, $iUserId) = each($aOtherSuperMaintainers))
                {
                    $oUser = new User($iUserId);
                    echo "$oUser->sRealname*<br>\n";
                }
            }

            if(!$bFoundMaintainers)
            {
                echo "No other maintainers";
            }

            echo "</td></tr>\n";

            // Show which other apps the user maintains
            echo '<tr valign="top"><td class="color0" style=\'text-align:right\'><b>This user also maintains these apps:</b>';
            echo "<br /><a href=\"".BASE."objectManager.php?sClass=maintainerView".
                 "&iId={$this->iUserId}&sTitle=Maintained+Apps\">Detailed view</a>";
            echo '</td><td>',"\n";

            $oUser = new User($this->iUserId);
            $aOtherApps = Maintainer::getAppsMaintained($oUser);
            if($aOtherApps)
            {
                while(list($index, list($iAppIdOther, $iVersionIdOther, $bSuperMaintainerOther)) = each($aOtherApps))
                {
                    $oApp = new Application($iAppIdOther);

                    if($bSuperMaintainerOther)
                        echo $oApp->objectMakeLink()."*<br>\n";
                    else
                        echo $oVersion->fullNameLink($iVersionIdOther)."<br>\n";
                }
            } else
            {
                echo "User maintains no other applications";
            }

            echo "</td></tr>\n";

            $oApp = new Application($this->iAppId);
            $oVersion = new Version($this->iVersionId);

            echo "<input type=\"hidden\" name=\"iAppId\" value=\"".$oApp->iAppId."\">\n";
            echo "<input type=\"hidden\" name=\"iVersionId\" value=\"".$oVersion->iVersionId."\">\n";

            //app name
            echo '<tr valign=top><td class=color0 style=\'text-align:right\'><b>App Name:</b></td>',"\n";
            echo "<td>".$oApp->objectMakeLink()."</td></tr>\n";

            //version
            if($oVersion->iVersionId)
            {
                echo '<tr valign=top><td class=color0 style=\'text-align:right\'><b>App Version:</b></td>',"\n";
                echo "<td>".$oVersion->objectMakeLink()."</td></tr>\n";
            }

            //maintainReason
            echo '<tr valign=top><td class=color0 style=\'text-align:right\'><b>Maintainer request reason:</b></td>',"\n";
            echo '<td><textarea name="sMaintainReason" rows=10 cols=35>'.$this->sMaintainReason.'</textarea></td></tr>',"\n";
            echo '</table>';

            echo html_frame_end("&nbsp;");
        }
    }

    function ObjectGetId()
    {
        return $this->iMaintainerId;
    }

    public function checkOutputEditorInput($aClean)
    {
        $shErrors = '';

        if(!getInput('iAppId', $aClean) && !getInput('iVersionId', $aClean))
            $shErrors .= '<li>No application or version has been selected. This should never happen; please contact <a href="mailto:'.APPDB_OWNER_EMAIL.'">'.APPDB_OWNER_EMAIL.'</a></li>';

        if(!getInput('sMaintainReason', $aClean))
            $shErrors .= '<li>You need to write a short text about why you should be a maintainer</li>';

        return $shErrors;
    }

    function getOutputEditorValues($aClean)
    {
        $this->iAppId = $aClean['iAppId'];
        $this->iVersionId = $aClean['iVersionId'];
        $this->sReplyText = $aClean['sReplyText'];
        $this->sMaintainReason = $aClean['sMaintainReason'];
        $this->bSuperMaintainer = $this->iVersionId ? 0 : 1;

        return TRUE;
    }

    function objectGetItemsPerPage($sState = 'accepted')
    {
        $aItemsPerPage = array(25, 50, 100, 200);
        $iDefaultPerPage = 25;
        return array($aItemsPerPage, $iDefaultPerPage);
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

    public function isDuplicate()
    {
        $hResult = query_parameters("SELECT COUNT(maintainerId) as count FROM appMaintainers
                                    WHERE versionId = '?' and appId = '?' AND userId = '?' AND maintainerId != '?'",
                                    $this->iVersionId, $this->iAppId, $this->iUserId, $this->iMaintainerId);

        if(!$hResult)
            return false;

        $oRow = query_fetch_object($hResult);

        return $oRow->count > 0;
    }

    public function update()
    {
        $oMaintainer = new maintainer($this->iMaintainerId);

        if($this->isDuplicate())
            return $this->delete();

        if($this->iVersionId && $oMaintainer->iVersionId != $this->iVersionId)
        {
            $hResult = query_parameters("UPDATE appMaintainers SET versionId = '?' 
                                         WHERE maintainerId = '?'",
                                         $this->iVersionId, $this->iMaintainerId);

            if(!$hResult)
                return FALSE;
        }

        if($this->iAppId && $oMaintainer->iAppId != $this->iAppId)
        {
            $hResult = query_parameters("UPDATE appMaintainers SET appId = '?' 
                                         WHERE maintainerId = '?'",
                                         $this->iAppId, $this->iMaintainerId);

            if(!$hResult)
                return FALSE;
        }

        return TRUE;
    }

    function getDefaultReply()
    {
        $sReplyTextHelp = "Enter a personalized reason for accepting or rejecting the ".
                          "user's maintainer request here";

        return $sReplyTextHelp;
    }

    function objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction)
    {
        return new mailOptions();
    }

    function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        $oSubmitter = new user($this->iUserId);

        $sVerb = $this->sState == 'queued' ? 'rejected' : 'removed';

        if($this->bSuperMaintainer)
        {
            $oApp = new application($this->iAppId);
            $sFor = $oApp->sName;
        } else
        {
            $sFor = version::fullName($this->iVersionId);
        }

        $sMsg = null;
        $sSubject = null;

        if($bMailSubmitter)
        {
            switch($sAction)
            {
                case "delete":
                    $sSubject = "Maintainership for $sFor $sVerb";
                    if($this->sState == 'queued')
                    {
                        $sMsg = "Your request to be a maintainer of '$sFor'".
                                    " has been denied.";
                    } else
                    {
                        $sMsg = "You have been removed as a maintainer of ".
                                    "'$sFor'.";
                    }
                break;
            }
            $aMailTo = null;
        } else
        {
            switch($sAction)
            {
                case "delete":
                    if(!$bParentAction)
                    {
                        $sSubject = "Maintainership for $sFor $sVerb";
                        if($this->sState == 'accepted')
                        {
                            $sMsg = $oSubmitter->sRealname." has been removed as a ".
                                        "maintainer of $sFor.";
                        } else
                        {
                            $sMsg = $oSubmitter->sRealname."'s request to be a maintainer ".
                                        " of $sFor has been rejected.";
                        }
                    }
                break;
            }
            $aMailTo = User::get_notify_email_address_list(null, null);
        }
        return array($sSubject, $sMsg, $aMailTo);
    }

    function objectGetSubmitterId()
    {
        return $this->iUserId;
    }

    function objectHideDelete()
    {
        return TRUE;
    }

    function display()
    {
        /* STUB: There is not much use for this, but it may be implemented later */
        return TRUE;
    }

    function objectMakeUrl()
    {
        /* STUB: There is not much use for this, but it may be implemented later */
        return TRUE;
    }

    function objectMakeLink()
    {
        /* STUB: There is not much use for this, but it may be implemented later */
        return TRUE;
    }

    /* Delete a user's non-super maintainer entries for an application.  This is useful
       to ensure that the user has no maintainer entries for an app he supermaintains */
    function removeUserFromAppVersions()
    {
        $sQuery = "DELETE FROM appMaintainers WHERE
                superMaintainer = '0'
                AND
                appId = '?'
                AND
                userId = '?'";
        $hResult = query_parameters($sQuery, $this->iAppId, $this->iUserId);

        if(!$hResult)
            return FALSE;

        return TRUE;
    }

    function allowAnonymousSubmissions()
    {
        return FALSE;
    }

    function fetchNotificationUpdate()
    {
      $bDebugOutputEnabled = false;

      if($bDebugOutputEnabled)
        echo "notifyMaintainerOfQueuedData()\n";

      // if a maintainer has an non-zero warning level
      // has it been enough days since we last warned them to warrent
      // checking to see if we should warn them again?
      if($this->iNotificationLevel != 0)
      {
        $iLastWarnTime = strtotime($this->sNotificationTime);
        $iLastWarnAgeInSeconds = strtotime("now") - $iLastWarnTime;
                                                                        
        if($bDebugOutputEnabled)
          echo "iLastWarnAgeInSeconds: ".$iLastWarnAgeInSeconds."\n";
 
        // if it hasn't been at least $iNotificationIntervalDays since the last
        // warning we can skip even checking the user
        if($iLastWarnAgeInSeconds < (iNotificationIntervalDays * 24 * 60 * 60))
        {
          if($bDebugOutputEnabled)
            echo "iNotificationIntervalDays has not elapsed, skipping checking the user\n";
          return null;
        }
      }

      if($bDebugOutputEnabled)
        echo "notification level is: ".$this->iNotificationLevel."\n";

      // instantiate the user so we can retrieve particular values
      $oUser = new User($this->iUserId);

      if($bDebugOutputEnabled)
      {
        echo "this->iUserId: ".$this->iUserId."\n";
        print_r($oUser);
      }

      // get the time the user signed up
      // if queued entries have been queued since before the user signed up
      // we can't punish them for this fact
      $iMaintainerSignupTime = strtotime($this->aSubmitTime);

      if($bDebugOutputEnabled)
        echo "iMaintainerSignupTime: ".$iMaintainerSignupTime."\n";

      // store the oldest queued entry
      $iOldestQueuedEntryTime = strtotime("now");

      // construct the subject of the notification email that we may send
      $sSubject = "Notification of queued data for ";
      $sMsg = "";
      $sMsg.= "Hello ".$oUser->sRealname."<".$oUser->sEmail.">".".\n\n";
      $sMsg.= "You are receiving this email to notify you that there is queued data";
      $sMsg.=" for the ";
      if($this->bSuperMaintainer)
      {
        $oApp = new Application($this->iAppId);
        $sSubject.= $oApp->sName;
        $sMsg.='application, '.$oApp->sName.'('.$oApp->objectMakeUrl().'), that you maintain.'."\n\n";
      } else
      {
        $sFullname = version::fullName($this->iVersionId);
        $oVersion = new Version($this->iVersionId);
        $sSubject.= $sFullname;
        $sMsg.='version, '.$sFullname.'('.$oVersion->objectMakeUrl().'), that you maintain.'."\n\n";
      }
      $sSubject.=" ready for your processing";

      // retrieve the queued entries
      $oQueuedEntries = new queuedEntries($this);
      $oQueuedEntries->retrieveQueuedEntries();
      if($oQueuedEntries->isEmpty())
      {
        if($bDebugOutputEnabled)
          echo "No entries, returning\n";

        //no entries, we might as well return here
        return;
      }

      // process each of the queued versions
      foreach($oQueuedEntries->aVersionIds as $iVersionId)
      {
        $oVersion = new Version($iVersionId);
          
        $sMsg .= 'Version '.$sFullname.' ('.$oVersion->objectMakeUrl().'">'.$sFullname.
          ') is queued and ready for processing.';

        $iSubmitTime = strtotime($oVersion->sSubmitTime);

        // is this submission time older than the oldest queued time?
        // if so this is the new oldest time
        if($iSubmitTime < $iOldestQueuedEntryTime)
        {
          $iOldestQueuedEntryTime = $iSubmitTime;
        }
      }

      if(count($oQueuedEntries->aVersionIds) != 0)
      {
        // FIXME: should use a function to generate these urls and use it here and
        // in sidebar_maintainer.php and sidebar_admin.php
        $sMsg = 'Please visit the version queue ('.APPDB_ROOT."objectManager.php?sClass=version_queue&amp;bIsQueue=true&amp;sTitle=".
          'Version%20Queue) to process queued versions for applications you maintain.'."\n";
      }

      //////////////////
      // queued testdata

      // go through the test results looking for the oldest queued data
      foreach($oQueuedEntries->aTestDataIds as $iTestDataId)
      {
        if($bDebugOutputEnabled)
          echo "Testresult found\n";
        $oTestData = new TestData($iTestDataId);

        $iSubmitTime = strtotime($oTestData->sSubmitTime);
        if($bDebugOutputEnabled)
          echo "iSubmitTime is ".$iSubmitTime."\n";

        // is this submission time older than the oldest queued time?
        // if so this is the new oldest time
        if($iSubmitTime < $iOldestQueuedEntryTime)
        {
          if($bDebugOutputEnabled)
            echo "setting new oldest time\n";

          $iOldestQueuedEntryTime = $iSubmitTime;
        }
      }

      $iTestResultCount = count($oQueuedEntries->aTestDataIds);
      if($iTestResultCount != 0)
      {
        // grammar is slightly different for singular vs. plural
        if($iTestResultCount == 1)
          $sMsg.="There is $iTestResultCount queued test result. ";
        else
          $sMsg.="There are $iTestResultCount queued test results. ";

        // FIXME: should use a function to generate these urls and use it here and
        // in sidebar_maintainer.php and sidebar_admin.php
        $sMsg .= 'Please visit the AppDB Test Data Queue'.
          '('.APPDB_ROOT.'objectManager.php?sClass=testData_queue&amp;'.
          'bIsQueue=true&amp;sTitle=Test%20Results%20Queue)'.
          ' to process queued test data for versions you maintain.'."\n";
      }
      // queued testdata
      //////////////////


      ////////////////////
      // queued screenshots
      foreach($oQueuedEntries->aScreenshotIds as $iScreenshotId)
      {
        $oScreenshot = new Screenshot($iScreenshotId);

        $iSubmitTime = strtotime($oScreenshot->sSubmitTime);

        // is this submission time older than the oldest queued time?
        // if so this is the new oldest time
        if($iSubmitTime < $iOldestQueuedEntryTime)
        {
          $iOldestQueuedEntryTime = $iSubmitTime;
        }
      }

      // if the oldest queue entry time is older than the time the maintainer
      // signed up, use the maintainer signup time as the oldest time
      if($iOldestQueuedEntryTime < $iMaintainerSignupTime)
        $iOldestQueuedEntryTime = $iMaintainerSignupTime;

      $iScreenshotCount = count($oQueuedEntries->aScreenshotIds);
      // if we found any queued screenshots add the screenshot queue processing link
      // to the email
      if($iScreenshotCount != 0)
      {
        if($iScreenshotCount == 1)
          $sMsg.= 'There is one queued screenshot waiting to be processed. ';
        else
          $sMsg.= "There are $iScreenshotCount screenshots waiting to be processed. ";

        // FIXME: should use a function to generate these urls and use it here and
        // in sidebar_maintainer.php and sidebar_admin.php
        $sMsg.= 'Please visit the screenshot queue(';
        $sMsg.= APPDB_ROOT.'objectManager.php?sClass=screenshot&amp;bIsQueue=true&amp;sTitle=Screenshot%20Queue) ';
        $sMsg.= 'to process queued screenshots for versions you maintain.'."\n";
      }

      // queued screenshots
      //////////////////////

      // compute the age in seconds of the oldest entry
      $iAgeInSeconds = strtotime("now") - $iOldestQueuedEntryTime;

      // compute the target warning level based on the age and the notification interval
      // we divide the age by the number of seconds in a day multiplied by the days per notification interval
      $iTargetLevel = (integer)($iAgeInSeconds / (iNotificationIntervalDays * 24 * 60 * 60));

      if($bDebugOutputEnabled)
      {
        echo "iOldestQueuedEntryTime is $iOldestQueuedEntryTime\n";
        echo "iAgeInSeconds is $iAgeInSeconds\n";
        echo "iNotificationIntervalDays is ".iNotificationIntervalDays."\n";
        echo "strtotime(now) is ".strtotime("now")."\n";
        echo "iTargetLevel is ".$iTargetLevel."\n";
      }

      $oNotificationUpdate = new notificationUpdate($oUser->sEmail, $sSubject,
                                                    $sMsg, $iTargetLevel);

      return $oNotificationUpdate;
    }

    // level 0 is from 0 to iNotificationIntervalDays
    // level 1 is from (iNotificationIntervalDays + 1) to (iNotificationIntervalDays * 2)
    // level 2 is from (iNotificationIntervalDays * 2 + 1) to (iNotificationIntervalDays * 3)
    // level 3 is beyond (iNotificationIntervalDays * 3)
    function processNotificationUpdate($oNotificationUpdate)
    {
      $bDebugOutputEnabled = false;

      // if the target level is less than the current level, adjust the current level
      // This takes into account the entries in the users queue that may have been processed
      if($oNotificationUpdate->iTargetLevel < $this->iNotificationLevel)
      {
        if($bDebugOutputEnabled)
          echo "Using iTargetLevel of $oNotificationUpdate->iTargetLevel\n";
        $this->iNotificationLevel = $oNotificationUpdate->iTargetLevel;
      }

      // if the target level is higher than the current level then adjust the
      // current level up by 1
      // NOTE: we adjust up by one because we want to ensure that we go through
      //       notification levels one at a time
      if($oNotificationUpdate->iTargetLevel > $this->iNotificationLevel)
      {
        if($bDebugOutputEnabled)
          echo "Increasing notification level of $this->iNotificationLevel by 1\n";
        $this->iNotificationLevel++;
      }

      switch($this->iNotificationLevel)
      {
      case 0: // lowest level, no notification
        // nothing to do here
        break;
      case 1: // send the first notification
        // nothing to do here, the first notification is just a reminder
        break;
      case 2: // send the second notification, notify them that if the queued entries aren't
              // processed after another $iNotificationIntervalDays that
              // we'll have to remove their maintainership for this application/version
              // so a more active person can fill the spot
        $oNotificationUpdate->sMsg.= "\nThis your second notification of queued entries.";
        $oNotificationUpdate->sMsg.= " If the queued entries are not processsed within";
        $oNotificationUpdate->sMsg.= " the next ".iNotificationIntervalDays. " days we will";
        $oNotificationUpdate->sMsg.= " remove your maintainership for this application/version";
        $oNotificationUpdate->sMsg.= " so a more active person can fill the spot.".
				     " It's important to process queued items in a timely manner".
				     " to provide a good user experience.";
        break;
      case 3: // remove their maintainership and notify the maintainer why we are doing so
        $oNotificationUpdate->sMsg.= "\nThis your third notification of queued entries.";
        $oNotificationUpdate->sMsg.= " Because your queued entries have not been processed";
        $oNotificationUpdate->sMsg.= " after two notifications we are removing your maintainer";
        $oNotificationUpdate->sMsg.= " role for this application/version. Removing inactive";
        $oNotificationUpdate->sMsg.= " maintainers makes our job easier, since we want to know"
				     ." not to wait for a maintainer to oversee submissions.";
        $oNotificationUpdate->sMsg.= " If you are still interested in being a maintainer please";
        $oNotificationUpdate->sMsg.= " submit a maintainer request.";
        $this->delete(); // delete ourselves from the database
        break;
      }
      
      // common end of our message
      $oNotificationUpdate->sMsg.= "\n\nThanks,\n";
      $oNotificationUpdate->sMsg.= "Appdb Admins\n";

      // save the notification level and notification time back into the database
      $sQuery = "update appMaintainers set notificationLevel = '?', notificationTime = ?".
        " where maintainerId = '?'";
      query_parameters($sQuery, $this->iNotificationLevel, "NOW()", $this->iMaintainerId);

      //TODO: we probably want to copy the mailing list on each of these emails
      $oNotificationUpdate->sEmail.=" cmorgan@alum.wpi.edu"; // FIXME: for debug append my email address

      // we don't send any emails if the notification level is zero
      if($this->iNotificationLevel == 0)
      {
        if($bDebugOutputEnabled)
          echo "At level 0, no warning issued to ".$oUser->sEmail."\n";
      } else
      {
        if($bDebugOutputEnabled)
        {
          echo "Email: ".$oNotificationUpdate->sEmail."\n";
          echo "Subject: ".$oNotificationUpdate->sSubject."\n";
          echo "Msg: ".$oNotificationUpdate->sMsg."\n\n";
        }

        mail_appdb($oNotificationUpdate->sEmail, $oNotificationUpdate->sSubject,
                   $oNotificationUpdate->sMsg);
      }
    }

    function notifyMaintainerOfQueuedData()
    {
      $oNotificationUpdate = $this->fetchNotificationUpdate();

      // if we have a valid notificationUpdate then process it, otherwise skip it
      if($oNotificationUpdate != NULL)
        $this->processNotificationUpdate($oNotificationUpdate);
    }

    // static method called by the cron maintenance script to notify
    // maintainers of data pending for their applications and versions
    //TODO: php5 make this static when we have php5
    function notifyMaintainersOfQueuedData()
    {
      // retrieve all of the maintainers
      $hResult = maintainer::objectGetEntries('accepted');

      //      echo "Processing ".query_num_rows($hResult)." maintainers\n";

      // notify this user, the maintainer, of queued data, if any exists
      while($oRow = query_fetch_object($hResult))
      {
        $oMaintainer = new maintainer(null, $oRow);
        $oMaintainer->notifyMaintainerOfQueuedData();
      }
    }
}

?>
