<?php
/************************************/
/* user class and related functions */
/************************************/


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

    /**
     * Constructor.
     * If $iUserId is provided, logs in user.
     */
    function User($iUserId="")
    {
        if($iUserId)
        {
            $sQuery = "SELECT *
                       FROM user_list
                       WHERE userId = '".$iUserId."'";
            $hResult = query_appdb($sQuery);
            $oRow = mysql_fetch_object($hResult);
            $this->iUserId = $oRow->userid;
            $this->sEmail = $oRow->email;
            $this->sRealname = $oRow->realname;
            $this->sStamp = $oRow->stamp;
            $this->sDateCreated = $oRow->created;
            $this->sWineRelease = $oRow->CVSrelease;
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
                   WHERE email = '".$sEmail."'
                   AND password = password('".$sPassword."')";
        $hResult = query_appdb($sQuery);
        $oRow = mysql_fetch_object($hResult);
        $this->iUserId = $oRow->userid;
        $this->sEmail = $oRow->email;
        $this->sRealname = $oRow->realname;
        $this->sStamp = $oRow->stamp;
        $this->sDateCreated = $oRow->created;
        $this->sWineRelease = $oRow->CVSrelease;
        if($this->isLoggedIn())
        {
            // Update timestamp
            query_appdb("UPDATE user_list SET stamp=null WHERE userid=".$this->iUserId);
            return true;
        }
        return false;
    }


    /*
     * Creates a new user.
     * returns true on success, false on failure
     */
    function create($sEmail, $sPassword, $sRealname, $sWineRelease)
    {
        if(user_exists($sEmail))
        {
            addMsg("An account with this e-mail exists already.","red");
            return false;
        } else
        {
        $aInsert = compile_insert_string(array( 'realname' => $sRealname,
                                                'email' => $sEmail,
                                                'CVSrelease' => $sWineRelease ));

        $sFields = "({$aInsert['FIELDS']}, `password`, `stamp`, `created`)";
        $sValues = "({$aInsert['VALUES']}, password('".$sPassword."'), NOW(), NOW() )";

        query_appdb("INSERT INTO user_list $sFields VALUES $sValues", "Error while creating a new user.");
        return $this->login($sEmail, $sPassword);
        }
    }


    /**
     * Update User Account;
     */
    function update($sEmail = null, $sPassword = null, $sRealname = null, $sWineRelease = null)
    {
        if(!$this->isLoggedIn()) return false;

        if ($sEmail)
        {
            if(user_exists($sEmail) && $sEmail != $this->sEmail)
            {
                addMsg("An account with this e-mail exists already.","red");
                return false;
            }
            if (!query_appdb("UPDATE user_list SET email = '".addslashes($sEmail)."' WHERE userid = ".$this->iUserId))
                return false;
            $this->sEmail = $sEmail;
        }

        if ($sPassword)
        {
            if (!query_appdb("UPDATE user_list SET password = password('$sPassword') WHERE userid = ".$this->iUserId))
                return false;
        }

        if ($sRealname)
        {
            if (!query_appdb("UPDATE user_list SET realname = '".addslashes($sRealname)."' WHERE userid = ".$this->iUserId))
                return false;
            $this->sRealname = $sRealname;
        }

        if ($sWineRelease)
        {
            if (!query_appdb("UPDATE user_list SET CVSrelease = '".addslashes($sWineRelease)."' WHERE userid = ".$this->iUserId))
                return false;
            $this->sWineRelease = $sWineRelease;
        }
        return true;
    }


    /**
     * Removes the current, or specified user and preferences from the database.
     * returns true on success and false on failure.
     */
    function delete()
    {
        if(!$this->isLoggedIn()) return false;
        $hResult2 = query_appdb("DELETE FROM user_privs WHERE id = '".$this->iUserId."'");
        $hResult3 = query_appdb("DELETE FROM user_prefs WHERE id = '".$this->iUserId."'");
        return($hResult = query_appdb("DELETE FROM user_list WHERE id = '".$this->iUserId."'"));
    }


    /**
     * Get a preference for the current user.
     */
    function getPref($sKey, $sDef = null)
    {
        if(!$this->isLoggedIn() || !$sKey)
            return $sDef;

        $hResult = query_appdb("SELECT * FROM user_prefs WHERE userid = ".$this->iUserId." AND name = '$sKey'");
        if(!$hResult || mysql_num_rows($hResult) == 0)
            return $sDef;
        $ob = mysql_fetch_object($hResult);
        return $ob->value; 
    }


    /**
     * Set a preference for the current user.
     */
    function setPref($sKey, $sValue)
    {
        if(!$this->isLoggedIn() || !$sKey || !$sValue)
            return false;

        $hResult = query_appdb("DELETE FROM user_prefs WHERE userid = ".$this->iUserId." AND name = '$sKey'");
        $hResult = query_appdb("INSERT INTO user_prefs VALUES(".$this->iUserId.", '$sKey', '$sValue')");
        return $hResult;
    }


    /**
     * Check if this user has $priv.
     */
    function hasPriv($sPriv)
    {
        if(!$this->isLoggedIn() || !$sPriv)
            return false;

        $hResult = query_appdb("SELECT * FROM user_privs WHERE userid = ".$this->iUserId." AND priv  = '".$sPriv."'");
        if(!$hResult)
            return false;
        return mysql_num_rows($hResult);
    }


    /**
     * Check if this user is a maintainer of a given appId/versionId.
     */
    function isMaintainer($iAppId=null, $iVersionId=null)
    {
        if(!$this->isLoggedIn()) return false;

        /* if this user is a super maintainer of this appid then they */
        /* are a maintainer of all of the versionId's of it as well */
        if($this->isSuperMaintainer($iAppId))
        {
            return true;
        }
        
        if($iAppId && $iVersionId)
        {
            $sQuery = "SELECT * FROM appMaintainers WHERE userid = '".$this->iUserId."' AND appId = '".$iAppId."' AND versionId = '$iVersionId'";
        } else
        {
            $sQuery = "SELECT * FROM appMaintainers WHERE userid = '".$this->iUserId."'";
        }
        $hResult = query_appdb($sQuery);
        if(!$hResult)
            return false;
        return mysql_num_rows($hResult);
    }


    /*
     * Check if this user is a maintainer of a given appId/versionId.
     */
    function isSuperMaintainer($iAppId=null)
    {
        if(!$this->isLoggedIn()) return false;

        if($iAppId)
        {
            $sQuery = "SELECT * FROM appMaintainers WHERE userid = '$this->iUserId' AND appId = '$iAppId' AND superMaintainer = '1'";
        } else
        {
            $sQuery = "SELECT * FROM appMaintainers WHERE userid = '$this->iUserId' AND superMaintainer = '1'";
        }
        $hResult = query_appdb($sQuery);
        if(!$hResult)
            return false;
        return mysql_num_rows($hResult);
    }


    function addPriv($sPriv)
    {
        if(!$this->isLoggedIn() || !$sPriv)
            return false;

        if($this->hasPriv($sPriv))
            return true;

        $hResult = query_appdb("INSERT INTO user_privs VALUES ($this->iUserId, '$sPriv')");
        return $hResult;
    }


    function delPriv($sPriv)
    {
        if(!$this->isLoggedIn() || !$sPriv)
            return false;

        $hRresult = query_appdb("DELETE FROM user_privs WHERE userid = $this->iUserId AND priv = '$sPriv'");
        return $hRresult;
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
     *
     */
     function showDebuggingInfos()
     {
         return (($this->isLoggedIn() && $this->getPref("debug") == "yes") || APPDB_DEBUG == 1);
     }
}


/*
 * User functions that are not part of the class
 */

/**
 * Creates a new random password.
 */
function generate_passwd($pass_len = 10)
{
    $nps = "";
    mt_srand ((double) microtime() * 1000000);
    while (strlen($nps)<$pass_len)
    {
        $c = chr(mt_rand (0,255));
        if (eregi("^[a-z0-9]$", $c)) $nps = $nps.$c;
    }
    return ($nps);
}


/**
 * Get the email address of people to notify for this appId and versionId.
 */
function get_notify_email_address_list($appId, $versionId = 0)
{
    $aUserId = array();
    $c = 0;
    $retval = "";
    if ($versionId == 0)
        $sWhere = "appId = ".$appId;
    else
        $sWhere = "appId = ".$appId." AND versionId = ".$versionId;

    $query = "SELECT userId FROM appMaintainers WHERE ".$sWhere.";";
    $result = query_appdb($query);
    if(mysql_num_rows($result) > 0)
    {
        while($row = mysql_fetch_object($result))
        {
            $aUserId[$c] = array($row->userId);
            $c++;
        }
    }
    $result = query_appdb("SELECT * FROM user_privs WHERE priv  = 'admin'");
    if(mysql_num_rows($result) > 0)
    {
        while($row = mysql_fetch_object($result))
        {
            $i = array_search($row->userid, $aUserId);
            if ($aUserId[$i] != array($row->userid))
            {
                $aUserId[$c] = array($row->userid);
                $c++;
            }
        }

    }
    if ($c > 0)
    {
        while(list($index, list($userIdValue)) = each($aUserId))
        {
            $oUser = new User($userIdValue);
            if ($oUser->getPref("send_email")=="yes")
                $retval .= $oUser->sEmail." ";
        }
    }
    return $retval;
}


/**
 * Get the number of users in the database 
 */
function get_number_of_users()
{
    $result = query_appdb("SELECT count(*) as num_users FROM user_list;");
    $row = mysql_fetch_object($result);
    return $row->num_users;
}


/**
 * Get the number of active users within $days of the current day
 */
function get_active_users_within_days($days)
{
    $result = query_appdb("SELECT count(*) as num_users FROM user_list WHERE stamp >= DATE_SUB(CURDATE(), interval $days day);");
    $row = mysql_fetch_object($result);
    return $row->num_users;
}


/**
 * Check if a user exists.
 * returns TRUE if the user exists
 */
function user_exists($sEmail)
{
    $result = query_appdb("SELECT * FROM user_list WHERE email = '$sEmail'");
    if(!$result || mysql_num_rows($result) != 1)
        return 0;
    return 1;
}
?>
