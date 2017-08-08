<?php
/************************************/
/* user class and related functions */
/************************************/

require_once(BASE."include/version.php");
require_once(BASE."include/maintainer.php");
require_once(BASE."include/util.php");

define("SUCCESS", 0);
define("USER_CREATE_EXISTS", 1);
define("USER_CREATE_FAILED", 2);
define("USER_LOGIN_FAILED", 3);
define("USER_UPDATE_FAILED", 4);
define("USER_UPDATE_FAILED_EMAIL_EXISTS", 5); /* user updating to an email address that is already in use */
define("USER_UPDATE_FAILED_NOT_LOGGED_IN", 6); /* user::update() called but user not logged in */

/**
 * User class for handling users
 */
class User {
    var $iUserId;
    var $sEmail;
    var $sRealname;
    var $sStamp;
    var $sDateCreated;
    var $sWineRelease;
    var $bInactivityWarned;

    /**
     * Constructor.
     * If $iUserId is provided, logs in user.
     */
    function User($iUserId="")
    {
        $this->sRealname = "an anonymous user";
        if(is_numeric($iUserId))
        {
            $sQuery = "SELECT *
                       FROM user_list
                       WHERE userId = '?'";
            $hResult = query_parameters($sQuery, $iUserId);
            $oRow = query_fetch_object($hResult);
            if($oRow)
            {
                $this->iUserId = $oRow->userid;
                $this->sEmail = $oRow->email;
                $this->sRealname = $oRow->realname;
                $this->sStamp = $oRow->stamp;
                $this->sDateCreated = $oRow->created;
                $this->sWineRelease = $oRow->CVSrelease;
                $this->bInactivityWarned = $oRow->inactivity_warned;
            }
        }
        return $this->isLoggedIn();
    }


    /**
     * Logs in an user using e-mail and password.
     */
    function login($sEmail, $sPassword)
    {
        $sQuery = "SELECT *
                   FROM user_list
                   WHERE email = '?' AND password = ";

        $sMysqlSHAPasswordPart = "SHA1('?');";
        $sMysqlPasswordPart = "password('?');";
        $sMysql40xPasswordPart = "old_password('?');";

        // if true we used an old style password and we need to
        // update the users password to the new style
        $bUsedOldStylePassword = false;

        $oRow = null; // null out the row object

        // if we aren't logged in yet
        // try to login with the mysql sha1() value of the password
        if(!$oRow)
        {
            $hResult = query_parameters($sQuery.$sMysqlSHAPasswordPart,
                                      $sEmail, $sPassword);
            $oRow = query_fetch_object($hResult);
        }

        // if we aren't logged in yet
        // try to login with the mysql password() value of the password
        if(!$oRow)
        {
            $hResult = query_parameters($sQuery.$sMysqlPasswordPart,
                                        $sEmail, $sPassword);
            $oRow = query_fetch_object($hResult);
            if($oRow) $bUsedOldStylePassword = true;
        }

        // if we aren't logged in yet
        // try to login with the mysql old_password() value of the password
        if(!$oRow)
        {
            // make sure we have a newer version, older versions may not
            $sResult = query_get_server_info();
            $fVersion = substr($sResult, 0, 3);

            // if we have a newer version of mysql, try with the 'old_password()' function
            if($fVersion >= 4.1)
            {
                $hResult = query_parameters($sQuery.$sMysql40xPasswordPart,
                                            $sEmail, $sPassword);
                $oRow = query_fetch_object($hResult);
                if($oRow) $bUsedOldStylePassword = true;
            }
        }

        if($oRow)
        {
            $this->iUserId = $oRow->userid;
            $this->sEmail = $oRow->email;
            $this->sRealname = $oRow->realname;
            $this->sStamp = $oRow->stamp;
            $this->sDateCreated = $oRow->created;
            $this->sWineRelease = $oRow->CVSrelease;

            // if we used an old style password, update the users password
            if($bUsedOldStylePassword)
            {
                $this->update_password($sPassword);
            }
        }

        if($this->isLoggedIn())
        {
            // Update timestamp and clear the inactivity flag if it was set
            query_parameters("UPDATE user_list SET stamp = ?, inactivity_warned = '?' WHERE userid='?'",
                             "NOW()", "false", $this->iUserId);

            /* set the session variable for the current user to this user object */
            $_SESSION['current'] = $this;

            return SUCCESS;
        }

        /* destroy all session variables since we failed to login */
        $GLOBALS['session']->destroy();

        return USER_LOGIN_FAILED;
    }

    function logout()
    {
        /* destroy all session variables since we are logging out */
        $GLOBALS['session']->destroy();
    }


    /*
     * Creates a new user.
     * returns SUCCESS on success, USER_CREATE_EXISTS if the user already exists
     */
    function create($sEmail, $sPassword, $sRealname, $sWineRelease)
    {
        if(User::exists($sEmail))
        {
            return USER_CREATE_EXISTS;
        } else
        {
            $hResult = query_parameters("INSERT INTO user_list (realname, email, CVSrelease, password, stamp,".
                                        "created) VALUES ('?', '?', '?', SHA1('?'), ?, ?)",
                                        $sRealname, $sEmail, $sWineRelease, $sPassword, "NOW()", "NOW()");

            if(!$hResult) return USER_CREATE_FAILED;

            $retval = $this->login($sEmail, $sPassword);
            if($retval == SUCCESS)
                $this->setPref("comments:mode", "threaded"); /* set the users default comments:mode to threaded */
            $this->logout();

            return $retval;
        }
    }


    /**
     * Update User Account;
     */
    function update()
    {
        if(!$this->isLoggedIn()) return USER_UPDATE_FAILED_NOT_LOGGED_IN;

        /* create an instance of ourselves so we can see what has changed */
        $oUser = new User($this->iUserId);

        if($this->sEmail && ($this->sEmail != $oUser->sEmail))
        {
            /* make sure this email isn't already in use */
            if(User::exists($this->sEmail))
            {
                addMsg("An account with this e-mail exists already.","red");
                return USER_UPDATE_FAILED_EMAIL_EXISTS;
            }
            if (!query_parameters("UPDATE user_list SET email = '?' WHERE userid = '?'",
                                  $this->sEmail, $this->iUserId))
                return USER_UPDATE_FAILED;
        }

        if ($this->sRealname && ($this->sRealname != $oUser->sRealname))
        {
            if (!query_parameters("UPDATE user_list SET realname = '?' WHERE userid = '?'",
                             $this->sRealname, $this->iUserId))
                return USER_UPDATE_FAILED;
        }

        if ($this->sWineRelease && ($this->sWineRelease != $oUser->sWineRelease))
        {
            if (!query_parameters("UPDATE user_list SET CVSrelease = '?' WHERE userid = '?'",
                                  $this->sWineRelease, $this->iUserId))
                return USER_UPDATE_FAILED;
        }

        return SUCCESS;
    }

    /** 
     * NOTE: we can't update the users password like we can update other
     * fields such as their email or username because the password is hashed
     * in the database so we can't keep the users password in a class member variable
     * and use update() because we can't check if the password changed without hashing
     * the newly supplied one
     */
    function update_password($sPassword)
    {
        if($sPassword)
        {
            if (query_parameters("UPDATE user_list SET password = SHA1('?') ".
                                 "WHERE userid = '?'",
                                 $sPassword, $this->iUserId))
                return true;
        }

        return false;
    }


    /**
     * Removes the current, or specified user and preferences from the database.
     * returns true on success and false on failure.
     */
    function delete()
    {
        $hResult2 = query_parameters("DELETE FROM user_privs WHERE userid = '?'", $this->iUserId);
        $hResult3 = query_parameters("DELETE FROM user_prefs WHERE userid = '?'", $this->iUserId);
        $hResult4 = query_parameters("DELETE FROM appVotes WHERE userid = '?'", $this->iUserId);
        $hResult5 = Maintainer::deleteMaintainer($this);
        $hResult6 = query_parameters("DELETE FROM appComments WHERE userId = '?'", $this->iUserId);
        return($hResult = query_parameters("DELETE FROM user_list WHERE userid = '?'", $this->iUserId));
    }


    /**
     * Get a preference for the current user.
     */
    function getPref($sKey, $sDef = null)
    {
        if(!$this->isLoggedIn() || !$sKey)
            return $sDef;

        $hResult = query_parameters("SELECT * FROM user_prefs WHERE userid = '?' AND name = '?'",
                                $this->iUserId, $sKey);
        if(!$hResult || query_num_rows($hResult) == 0)
            return $sDef;
        $oRow = query_fetch_object($hResult);
        return $oRow->value; 
    }


    /**
     * Set a preference for the current user.
     */
    function setPref($sKey, $sValue)
    {
        if(!$this->isLoggedIn() || !$sKey || !$sValue)
            return false;

        $hResult = query_parameters("DELETE FROM user_prefs WHERE userid = '?' AND name = '?'",
                                    $this->iUserId, $sKey);
        $hResult = query_parameters("INSERT INTO user_prefs (userid, name, value) VALUES".
                                    "('?', '?', '?')", $this->iUserId, $sKey, $sValue);
        return $hResult;
    }


    /**
     * Check if this user has $priv.
     */
    function hasPriv($sPriv)
    {
        if(!$this->isLoggedIn() || !$sPriv)
            return false;

        $hResult = query_parameters("SELECT * FROM user_privs WHERE userid = '?' AND priv  = '?'",
                                $this->iUserId, $sPriv);
        if(!$hResult)
            return false;
        return query_num_rows($hResult);
    }


    /* Check if this user is a maintainer of a given appId/versionId */
    function isMaintainer($iVersionId=null)
    {
        return Maintainer::isUserMaintainer($this, $iVersionId);
    }

    /* Check if this user is a maintainer of a given appId/versionId */
    function isSuperMaintainer($iAppId=null)
    {
        return Maintainer::isUserSuperMaintainer($this, $iAppId);
    }

    function addPriv($sPriv)
    {
        if(!$this->isLoggedIn() || !$sPriv)
            return false;

        if($this->hasPriv($sPriv))
            return true;

        $hResult = query_parameters("INSERT INTO user_privs (userid, priv) VALUES".
                                    " ('?', '?')", $this->iUserId, $sPriv);
        return $hResult;
    }


    function delPriv($sPriv)
    {
        if(!$this->isLoggedIn() || !$sPriv)
            return false;

        $hResult = query_parameters("DELETE FROM user_privs WHERE userid = '?' AND priv = '?'",
                                 $this->iUserId, $sPriv);
        return $hResult;
    }


    /**
     * Checks if the current user is valid.
     */
    function isLoggedIn()
    {
        return $this->iUserId;
    }


    /**
     * Checks if user should see debugging infos.
     */
     function showDebuggingInfos()
     {
         return (($this->isLoggedIn() && $this->getPref("debug") == "yes") || APPDB_DEBUG == 1);
     }


    /**
     * Checks if user wants to get e-mails.
     */    
     function wantsEmail()
     {
         return ($this->isLoggedIn() && $this->getPref("send_email","yes")=="yes");
     }

     function isAppSubmitter($iAppId)
     {
         $hResult = query_parameters("SELECT appId FROM appFamily
                                      WHERE submitterId = '?'
                                      AND appId = '?'",
                                     $this->iUserId, $iAppId);
         if(query_num_rows($hResult))
             return true;
         else
             return false;
     }

     function isVersionSubmitter($iVersionId)
     {
         $hResult = query_parameters("SELECT appVersion.versionId FROM appVersion, appFamily
                                  WHERE appFamily.appId = appVersion.appId 
                                  AND appVersion.submitterId = '?'
                                  AND appVersion.versionId = '?'",
                                 $this->iUserId, $iVersionId);
         if(query_num_rows($hResult))
             return true;
         else
             return false;
    }

     /* if this user has data associated with them we will return true */
     /* otherwise we return false */
     function hasDataAssociated()
     {
         $hResult = query_parameters("SELECT count(userId) as c FROM appComments WHERE userId = '?'",
                                 $this->iUserId);
         $oRow = query_fetch_object($hResult);
         if($oRow->c != 0) return true;

         if($this->isMaintainer() || $this->isSuperMaintainer())
             return true;

         $hResult = query_parameters("SELECT count(userId) as c FROM appVotes WHERE userId = '?'",
                                 $this->iUserId);
         $oRow = query_fetch_object($hResult);
         if($oRow->c != 0) return true;

         return false;
     }


     /**
      * Creates a new random password.
      */
     public static function generate_passwd($pass_len = 10)
     {
         $nps = "";
         mt_srand ((double) microtime() * 1000000);
         while (strlen($nps)<$pass_len)
         {
             $c = chr(mt_rand (0,255));
             if (preg_match("/[a-zA-Z0-9]/", $c)) $nps = $nps.$c;
         }
         return ($nps);
     }

     /**
      * Check if a user exists.
      * returns the userid if the user exists
      */
     public static function exists($sEmail)
     {
         $hResult = query_parameters("SELECT userid FROM user_list WHERE email = '?'",
                                     $sEmail);
         if(!$hResult || query_num_rows($hResult) != 1)
         {
             return 0;
         } else
         {
             $oRow = query_fetch_object($hResult);
             return $oRow->userid;
         }
     }

     /**
      * Get the number of users in the database
      * The parameters are only included for compatibility; we don't queue users 
      */
     public static function objectGetEntriesCount($bQueued = null, $bRejected = null)
     {
         $hResult = query_parameters("SELECT count(*) as num_users FROM user_list;");
         $oRow = query_fetch_object($hResult);
         return $oRow->num_users;
     }

     /**
      * Get the number of active users within $days of the current day
      */
     public static function active_users_within_days($days)
     {
         $hResult = query_parameters("SELECT count(*) as num_users FROM user_list WHERE stamp >= DATE_SUB(CURDATE(), interval '?' day);",
                                     $days);
         $oRow = query_fetch_object($hResult);
         return $oRow->num_users;
     }

     /**
      * Get the email address of people to notify for this appId and versionId.
      */
     public static function get_notify_email_address_list($iAppId = null, $iVersionId = null)
     {
         $aUserId = array();
         $sRetval = "";

         /*
          * Retrieve version maintainers.
          */
         $hResult = Maintainer::getMaintainersForAppIdVersionId($iAppId, $iVersionId);

         if($hResult)
         {
             if(query_num_rows($hResult) > 0)
             {
                 while($oRow = query_fetch_object($hResult))
                     $aUserId[] = $oRow->userId;
             }
         }

         /*
          * Retrieve version Monitors.
          */
         /*
          * If versionId was supplied we fetch superMonitors of application and Monitors of version.
          */
         if($iVersionId)
         {
             $hResult = query_parameters("SELECT appMonitors.userId 
                                 FROM appMonitors, appVersion
                                 WHERE appVersion.appId = appMonitors.appId 
                                 AND appVersion.versionId = '?'",
                                         $iVersionId);
         } 
         /*
          * If versionId was not supplied we fetch superMonitors of application and Monitors of all versions.
          */
         elseif($iAppId)
         {
             $hResult = query_parameters("SELECT userId 
                                 FROM appMonitors
                                 WHERE appId = '?'",
                                         $iAppId);
         }
         if($hResult)
         {
             if(query_num_rows($hResult) > 0)
             {
                 while($oRow = query_fetch_object($hResult))
                     $aUserId[] = $oRow->userId;
             }
         }

         /*
          * Retrieve administrators.
          */
         $hResult = query_parameters("SELECT * FROM user_privs WHERE priv  = 'admin'");
         if(query_num_rows($hResult) > 0)
         {
             while($oRow = query_fetch_object($hResult))
             {
                 $i = array_search($oRow->userid, $aUserId);

                 // if we didn't find this entry in the $aUserId
                 // array then we should add it if the admin has
                 // enabled global emails.
                 $oAdmin = new user($oRow->userid);
                 if($i === false && $oAdmin->getPref('disable_global_emails','no') == 'no')                   
                     $aUserId[] = $oRow->userid;
             }
         }

         // go through the email entries and only add the emails for the users
         // that want to receive it
         if (sizeof($aUserId) > 0)
         {
             foreach($aUserId as $iUserId)
             {
                 $oUser = new User($iUserId);
                 if ($oUser->wantsEmail())
                     $sRetval .= $oUser->sEmail." ";
             }
         }

         return $sRetval;
     }
     
    public static function getAdminEmails()
    {
        $sRecipients = '';
        $sQuery = "SELECT user_list.email FROM user_privs, user_list, user_prefs 
            WHERE user_privs.userid = user_list.userId 
            AND user_privs.userId = user_prefs.userId 
            AND user_privs.priv = 'admin' 
            AND user_prefs.name = 'send_email' 
            AND user_prefs.value = 'yes'";
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


     /************************/
     /* Permission functions */
     /************************/

     /**
      * Returns true or false depending on whether the user can view the image
      */
     function canViewImage($iImageId)
     {
         $oScreenshot = new Screenshot($iImageId);

         if(!$oScreenshot->bQueued ||
            ($oScreenshot->bQueued && ($this->hasPriv("admin") ||
                                       $this->isMaintainer($oScreenshot->iVersionId) ||
                                       $this->isSuperMaintainer($oScreenshot->iAppId) ||
                                       $this->iUserId == $oScreenshot->iSubmitterId)))
             return true;

         return false;
     }

     /***************************/
     /* application permissions */
     function canViewApplication($oApp)
     {
         /* If there is no id the app is not in the database, which means the current user must have created the object */
         if(!$oApp->iAppId)
             return true;

         /* if the application isn't queued */
         if($oApp->objectGetState() == 'accepted')
             return true;

         if($this->hasPriv("admin"))
             return true;

         /* if this user is the submitter and the application is queued */
         if(($this->iUserId == $oApp->iSubmitterId) &&
            ($oApp->objectGetState() != 'accepted'))
             return true;

         return false;
     }

     /**
      * Does the user have permission to modify this application?
      */
     function canModifyApplication($oApp)
     {
         if($this->hasPriv("admin"))
             return true;

         /* is this user a super maintainer of this app? */
         if($this->isSuperMaintainer($oApp->iAppId))
             return true;
         
         /* if the user is the submitter of the application */
         /* and the application is still queued */
         /* the user can modify the app */
         if(($this->iUserId == $oApp->iSubmitterId) &&
            ($oApp->objectGetState() != 'accepted'))
             return true;

         return false;
     }

     /**
      * Can this user create applications?
      */
     function canCreateApplication()
     {
         return $this->isLoggedIn();
     }

     /**
      * Returns 'true' if the current user has the permission to delete
      * this application, 'false' otherwise
      */
     function canDeleteApplication($oApp)
     {
         if($this->hasPriv("admin"))
             return true;

         /* is this the user that submitted the application and is still queued */
         if(($oApp->objectGetState() != 'accepted') && ($oApp->iSubmitterId == $this->iUserId))
             return true;

         return false;
     }

     /* Can this user unQueue applications? */
     function canUnQueueApplication()
     {
         return $this->hasPriv("admin");
     }

     /* Can this user Requeue an application? */
     function canRequeueApplication($oApp)
     {
         if($oApp->objectGetState() == 'accepted')
             return false;

         if($this->hasPriv("admin"))
             return true;

         if(($oApp->objectGetState() != 'accepted') && ($oApp->iSubmitterId == $this->iUserId))
             return true;

         return false;
     }

     /* Can the user reject application? */
     function canRejectApplication()
     {
         return $this->hasPriv("admin");
     }

     /***********************/
     /* version permissions */

     function canViewVersion($oVersion)
     {
         /* If there is no id that means data must have been generated by the user */
         if(!$oVersion->iVersionId)
             return true;

         /* if the version isn't queued */
         if($oVersion->objectGetState() == 'accepted')
             return true;

         if($this->hasPriv("admin"))
             return true;

         /* if the user is the submitter and the version is still queued */
         if(($this->iUserId == $oVersion->iSubmitterId) &&
            ($oVersion->objectGetState() != 'accepted'))
             return true;

         /* if this user supermaintains the application this version belongs to */
         if($this->isSupermaintainer($oVersion->iAppId))
             return true;

         return false;
     }

     /**
      * Does the user have permission to modify on this version?
      */
     function hasAppVersionModifyPermission($oVersion)
     {
         if(!$this->isLoggedIn())
             return false;

         if($this->hasPriv("admin"))
             return true;

         if($this->isSuperMaintainer($oVersion->iAppId))
             return true;

         if($this->isMaintainer($oVersion->iVersionId))
             return true;

         /* the version is queued and the user is the submitter */
         if(($oVersion->objectGetState() != 'accepted') && ($this->iUserId == $oVersion->iSubmitterId))
             return true;

         return false;
     }

     /**
      * Can this user create a version?
      */
     function canCreateVersion()
     {
         return $this->isLoggedIn();
     }

     /**
      * Returns 'true' if the current user has the permission to delete
      * this version, 'false' otherwise
      */
     function canDeleteVersion($oVersion)
     {
         if($this->hasPriv("admin"))
             return true;

         /* if the app is anything other than not queued and if the user is the submitter */
         /* then allow the user to delete the app */
         if(($oVersion->objectGetState() != 'accepted') && ($oVersion->iSubmitterId == $this->iUserId))
             return true;
         
         /* is this user a supermaintainer of the application this version is under? */
         if($this->isSuperMaintainer($oVersion->iAppId))
             return true;

         return false;
     }

     /**
      * Can the user unqueue this version?
      */
     function canUnQueueVersion($oVersion)
     {
         if($this->hasPriv("admin"))
             return true;
         
         if($this->hasAppVersionModifyPermission($oVersion))
             return true;

         return false;
     }

     /**
      * Can the user reject this version?
      */
     function canRejectVersion($oVersion)
     {
         if($this->hasPriv("admin"))
             return true;

         if($this->hasAppVersionModifyPermission($oVersion))
             return true;

         return false;
     }

     /**
      * Can the user reject this version?
      */
     function canRequeueVersion($oVersion)
     {
         if($this->hasPriv("admin"))
             return true;

         if($this->hasAppVersionModifyPermission($oVersion))
             return true;

         if(($this->iUserId == $oVersion->iSubmitterId) &&
            ($oVersion->objectGetState() != 'accepted'))
             return true;

         return false;
     }

     function objectMakeUrl()
     {
         if(!$this->iUserId)
             return;

         $sUrl = BASE."contact.php?iRecipientId=$this->iUserId";
         return $sUrl;
     }

     function objectMakeLink()
     {
         if(!$this->iUserId)
             return $this->sRealname;

         $sLink = "<a href=\"".$this->objectMakeUrl()."\">$this->sRealname</a>";
         return $sLink;
     }
}

?>
