<?php

require_once(BASE.'include/objectManagerBase.php');

abstract class Tag extends ObjectManagerBase
{
    protected $bMultipleAssignments;
    protected $sTextId;
    protected $sName;
    protected $sDescription;
    
    function Constructor($iId = null, $oRow = null, $sTextId = '')
    {
        if($sTextId)
        {
            $hResult = query_parameters("SELECT * FROM ? WHERE textId = '?'", $this->objectGetSQLTable(), $sTextId);

            if($hResult)
                $oRow = mysql_fetch_object($hResult);
        }

        if(!is_numeric($iId) && !$oRow)
            return;

        if(!$oRow)
        {
            $hResult = query_parameters("SELECT * FROM ? WHERE id = '?'", $this->objectGetSQLTable(), $iId);

            if($hResult)
                $oRow = mysql_fetch_object($hResult);
        }

        if($oRow)
            $this->readDBFields($oRow);
    }

    protected abstract function getTagClass();
 
    protected function readDBFields($oRow)
    {
        $this->objectReadCommonDBFields($oRow);
        $this->bMultipleAssignments = ($oRow->multipleAssignments == 1) ? true : false;
        $this->sTextId = $oRow->textId;
        $this->sName = $oRow->name;
        $this->sDescription = $oRow->description;
    }

    protected function objectGetSQLTable()
    {
        return 'tags_'.$this->getTagClass();
    }

    protected function getSQLTableForAssignments()
    {
        return $this->objectGetSQLTable().'_assignments';
    }

    public function removeAssignment($iId)
    {
        $hResult = query_parameters("DELETE FROM ? WHERE tagId = '?' AND taggedId = '?'", $this->getSQLTableForAssignments(), $this->iId, $iId);

        if(!$hResult)
            return false;

        return true;
    }

    public function assign($iId)
    {
        $hResult = query_parameters("INSERT INTO ? (tagId, taggedId) VALUES('?', '?')", $this->getSQLTableForAssignments(), $this->iId, $iId);

        if(!$hResult)
            return false;

        return true;
    }

    public function updateAssignedTags($iId, $aNew)
    {
        $sClass = get_class($this);
        $aOld = $this->getAssignedTags($iId);

        $aDeleted = array_diff($aOld, $aNew);
        $aAdded = array_diff($aNew, $aOld);

        foreach($aDeleted as $iDeleted)
        {
            $oTag = new $sClass($iDeleted);
            $oTag->removeAssignment($iId);
        }
        
        foreach($aAdded as $iAdded)
        {
            $oTag = new $sClass($iAdded);
            $oTag->assign($iId);
        }
    }

    public function getTaggedEntries()
    {
        $hResult = query_parameters("SELECT taggedId FROM ? WHERE tagId = '?'", $this->getSQLTableForAssignments(), $this->iId);

        if(!$hResult)
            return array();

        $aRet = array();
        $sClass = $this->getTagClass();
        while(($oRow = mysql_fetch_object($hResult)))
            $aRet[] = new $sClass($oRow->taggedId);

        return $aRet;
    }

    public function create()
    {
        $hResult = query_parameters("INSERT INTO ? (textId, name, description, multipleAssignments) VALUES('?', '?', '?', '?')", $this->objectGetSQLTable(), $this->sTextId, $this->sName, $this->sDescription, $this->bMultipleAssignments ? 1 : 0);

        if(!$hResult)
            return false;

        $this->iId = mysql_insert_id();
        
        return true;
    }

    public function update()
    {
        $hResult = query_parameters("UPDATE ? SET textId = '?', name = '?', description = '?', multipleAssignments = '?' WHERE id = '?'", $this->objectGetSQLTable(), $this->sTextId, $this->sName, $this->sDescription, $this->bMultipleAssignments ? 1 : 0, $this->iId);

        if(!$hResult)
            return false;

        return true;
    }

    public function getTags()
    {
        $hTags = $this->objectGetEntries();
        $aRet = array();
        $sClass = get_class($this);
        
        if(!$hTags)
            return $aRet;
        
        while(($oRow = mysql_fetch_object($hTags)))
            $aRet[] = new $sClass(null, $oRow);

        return $aRet;
    }

    public function objectGetEntries()
    {
        $hResult = query_parameters("SELECT * FROM ? WHERE state = 'accepted'", $this->objectGetSQLTable());

        if(!$hResult)
            return false;

        return $hResult;
    }

public function objectGetEntriesCount()
    {
        $hResult = query_parameters("SELECT COUNT(*) as count FROM ? WHERE state = 'accepted'", $this->objectGetSQLTable());

        if(!$hResult)
            return false;

        $oRow = mysql_fetch_object($hResult);

        return $oRow->count;
    }

    public function objectGetHeader()
    {
        $oRow = new TableRow();

        $oRow->AddTextCell('Text ID');
        $oRow->AddTextCell('Name');
        $oRow->AddTextCell('Description');
        
        return $oRow;
    }
    
    public function objectGetTableRow()
    {
        $oRow = new TableRow();

        $oRow->AddTextCell($this->sTextId);
        $oRow->AddTextCell($this->sName);
        $oRow->AddTextCell($this->sDescription);
        
        return new OMTableRow($oRow);
    }

    public function getAssignedTags($iTaggedId)
    {
        $aRet = array();

        $hResult = query_parameters("SELECT tagId FROM ? WHERE taggedId = '?' AND state = 'accepted'", $this->getSQLTableForAssignments(), $iTaggedId);

        if(!$hResult)
            return $aRet;

        while(($oRow = mysql_fetch_object($hResult)))
        {
            $aRet[] = $oRow->tagId;
        }
        
        return $aRet;
    }

    public function getSelectedTags($aClean)
    {
        $aRet = array();

        foreach($this->getTags() as $oTag)
        {
            if(getInput('bAssignTag'.$oTag->objectGetId(), $aClean) == 'true')
                $aRet[] = $oTag->objectGetId();
        }
        
        return $aRet;
    }

    public function getAssignTagEditor($bChecked = false)
    {
        $sChecked = $bChecked ? ' checked="checked"' : '';
        $shRet = "<input type=\"checkbox\" value=\"true\" name=\"bAssignTag{$this->iId}\"$sChecked /> ";

        $shRet .= $this->sName;
        
        return $shRet;
    }

    public function getAssignTagsEditor($iAssignedFor = null, $aSelected = array())
    {
        $shRet = '';

        if(!sizeof($aSelected) && $iAssignedFor)
            $aSelected = $this->getAssignedTags($iAssignedFor);

        foreach($this->getTags() as $oTag)
        {
            $bSelected = in_array($oTag->objectGetId(), $aSelected);
            $shRet .= $oTag->getAssignTagEditor($bSelected);
            $shRet .= '<br />';
        }
        
        return $shRet;
    }

    public function outputEditor()
    {
        $sMultipleAssignments = $this->bMultipleAssignments ? ' checked="checked"' : '';
        $oTable = new Table();

        $oRow = new TableRow();
        $oRow->AddTextCell('Text ID (used in URLs):');
        $oRow->AddTextCell("<input type=\"text\" name=\"sTextId\" value=\"{$this->sTextId}\" maxlength=\"255\" size=\"30\" />");
        $oTable->AddRow($oRow);

        $oRow = new TableRow();
        $oRow->AddTextCell('Name:');
        $oRow->AddTextCell("<input type=\"text\" name=\"sName\" value=\"{$this->sName}\" maxlength=\"255\" size=\"30\" />");
        $oTable->AddRow($oRow);

        $oRow = new TableRow();
        $oRow->AddTextCell('Multiple assignments:');
        $oRow->AddTextCell("<input type=\"checkbox\" name=\"bMultipleAssignments\" value=\"true\" maxlength=\"255\" size=\"30\"$sMultipleAssignments /> (multiple entrie can have this tag)");
        $oTable->AddRow($oRow);

        $oRow = new TableRow();
        $oCell = new TableCell('Description:');
        $oCell->SetVAlign('top');
        $oRow->AddCell($oCell);
        $oRow->AddTextCell("<textarea name=\"sDescription\" rows=\"8\" cols=\"30\">{$this->sDescription}</textarea>");
        $oTable->AddRow($oRow);

        echo $oTable->GetString();
    }

    public function checkOutputEditorInput($aClean)
    {
        $shErrors = '';

        if(!getInput('sTextId', $aClean))
            $shErrors .= '<li>You need to supply a text ID</li>';

        if(!getInput('sName', $aClean))
            $shErrors .= '<li>You need to supply a name</li>';

        return $shErrors;
    }

    public function getOutputEditorValues($aClean)
    {
        $this->sTextId = getInput('sTextId', $aClean);
        $this->sName = getInput('sName', $aClean);
        $this->sDescription = getInput('sDescription', $aClean);
        $this->bMultipleAssignments = getInput('bMultipleAssignments', $aClean) == 'true' ? true : false;
    }

    public function objectShowAddEntry()
    {
        return true;
    }
}

?>