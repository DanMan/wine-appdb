<?php

define("ERROR_SQL", "sql_error");
define("ERROR_GENERAL", "general_error");

class error_log
{
    function log_error($sErrorType, $sLogText)
    {
        global $aClean;

        /* dump the contents of $_REQUEST and $aClean to a variable */
        /* so we can output that to the log entry.  it should make it much easier */
        /* to determine when and where the error took place */
        ob_start();
        echo "REQUEST:\n";
        var_dump($_REQUEST);
        echo "aClean:\n";
        var_dump($aClean);
        $sRequestText = ob_get_contents();
        ob_end_clean();

        $sQuery = 'INSERT INTO error_log (submitTime, userid, type, log_text, request_text, deleted) '.
            "VALUES(?, '?', '?', '?', '?', '?')";
        $iUser = (isset($_SESSION['current']) ? $_SESSION['current']->iUserId : 0);
        $hResult = query_parameters($sQuery,
                                    "NOW()",
                                    $iUser,
                                    $sErrorType,
                                    $sLogText,
                                    $sRequestText,
                                    '0');
    }

    /* get a backtrace and log it to the database */
    function logBackTrace($sDescription)
    {
        ob_start();
        print_r(debug_backtrace());
        $sDebugOutput = ob_get_contents();
        ob_end_clean();

        error_log::log_error("general_error", $sDescription.' '.$sDebugOutput);
    }

    function getEntryCount()
    {
        $sQuery = "SELECT count(*) as cnt FROM error_log WHERE deleted = '0'";
        $hResult = query_parameters($sQuery);
        $oRow = query_fetch_object($hResult);
        return $oRow->cnt;
    }

    /* purge all of the current entries from the error log */
    function flush()
    {
        $sQuery = "UPDATE error_log SET deleted='1'";
        $hResult = query_parameters($sQuery);

        if($hResult) return true;
        else return false;
    }
    
    function mail_admins_error_log($sSubject = "")
    {
        $sSubject .= "Database Error log\r\n";
        $sEmail = user::getAdminEmails(); /* get list admins */

        $sQuery = "SELECT * from error_log WHERE deleted='0' ORDER BY submitTime";
        $hResult = query_parameters($sQuery);

        $bEmpty = false;
        if(query_num_rows($hResult) == 0)
            $bEmpty = true;

        $sMsg = "Log entries:\r\n";
        $sMsg.= "\r\n";

        $sMsg.= "Submit time            userid  type\r\n";
        $sMsg.= "log_text\r\n";
        $sMsg.= "request_text\r\n";
        $sMsg.="----------------------------------\r\n\r\n";

        /* append each log entry to $sMsg */
        while($oRow = query_fetch_object($hResult))
        {
            $sMsg.=$oRow->submitTime."    ".$oRow->userid."   ".$oRow->type."\r\n";
            $sMsg.= "---------------------\r\n";
            $sMsg.=$oRow->log_text."\r\n";
            $sMsg.= "---------------------\r\n";
            $sMsg.=$oRow->request_text."\r\n\r\n";
        }

        /* if we had no entries we should indicate */
        /* that the error log is empty */
        if($bEmpty)
            $sMsg = "The error log is empty.\r\n";

        if($sEmail)
            mail_appdb($sEmail, $sSubject, $sMsg);
    }
}

?>
