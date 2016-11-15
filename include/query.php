<?php
/*************************************************/
/* Application Database DB Query Functions       */
/*************************************************/

// AppDB connection link
$hAppdbLink = null;

// bugs connection link
$hBugzillaLink = null;

// set DEADLOCK
define("MYSQL_DEADLOCK_ERRNO", 1213);

function query_appdb($sQuery, $sComment="")
{
    global $hAppdbLink;

    // log query
    appdb_debug("query_appdb -> {$sQuery}");

    // if not connected, connect
    if(!is_resource($hAppdbLink))
    {
        // The last argument makes sure we are really opening a new connection
        $hAppdbLink = mysql_connect(APPS_DBHOST, APPS_DBUSER, APPS_DBPASS, true);
        if(!$hAppdbLink)
          query_error($sQuery, $sComment, $hAppdbLink);
        mysql_select_db(APPS_DB, $hAppdbLink);
    }

    $iRetries = 2;

    /* we need to retry queries that hit transaction deadlocks */
    /* as a deadlock isn't really a failure */
    while($iRetries)
    {
        $hResult = mysql_query($sQuery, $hAppdbLink);
        if(!$hResult)
        {
            /* if this error isn't a deadlock OR if it is a deadlock and we've */
            /* run out of retries, report the error */
            $iErrno = mysql_errno($hAppdbLink);
            if(($iErrno != MYSQL_DEADLOCK_ERRNO) || (($iErrno == MYSQL_DEADLOCK_ERRNO) && ($iRetries <= 0)))
            {
                query_error($sQuery, $sComment, $hAppdbLink);
                return $hResult;
            }

            $iRetries--;
        } else
        {
            return $hResult;
        }
    }

    return NULL;
}

function query_bugzilladb($sQuery, $sComment="")
{
    global $hBugzillaLink;

    // log query
    appdb_debug("query_bugzilladb -> {$sQuery}");

    // if not connected, connect
    if(!is_resource($hBugzillaLink))
    {
        // The last argument makes sure we are really opening a new connection
        $hBugzillaLink = mysql_connect(BUGZILLA_DBHOST, BUGZILLA_DBUSER, BUGZILLA_DBPASS, true);
        if(!$hBugzillaLink)
            return;
        mysql_select_db(BUGZILLA_DB, $hBugzillaLink);
        // Tell MySQL to return UTF8-encoded results
        $sQueryAskingForUtf8Results = "SET SESSION CHARACTER_SET_RESULTS = 'utf8'";
        if (!mysql_query($sQueryAskingForUtf8Results, $hBugzillaLink))
        {
            query_error($sQueryAskingForUtf8Results, "", $hBugzillaLink);
        }
    }

    $hResult = mysql_query($sQuery, $hBugzillaLink);
    if(!$hResult)
        query_error($sQuery, $sComment, $hBugzillaLink);
    return $hResult;
}

/*
 * Wildcard Rules
 * SCALAR  (?) => 'original string quoted'
 * OPAQUE  (&) => 'string from file quoted'
 * MISC    (~) => original string (left 'as-is')
 *
 * NOTE: These rules convienently match those for Pear DB
 *
 * MySQL Prepare Function
 * By: Kage (Alex)
 * KageKonjou@GMail.com
 * http://us3.php.net/manual/en/function.mysql-query.php#53400
 *
 * Modified by CMM 20060622
 *
 * Values are mysql_real_escape_string()'d to prevent against injection attacks
 * See http://php.net/mysql_real_escape_string for more information about why this is the case
 *
 * Usage:
 *  $hResult = query_parameters("Select * from mytable where userid = '?'",
 *                            $iUserId);
 *
 * Note:
 *   Ensure that all variables are passed as parameters to query_parameters()
 *   to ensure that sql injection attacks are prevented against
 *
 */
function query_parameters()
{
    global $hAppdbLink;

    if(!is_resource($hAppdbLink))
    {
        // The last argument makes sure we are really opening a new connection
        $hAppdbLink = mysql_connect(APPS_DBHOST, APPS_DBUSER, APPS_DBPASS, true) or die('Database error, please try again soon: '.mysql_error());
        mysql_select_db(APPS_DB, $hAppdbLink);
    }

    $aData = func_get_args();
    $sQuery = $aData[0];
    $aTokens = preg_split("/[&?~]/", $sQuery); /* NOTE: no need to escape characters inside of [] in regex */
    $sPreparedquery = $aTokens[0];
    $iCount = strlen($aTokens[0]);

    /* do we have the correct number of tokens to the number of parameters provided? */
    if(count($aTokens) != count($aData))
        return NULL; /* count mismatch, return NULL */

    for ($i=1; $i < count($aTokens); $i++)
    {
        $char = substr($sQuery, $iCount, 1);
        $iCount += (strlen($aTokens[$i])+1);
        if ($char == "&")
        {
            $fp = @fopen($aData[$i], 'r');
            $pdata = "";
            if ($fp)
            {
                while (($sBuf = fread($fp, 4096)) != false)
                {
                    $pdata .= $sBuf;
                }
                fclose($fp);
            }
        } else
        {
            $pdata = &$aData[$i];
        }
        $sPreparedquery .= ($char != "~" ? mysql_real_escape_string($pdata) : $pdata);
        $sPreparedquery .= $aTokens[$i];
    }

    return query_appdb($sPreparedquery);
}

function query_error($sQuery, $sComment, $hLink)
{
    error_log("Query: '".$sQuery."' ".
              "mysql_errno(): '".mysql_errno($hLink)."' ".
              "mysql_error(): '".mysql_error($hLink)."' ".
              "comment: '".$sComment."'");
    trigger_error("Database Error: '".mysql_error($hLink)."' ", E_USER_ERROR);
}

function query_fetch_row($hResult)
{
  return mysql_fetch_row($hResult);
}

function query_fetch_object($hResult)
{
  return mysql_fetch_object($hResult);
}

function query_appdb_insert_id()
{
    global $hAppdbLink;
    return mysql_insert_id($hAppdbLink);
}

function query_bugzilla_insert_id()
{
    global $hBugzillaLink;
    return mysql_insert_id($hBugzillaLink);
}

function query_num_rows($hResult)
{
    return mysql_num_rows($hResult);
}

function query_escape_string($sString)
{
    global $hAppdbLink;

    if(!is_resource($hAppdbLink))
    {
        // The last argument makes sure we are really opening a new connection
        $hAppdbLink = mysql_connect(APPS_DBHOST, APPS_DBUSER, APPS_DBPASS, true);
        if(!$hAppdbLink)
          die("Database error, please try again soon.");
        mysql_select_db(APPS_DB, $hAppdbLink);
    }

    return mysql_real_escape_string($sString, $hAppdbLink);
}

function query_field_type($hResult, $iFieldOffset)
{
    return mysql_field_type($hResult, $iFieldOffset);
}

function query_field_name($hResult, $iFieldOffset)
{
    return mysql_field_name($hResult, $iFieldOffset);
}

function query_field_len($hResult, $ifieldOffset)
{
    return mysql_field_len($hResult, $iFieldOffset);
}

function query_field_flags($hResult, $iFieldOffset)
{
    return mysql_field_flags($hResult, $iFieldOffset);
}

function query_fetch_field($hResult, $iFieldOffset)
{
    return mysql_fetch_field($hResult, $iFieldOffset);
}

?>
