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
        $hAppdbLink = new mysqli(APPS_DBHOST, APPS_DBUSER, APPS_DBPASS, APPS_DB);
        if(!$hAppdbLink)
          query_error($sQuery, $sComment, $hAppdbLink);
    }

    $iRetries = 2;

    /* we need to retry queries that hit transaction deadlocks */
    /* as a deadlock isn't really a failure */
    while($iRetries)
    {
        $hResult = $hAppdbLink->query($sQuery);
        if(!$hResult)
        {
            /* if this error isn't a deadlock OR if it is a deadlock and we've */
            /* run out of retries, report the error */
            if(($hAppdbLink->errno != MYSQL_DEADLOCK_ERRNO) || (($hAppdbLink->errno == MYSQL_DEADLOCK_ERRNO) && ($iRetries <= 0)))
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
        $hBugzillaLink = new mysqli(BUGZILLA_DBHOST, BUGZILLA_DBUSER, BUGZILLA_DBPASS, BUGZILLA_DB);
        if(!$hBugzillaLink)
            return;
        // Tell MySQL to return UTF8-encoded results
        $sQueryAskingForUtf8Results = "SET SESSION CHARACTER_SET_RESULTS = 'utf8'";
        if (!$hBugzillaLink->query($sQueryAskingForUtf8Results))
        {
            query_error($sQueryAskingForUtf8Results, "", $hBugzillaLink);
        }
    }

    $hResult = $hBugzillaLink->query($sQuery);
    if(!$hResult)
        query_error($sQuery, $sComment, $hBugzillaLink);
    return $hResult;
}

/*
 * Wildcard Rules
 * SCALAR  (?) => 'original string quoted'
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
 * Modified by JWN 20161115
 *
 * Values are $hAppdbLink->real_escape_string()'d to prevent against injection attacks
 * See http://php.net/$hAppdbLink->real_escape_string for more information about why this is the case
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

    if(empty($hAppdbLink) or !is_resource($hAppdbLink))
    {
        $hAppdbLink = new mysqli(APPS_DBHOST, APPS_DBUSER, APPS_DBPASS, APPS_DB);
        if (!$hAppdbLink)
            query_error('', 'Database connection failed!', $hAppdbLink);
    }

    $aData = func_get_args();
    $sQuery = $aData[0];
    $aTokens = preg_split("/[?~]/", $sQuery); /* NOTE: no need to escape characters inside of [] in regex */
    $sPreparedquery = $aTokens[0];
    $iCount = strlen($aTokens[0]);

    /* do we have the correct number of tokens to the number of parameters provided? */
    if(count($aTokens) != count($aData))
        return NULL; /* count mismatch, return NULL */

    for ($i=1; $i < count($aTokens); $i++)
    {
        $char = substr($sQuery, $iCount, 1);
        $iCount += (strlen($aTokens[$i])+1);
        $pdata = &$aData[$i];
        $sPreparedquery .= ($char != "~" ? $hAppdbLink->real_escape_string($pdata) : $pdata);
        $sPreparedquery .= $aTokens[$i];
    }

    return query_appdb($sPreparedquery);
}

function query_error($sQuery, $sComment, $hLink)
{
    error_log("Query: '".$sQuery."' $hLink->errno: '".$hLink->errno."' comment: '".$sComment."'");
    trigger_error("Database Error: {$hLink->error} Comment: {$sComment}", E_USER_ERROR);
}

function query_fetch_row($hResult)
{
    return $hResult->fetch_row();
}

function query_fetch_object($hResult)
{
    return $hResult->fetch_object();
}

function query_appdb_insert_id()
{
    global $hAppdbLink;
    return $hAppdbLink->insert_id;
}

function query_bugzilla_insert_id()
{
    global $hBugzillaLink;
    return $hAppdbLink->insert_id;
}

function query_num_rows($hResult)
{
    return $hResult->num_rows;
}

function query_affected_rows()
{
    global $hAppdbLink;
    return $hAppdbLink->affected_rows;
}

function query_escape_string($sString)
{
    global $hAppdbLink;

    if(!is_resource($hAppdbLink))
    {
        // The last argument makes sure we are really opening a new connection
        $hAppdbLink = new mysqli(APPS_DBHOST, APPS_DBUSER, APPS_DBPASS, APPS_DB);
        if(!$hAppdbLink)
          query_error('', 'Database connection failed!', $hAppdbLink);
    }

    return $hAppdbLink->real_escape_string($sString);
}

function query_field_type($hResult, $iFieldOffset)
{
    $finfo = $hResult->fetch_field_direct($iFieldOffset);
    return $finfo->type;
}

function query_field_name($hResult, $iFieldOffset)
{
    $finfo = $hResult->fetch_field_direct($iFieldOffset);
    return $finfo->name;
}

function query_field_len($hResult, $ifieldOffset)
{
    $finfo = $hResult->fetch_field_direct($iFieldOffset);
    return $finfo->max_length;
}

function query_field_flags($hResult, $iFieldOffset)
{
    $finfo = $hResult->fetch_field_direct($iFieldOffset);
    return $finfo->flags;
}

function query_fetch_field($hResult, $iFieldOffset)
{
    return $hResult->fetch_field_direct($iFieldOffset);
}

function query_get_server_info()
{
    global $hAppdbLink;
    return $hAppdbLink->server_info;
}


