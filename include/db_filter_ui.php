<?php

/**
 * User interface for the SQL filter classes
 *
 * Copyright 2008 BitSplash Software LLC
 * Copyright 2008 Alexander N. Sørnes <alex@thehandofagony.com>
 *
*/

require_once('db_filter.php');

define('FILTER_VALUES_NORMAL', 1);
define('FILTER_VALUES_ENUM', 2);
define('FILTER_VALUES_OPTION_BOOL', 3);
define('FILTER_VALUES_OPTION_ENUM', 4);

define('MAX_FILTERS', 50);

/* Info describing an available filter: what column it applies to,
    and what comparison options are available */
class FilterInfo
{
    private $sColumn;
    private $sDisplayName;
    private $aTypes; // Available filters for this column
    private $iValueType; // Normal, enum ...
    private $aValueTypeData; // List of enums
    private $aValueTypeDataDisplay; // Optional display names for enums

    public function FilterInfo($sColumn, $sDisplayName, $aTypes, $iValueType = FILTER_VALUES_NORMAL, $aValueTypeData = array(), $aValueTypeDisplay = array())
    {
        $this->sColumn = $sColumn;
        $this->sDisplayName = $sDisplayName;
        $this->aTypes = $aTypes;
        $this->iValueType = $iValueType;
        $this->aValueTypeData = $aValueTypeData;

        if(sizeof($aValueTypeData) && !sizeof($aValueTypeDisplay))
            $this->aValueTypeDataDisplay = $aValueTypeData;
        else
            $this->aValueTypeDataDisplay = $aValueTypeDisplay;
    }

    public function getColumn()
    {
        return $this->sColumn;
    }

    public function getDisplayName()
    {
        return $this->sDisplayName;
    }

    public function getValueType()
    {
        return $this->iValueType;
    }

    public function getValueTypeData()
    {
        return $this->aValueTypeData;
    }

    public function getValueTypeDataDisplay()
    {
        return $this->aValueTypeDataDisplay;
    }

    public function getTypes()
    {
        return $this->aTypes;
    }

    public function isOption()
    {
        switch($this->iValueType)
        {
            case FILTER_VALUES_OPTION_BOOL:
            case FILTER_VALUES_OPTION_ENUM:
                return true;

            default:
                return false;
        }
    }

    public static function getOpName($iOpId)
    {
        switch($iOpId)
        {
            case FILTER_EQUALS:
                return 'equal to';
            case FILTER_LIKE:
                return 'like';
            case FILTER_CONTAINS:
                return 'contains';
            case FILTER_STARTS_WITH:
                return 'starts with';
            case FILTER_ENDS_WITH:
                return 'ends with';
            case FILTER_NOT_LIKE:
                return 'not like';
            case FILTER_NOT_EQUALS:
                return 'not equal to';
            case FILTER_LESS_THAN:
                return 'less than';
            case FILTER_GREATER_THAN:
                return 'greater than';
        }
    }
}

/* Class handling tables where the user can filter contents */
class FilterInterface
{
    private $aFilterInfo;
    private $oFilterSet;
    private $aEscapeChars;
    private $aEscapeCharsWith;
    private $sErrors; // Used to inform the user about errors (and to give advice)

    public function FilterInterface($sTableName = '')
    {
        $this->aFilterInfo = array();
        $this->oFilterSet = new FilterSet(query_escape_string($sTableName));
        $this->aEscapeChars = array('.');
        $this->aEscapeCharsWith = array('-');
        $this->sErrors = '';
    }

    public function AddFilterObject(Filter $oFilter)
    {
        $this->oFilterSet->AddFilterObject($oFilter);
    }

    public function setFilterSet(FilterSet $oSet)
    {
        $this->oFilterSet = $oSet;
    }

    /* Convenience function to add a filter option */
    public function AddFilterInfo($sColumn, $sDisplayName, $aTypes, $iValueType = VALUE_TYPE_NORMAL, $aValueTypeData = array(), $aValueTypeDisplay = array())
    {
        $this->aFilterInfo[$sColumn] = new FilterInfo($sColumn, $sDisplayName, $aTypes, $iValueType, $aValueTypeData, $aValueTypeDisplay);
    }

    /* We can't use some special chars in variable names, such as '.' */
    public function escapeChars($sIn)
    {
        return str_replace($this->aEscapeChars, $this->aEscapeCharsWith, $sIn);
    }

    public function unescapeChars($sIn)
    {
        return str_replace($this->aEscapeWith, $this->aEscape, $sIn);
    }

    public function getUrlElement($iId, Filter $oFilter)
    {
        $sColumn = $this->escapeChars($oFilter->getColumn());

        $sId = $iId;

        $shEditor = "&i{$sColumn}Op$sId={$oFilter->getOperatorId()}";
        $shEditor .= "&s{$sColumn}Data$sId={$oFilter->getData()}";

        return $shEditor;
    }

    public function getHiddenInputTag($iId, Filter $oFilter)
    {
        $sColumn = $this->escapeChars($oFilter->getColumn());
        $sId = $iId;

        $shEditor = "<input type=\"hidden\" name=\"i{$sColumn}Op$sId\" value=\"{$oFilter->getOperatorId()}\">";
        $shEditor .= "<input type=\"hidden\" name=\"s{$sColumn}Data$sId\" value=\"{$oFilter->getData()}\">";

        return $shEditor;
    }

    public function getOptionBoolEditor($iId, Filter $oFilter)
    {
        $sColumn = $this->escapeChars($oFilter->getColumn());
        $oColumn = $this->aFilterInfo[$oFilter->getColumn()];
        $sId = ($iId == -1) ? '' : $iId;

        $aTypes = $oColumn->getTypes();
        $iOp = $aTypes[0];

        if($iId == -1)
        {
            /* The first entry in the list of choices is the default */
            $aValues = $oColumn->getValueTypeData();
            $sData = $aValues[0];
        } else
        {
            $sData = $oFilter->getData();
        }

        $shRet = "<input type=\"hidden\" name=\"i{$sColumn}Op$sId\" value=\"$iOp\">";

        if($sData == 'true')
            $sChecked = ' checked="checked"';
        else
            $sChecked = '';

        $shRet .= "<input value=\"true\" $sChecked name=\"s{$sColumn}Data$sId\" type=\"checkbox\">";
        $shRet .= ' '.$oColumn->getDisplayName();

        return $shRet;
    }

    public function getItemEditor($iId, Filter $oFilter)
    {
        $sColumn = $this->escapeChars($oFilter->getColumn());
        $oColumn = $this->aFilterInfo[$oFilter->getColumn()];

        $sId = ($iId == -1) ? '' : $iId;
        $shEditor = $oColumn->getDisplayName().' ';

        $aTypes = $oColumn->getTypes();

        /* It doesn't make sense to show a dropdown menu of choices if there is only one
           If the filter is already active then there are more than one; one to remove */
        if($iId == -1 && sizeof($aTypes) == 1)
        {
            echo "<input type=\"hidden\" name=\"i{$sColumn}Op$sId\" value=\"{$aTypes[0]}\">";

            /* Printing 'equal to' sounds weird if it is the only choice */
            if($aTypes[0] != FILTER_EQUALS)
                $shEditor .= $oColumn->getOpName($aTypes[0]);
        } else if ($aTypes[0] != FILTER_OPTION_ENUM)
        {
            $shEditor .= "<select name='i{$sColumn}Op$sId' class='form-control form-control-inline'>";

            if($iId != -1)
            {
                $sSel = '';
                $sText = 'remove';

                $shEditor .= "<option value='0'$sSel>-- $sText --</option>";
            }

            foreach($aTypes as $iType)
            {
                if($oFilter->getOperatorId() == $iType)
                    $sSel = " selected='selected'";
                else
                    $sSel = '';
                $shEditor .= "<option value='$iType'$sSel>".$oColumn->getOpName($iType).'</option><br>';
            }
            $shEditor .= '</select> ';
        } else
        {
            echo "<input type=\"hidden\" name=\"i{$sColumn}Op$sId\" value=\"{$aTypes[0]}\">";
        }

        switch($oColumn->getValueType())
        {
            case FILTER_VALUES_NORMAL:
                $shEditor .= "<input type='text' value=\"{$oFilter->getData()}\" name='s{$sColumn}Data$sId' size='30' class='form-control form-control-inline'>";
            break;
            case FILTER_VALUES_ENUM:
            case FILTER_VALUES_OPTION_ENUM:
                $shEditor .= $this->getEnumEditor($oColumn, $oFilter, $sId);
            break;
        }

        return $shEditor;
    }

    public function getEnumEditor($oColumn, $oFilter, $sId)
    {
        $sColumn = $this->escapeChars($oFilter->getColumn());
        $aOptions = $oColumn->getValueTypeData();
        $aOptionNames = $oColumn->getValueTypeDataDisplay();

        $sData = $oFilter->getData();

        $shEditor = "<select name=\"s{$sColumn}Data$sId\" class=\"form-control form-control-inline\">";

        if($sData)
            $shEditor .= "<option value=\"\">-- remove --</option>";
        else
            $shEditor .= "<option value=\"\">-- select --</option>";

        for($i = 0; $i < sizeof($aOptions); $i++)
        {
            $sOption = $aOptions[$i];
            $sSelected = '';
            if($sData == $sOption)
                $sSelected = ' selected="selected"';
            $shEditor .= "<option value=\"$sOption\"$sSelected>{$aOptionNames[$i]}</option>";
        }

        $shEditor .= "</select>";

        return $shEditor;
    }

    /* Get filter data formatted to fit in a URL */
    public function getUrlData()
    {
        $shEditor = '';
        $aCounts = array();

        foreach($this->oFilterSet->getFilters() as $oFilter)
        {
            $sColumn = $oFilter->getColumn();

            if(!array_key_exists($sColumn, $aCounts))
                $aCounts[$sColumn] = 0;

            $shEditor .= $this->getUrlElement($aCounts[$sColumn], $oFilter);

            $shEditor .= '<br>';

            $aCounts[$sColumn]++;
        }

        return $shEditor;
    }

    /* Get a list of hidden input tags to preserve form data */
    public function getHiddenFormData()
    {
        $shEditor = '';
        $aCounts = array();

        foreach($this->oFilterSet->getFilters() as $oFilter)
        {
            $sColumn = $oFilter->getColumn();

            if(!array_key_exists($sColumn, $aCounts))
                $aCounts[$sColumn] = 0;

            $shEditor .= $this->getHiddenInputTag($aCounts[$sColumn], $oFilter);

            $shEditor .= '<br>';

            $aCounts[$sColumn]++;
        }

        return $shEditor;
    }

    public function getEditor()
    {
        $shNewItemsEditor = '';
        $shCurrentItemsEditor = '';
        $aCounts = array();

        if(sizeof($this->oFilterSet->getFilters()))
             $shCurrentItemsEditor .= '<p><b>Active filters</b></p>';
        foreach($this->oFilterSet->getFilters() as $oFilter)
        {
            $sColumn = $oFilter->getColumn();

            if(!array_key_exists($sColumn, $aCounts))
                $aCounts[$sColumn] = 0;

            if($oFilter->getOperatorId() == FILTER_OPTION_BOOL)
                $shCurrentItemsEditor .= $this->getOptionBoolEditor($aCounts[$sColumn], $oFilter);
            else
                $shCurrentItemsEditor .= $this->getItemEditor($aCounts[$sColumn], $oFilter);
            $shCurrentItemsEditor .= '<br>';

            $aCounts[$sColumn]++;
        }

        /* Show errors, if any */
        if($this->sErrors)
            $shNewItemsEditor .= "<span color=\"text-danger\">{$this->sErrors}</span>";

        foreach($this->aFilterInfo as $oOption)
        {
            $oDummyFilter = new Filter($oOption->getColumn(), 0, '');
            $aTypes = $oOption->getTypes();

            if($oOption->getValueType() == FILTER_VALUES_OPTION_BOOL)
            {
                if(!array_key_exists($oOption->getColumn(), $aCounts))
                    $shNewItemsEditor .= $this->getOptionBoolEditor(-1, $oDummyFilter);
                $shNewItemsEditor .= '<br>';
            } else
            {
                /* Make necessary checks for filters that are only supposed to be shown once */
                if($oOption->getValueType() != FILTER_VALUES_OPTION_ENUM || !array_key_exists($oOption->getColumn(), $aCounts))
                {
                    $shNewItemsEditor .= $this->getItemEditor(-1, $oDummyFilter);
                    $shNewItemsEditor .= '<br>';
                }
            }
        }

        return $shNewItemsEditor.$shCurrentItemsEditor;
    }

    public function getFilterInfo()
    {
        return $this->aFilterInfo;
    }

    /* Reads all input related to filters for the given table column */
    public function readInputForColumn($aClean, FilterInfo $oOption)
    {
        $aReturn = array();
        $bChangedOption = false;

        for($i = 0; array_key_exists('i'.$this->escapeChars($oOption->getColumn())."Op$i", $aClean); $i++)
        {
            $sColumn = $this->escapeChars($oOption->getColumn());
            $sData = query_escape_string(getInput("s{$sColumn}Data$i", $aClean));
            $iOp = $aClean["i{$sColumn}Op$i"];

            if(!$iOp)
                continue;

            $oFilter = new Filter($oOption->getColumn(), $iOp, $sData);

            /* Only show an option as an active filter if it has been changed
               from the default */
            if($oOption->getValueType() == FILTER_VALUES_OPTION_BOOL || $oOption->getValueType() == FILTER_VALUES_OPTION_ENUM)
            {
                if($oOption->getValueType() == FILTER_VALUES_OPTION_BOOL)
                {
                    /* The default option is the first entry in the list of choices */
                    $aChoices = $oOption->getValueTypeData();
                    $sDefault = $aChoices[0];
                    if(!$sData)
                        $sData = 'false';

                    if($sData == $sDefault)
                        continue;
                }
                if($i > 0)
                    continue;
                $bChangedOption = true;
            }

            if(!$sData)
                continue;

            $aReturn[] = $oFilter;
        }

        if(array_key_exists('i'.$this->escapeChars($oOption->getColumn())."Op", $aClean))
        {
            $sColumn = $this->escapeChars($oOption->getColumn());
            $i = sizeof($aReturn);
            $sData = query_escape_string(getInput("s{$sColumn}Data", $aClean));
            $iOp = $aClean["i{$sColumn}Op"];


            if($iOp && $sData && ($oOption->getValueType() != FILTER_VALUES_OPTION_BOOL || !$bChangedOption))
            {
                $oFilter = new Filter($oOption->getColumn(), $iOp, $sData);
                $aReturn[] = $oFilter;
            } else if(!$iOp && $sData)
            {
                /* The user probably meant to add a filter, but forgot to seelect
                   a filter criterion */
                $this->sErrors .= 'You need to select a filter criterion from the drop-down list<br>';
            }
        }

        return $aReturn;
    }

    /* Reads an input array get enabled filters from form data.
       The given TableFilterSet defines available options */
    public function readInput($aClean)
    {
        $iCount = 0; // We set a maximum for how many filters a user can add,
                     // otherwise we may get a too long SQL query

        foreach($this->getFilterInfo() as $oOption)
        {
            foreach($this->readInputForColumn($aClean, $oOption) as $oNewFilter)
            {
                $iCount ++;
                $this->AddFilterObject($oNewFilter);
                if($iCount > MAX_FILTERS)
                    break;
            }
        }
    }

    public function loadTable($sTableName)
    {
        $this->oFilterSet->loadTable($sTableName);
    }

    public function saveTable($sTableName)
    {
        $this->oFilterSet->saveTable($sTableName);
    }

    public function getFilterCount()
    {
        return $this->oFilterSet->getFilterCount();
    }

     /* Returns an array of options, where the keys are the columns and the members
        are the settings themselves */
    public function getOptions()
    {
        $aOptions = array();
        foreach($this->oFilterSet->getFilters() as $oFilter)
        {
            if($oFilter->isOption())
                $aOptions[$oFilter->getColumn()] = $oFilter->getData();
        }
        foreach($this->aFilterInfo as $oFilterInfo)
        {
            if($oFilterInfo->isOption() &&
               !array_key_exists($oFilterInfo->getColumn(), $aOptions))
            {
                $aTypes = $oFilterInfo->getTypes();

                if($oFilterInfo->getValueType() == FILTER_VALUES_OPTION_BOOL)
                    $sDefault = $aTypes[0];
                else
                    $sDefault = '';

                $aOptions[$oFilterInfo->getColumn()] = $sDefault;
            }
        }
        return $aOptions;
    }

    public function getWhereClause()
    {
        return $this->oFilterSet->getWhereClause();
    }

    public function getTable($sTable, $iLimit = 0)
    {
        $hResult = $this->oFilterSet->getMatchedItems($sTable, $iLimit);

        if(!$hResult)
            return;

        echo 'Selected '.$this->oFilterSet->getMatchedItemsCount($sTable).' rows<br><br>';

        $oTable = new Table();

        while($aRow = query_fetch_row($hResult))
        {
            $oRow = new TableRow();

            foreach($aRow as $sCell)
            {
                $oRow->AddTextCell($sCell);
            }

            $oTable->AddRow($oRow);
        }

        return $oTable->getString();
    }
}

?>
