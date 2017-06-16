<?php

abstract class TagAssignmentMgr extends ObjectManagerBase
{
    protected $aTaggedEntries;

    function Constructor($iId = null, $oRow = null)
    {
        $this->iId = $iId;
        $this->aTaggedEntries = $this->getTagObject($this->iId)->getTaggedEntries();
    }
    
    protected function objectGetSQLTable()
    {
        return $this->getTagObject()->getSQLTableForAssignments();
    }
    
    public function create()
    {
        return false;
    }
    
    public function update()
    {
        $i = 0;

        foreach($this->aTaggedEntries as $oTag)
        {
            $hResult = query_parameters("UPDATE ? SET position = '?' WHERE taggedId = '?'", $this->objectGetSQLTable(), $i, $oTag->objectGetId());

            if(!$hResult)
                return false;

            $i++;
        }
        
        return true;
    }
    
    public function objectGetEntries()
    {
        return false;
    }
    
    public function objectGetHeader()
    {
        return null;
    }
    
    public function objectGetTableRow()
    {
        return null;
    }
    
    function objectHideDelete()
    {
        return true;
    }
    
    function objectHideReplyField()
    {
        return true;
    }
    
    public function outputEditor()
    {
        if(sizeof($this->aTaggedEntries) == 0)
            echo 'This page allows you to sort associated entries, but since there are none . . . have fun!<br /><br />';
        else if(sizeof($this->aTaggedEntries) == 1)
            echo 'This page allows you to sort associated entries, but since there\'s only one . . . have fun!<br /><br />';
        echo 'The following tags are assigned to this entry; this page allows you to change the order in which they are displayed:<br /><br />';
        
        $iSize = min(sizeof($this->aTaggedEntries), 10);

        $i = 0;
        $shOptions = '';

        foreach($this->aTaggedEntries as $oTag)
        {
            $shOptions .= '<option value="'.$oTag->objectGetId().'">'.$oTag->objectMakeLink()."</option>\n";
            echo '<input type="hidden" name="iTagPlace'.$i.'" value="'.$oTag->objectGetId().'" />';
            $i++;
        }
        echo "<select size=\"$iSize\" name=\"iTagAssocList\">\n";
        echo $shOptions;
        echo '</select>';
        echo '<div style="padding-bottom: 10px;">';
        echo "<script type=\"text/JavaScript\">\n";
        echo "function swap(index1, index2) {\n";
        echo "var selector = document.forms['sQform']['iTagAssocList'];\n";
        echo "var item1 = selector.options[index1];\n";
        echo "var item2 = selector.options[index2];\n";
        echo "selector.options[index1] = new Option(item2.text, item2.value, item2.defaultSelected, item2.selected);\n";
        echo "selector.options[index2] = item1;\n";
        echo "var hidden1 = document.forms['sQform']['iTagPlace'+index1];\n";
        echo "var hidden2 = document.forms['sQform']['iTagPlace'+index2];\n";
        echo "var tmp = hidden1.value;\n";
        echo "hidden1.value = hidden2.value;\n";
        echo "hidden2.value = tmp;\n";
        echo "}\n";
        echo "function moveUp() {\n";
        echo "var selector = document.forms['sQform']['iTagAssocList'];\n";
        echo "var index = selector.selectedIndex;\n";
        echo "if(index == 0)\n";
        echo "    return;\n";
        echo "swap(index, index - 1);\n";
        echo "}\n";
        echo "function moveDown() {\n";
        echo "var selector = document.forms['sQform']['iTagAssocList'];\n";
        echo "var index = selector.selectedIndex;\n";
        echo "if(index + 1 == selector.options.length)\n";
        echo "    return;\n";
        echo "swap(index, index + 1);\n";
        echo "}\n";
        echo "</script>\n";
        echo '</div>';
        echo '<div class="btn-toolbar";>';
        echo '<button onclick="moveUp()" type="button" class="btn btn-default">Move up</button>';
        echo '<button onclick="moveDown()" type="button" class="btn btn-default">Move down</button>';
        echo '</div>';
    }
    
    public function getOutputEditorValues($aClean)
    {
        $aDBTags = $this->getTagObject($this->iId)->getTaggedEntries();
        $this->aTaggedEntries = array();

        for($i = 0; $i < sizeof($aDBTags); $i++)
        {
            foreach($aDBTags as $oTag)
            {
                if($oTag->objectGetId() == $aClean['iTagPlace'.$i])
                {
                    $this->aTaggedEntries[] = $oTag;
                    break;
                }
            }
        }
        
        if(sizeof($aDBTags) != sizeof($this->aTaggedEntries))
            $this->aTaggedEntries = $aDBTags;
    }
    
    public function checkOutputEditorInput($aClean)
    {
        $shErrors = '';

        return $shErrors;
    }
    
    protected abstract function getTagObject($iId = null, $oRow = null);
}

?>
