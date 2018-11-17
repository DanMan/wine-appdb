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
    
    /* mark all of the current entries as deleted */
    function flush()
    {
        $sQuery = "UPDATE error_log SET deleted='1'";
        $hResult = query_parameters($sQuery);

        if($hResult) return true;
        else return false;
    }
    
    function objectGetId()
    {
        return $this->iId;
    }
    
    function objectGetState()
    {
        return 'accepted';
    }
    
    public function objectGetHeader()
    {
        return $oRow;
    }
    
    public function canEdit()
    {
        return $_SESSION['current']->hasPriv('admin');
    }
    
    function objectWantCustomDraw()
    {
       return true;
    }

    public function objectGetTableRow()
    {        
        return $oRow;
    }

    public function objectDrawCustomTable($hResult)
    {       
        echo html_frame_start("Error Log Entries","100%","",0);
        echo '<table width="100%" border=0 cellpadding=3 cellspacing=0>';
        echo '<tr class=color1>';
        echo '<td>Id</td>';
        echo '<td>Submit time</td>';
        echo '<td>UserId</td>';
        echo '<td>Type</td>';
        echo '<td>Log text</td>';
        echo '<td>Request text</td>';
        echo '</tr>';
        
        $i = 0;
        while($oRow = query_fetch_object($hResult))
        {
            echo '<tr class=color0>';
            echo '<td>'.$oRow->id.'</td>';
            echo '<td>'.$oRow->submitTime.'</td>';
            echo '<td>'.$oRow->userid.'</td>';
            echo '<td>'.$oRow->type.'</td>';
            echo '<td>'.$oRow->log_text.'</td>';
            echo '<td>'.$oRow->request_text.'</td>';
            $i++;
        } 
        echo '</table>';
        echo html_frame_end();   
        
        echo "Found $i entries. <br>";
    }
    
    function objectGetItemsPerPage($sState = 'accepted')
    {
        $aItemsPerPage = array(25, 50, 100, 200);
        $iDefaultPerPage = 25;
        return array($aItemsPerPage, $iDefaultPerPage);
    }
    
    public function objectGetEntries($sState, $iRows = null, $iStart = 0)
    {
        if(!$_SESSION['current']->hasPriv('admin'))
            return false;
        
        $sLimit = objectManager::getSqlLimitClause($iRows, $iStart, 'error_log');
        
        $sQuery = "SELECT * FROM error_log ORDER BY id DESC $sLimit";
        $hResult = query_parameters($sQuery);
        
        return $hResult;
    }
    
    function objectGetEntriesCount()
    {
        $sQuery = "SELECT COUNT(DISTINCT id) as count FROM error_log";
        $hResult = query_parameters($sQuery);
        
        if(!$hResult)
            return $hResult;
        
        $oRow = query_fetch_object($hResult);
        
        return $oRow->count;
    }
}


