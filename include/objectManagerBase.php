<?php

abstract class ObjectManagerBase
{
    protected $iId;
    protected $sState;
    
    protected function objectReadCommonDBFields($oRow)
    {
        $this->iId = $oRow->id;
        $this->sState = $oRow->state;
    }

    public function objectGetId()
    {
        return $this->iId;
    }

    public function objectGetState()
    {
        return $this->sState;
    }

    protected abstract function objectGetSQLTable();
    protected abstract function create();
    protected abstract function update();

    public abstract function objectGetEntries();
    public abstract function objectGetHeader();
    public abstract function objectGetTableRow();
    public abstract function outputEditor();
    public abstract function getOutputEditorValues($aClean);
    public abstract function checkOutputEditorInput($aClean);



    public function canEdit()
    {
        return $_SESSION['current']->hasPriv('admin');
    }
    
    public function objectShowAddEntry()
    {
        return false;
    }
    
    public function allowAnonymousSubmissions()
    {
        return false;
    }
}

