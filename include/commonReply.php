<?php

class CommonReply extends ObjectManagerBase
{
    private $shReply;
    private $aTags;

    function CommonReply($iId = null, $oRow = null)
    {
        if(!is_numeric($iId) && !$oRow)
            return;

        if(!$oRow)
        {
            $hResult = query_parameters("SELECT * FROM ? WHERE id  = '?'", $this->objectGetSQLTable(), $iId);
            
            if($hResult)
                $oRow = mysql_fetch_object($hResult);
        }
        
        if($oRow)
        {
            $this->objectReadCommonDBFields($oRow);
            $this->shReply = $oRow->reply;
        }
    }

    protected function objectGetSQLTable()
    {
        return 'commonReplies';
    }

    public function getReply()
    {
        return $this->shReply;
    }

    public function create()
    {
        $hResult = query_parameters("INSERT INTO ? (reply) VALUES('?')", $this->objectGetSQLTable(), $this->shReply);

        if(!$hResult)
            return false;

        $this->iId = mysql_insert_id();
        
        foreach($this->aTags as $iTag)
        {
            $oTag = new TagCommonReply($iTag);
            $oTag->assign($this->iId);
        }
        
        return true;
    }
    
    public function update()
    {
        $hResult = query_parameters("UPDATE ? SET reply = '?' WHERE id = '?'", $this->objectGetSQLTable(), $this->shReply, $this->iId);

        if(!$hResult)
            return false;

        $oTag = new TagCommonReply();
        $oTag->updateAssignedTags($this->iId, $this->aTags);

        return true;
    }

    public function objectGetEntriesCount()
    {
        $hResult = query_parameters("SELECT COUNT(id) as count FROM ? WHERE state = 'accepted'", $this->objectGetSQLTable());

        if(!$hResult)
            return false;

        $oRow = mysql_fetch_object($hResult);
        return $oRow->count;
    }

    public function objectGetEntries()
    {
        $hResult = query_parameters("SELECT * FROM ? WHERE state = 'accepted'", $this->objectGetSQLTable());

        if(!$hResult)
            return false;

        return $hResult;
    }
    
    public function objectGetHeader()
    {
        $oRow = new TableRow();
        $oRow->AddTextCell('Reply');
        
        return $oRow;
    }
    
    public function objectGetTableRow()
    {
        $oRow = new TableRow();
        $oRow->AddTextCell($this->shReply);

        return new OMTableRow($oRow);
    }
    
    public function outputEditor()
    {
        $oTable = new Table();
        
        $oRow = new TableRow();
        $oCell = new TableCell('Reply text:');
        $oCell->SetVAlign('top');
        $oRow->AddCell($oCell);
        $oRow->AddTextCell("<textarea name=\"shReply\" rows=\"5\" cols=\"30\">{$this->shReply}</textarea>");
        $oTable->AddRow($oRow);

        $oRow = new TableRow();
        $oRow->AddTextCell('');
        $oCell = new TableCell('');
        $oTag = new TagCommonReply();
        $oRow->AddTextCell($oTag->getAssignTagsEditor($this->iId));
        $oTable->AddRow($oRow);
        
        echo $oTable->GetString();
    }
    
    public function checkOutputEditorInput($aClean)
    {
        if(!getInput('shReply', $aClean))
            return "<li>You need to enter a reply text</li>";

        return '';
    }

    public function getOutputEditorValues($aClean)
    {
        $this->shReply = getInput('shReply', $aClean);

        $oTag = new TagCommonReply();
        $this->aTags = $oTag->getSelectedTags($aClean);
    }

    public function objectShowAddEntry()
    {
        return true;
    }
}

?>