<?php

require_once(BASE.'include/tag.php');

class TagNoteVersion extends Tag
{
    private $iAppId;

    function TagNoteVersion($iVersionId = null, $oRow = null, $sTextId = '')
    {
        if(!is_numeric($iVersionId) && !$oRow)
            return;

        if(!$oRow)
        {
            $hResult = query_parameters("SELECT * FROM appVersion WHERE versionId = '?'", $iVersionId);

            if($hResult)
                $oRow = mysql_fetch_object($hResult);
        }
        
        if($oRow)
        {
            $this->bMultipleAssignments = true;
            $this->iId = $oRow->versionId;
            $this->sName = $oRow->versionName;
            $this->sDescription = $oRow->description;
            $this->iAppId = $oRow->appId;
            $this->sTextId = $oRow->versionId;
            $this->sState = $oRow->state;
        }
    }

    public function setAppId($iAppId)
    {
        if(is_numeric($iAppId) && $iAppId > 0)
            $this->iAppId = $iAppId;
    }

    protected function objectGetSQLTable()
    {
        return 'appVersion';
    }

    protected function getSQLTableForAssignments()
    {
        return 'tags_NoteVersion_assignments';
    }

    public function objectGetEntriesCount()
    {
        $hResult = query_parameters("SELECT COUNT(*) as count FROM ? WHERE appId = '?' AND state = 'accepted'", $this->objectGetSQLTable(), $this->iAppId);

        if(!$hResult)
            return false;

        $oRow = mysql_fetch_object($hResult);

        return $oRow->count;
    }

    public function objectGetEntries()
    {
        $hResult = query_parameters("SELECT * FROM ? WHERE appId = '?' AND state = 'accepted'", $this->objectGetSQLTable(), $this->iAppId);

        if(!$hResult)
            return false;

        return $hResult;
    }

    protected function getTagClass()
    {
        return 'Note';
    }
}

?>