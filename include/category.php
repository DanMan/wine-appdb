<?php
/***************************************************/
/* this class represents a category + its children */
/***************************************************/

/**
 * Category class for handling categories.
 */
class Category {
    var $iCatId;
    var $iParentId;
    var $sName;
    var $sDescription;
    var $aApplicationsIds;  // an array that contains the appId of every application linked to this category
    var $aSubcatsIds;        // an array that contains the appId of every application linked to this category


    /**    
     * constructor, fetches the data.
     */
    function Category($iCatId = null)
    {
        // we are working on an existing category
        if($iCatId=="0" || $iCatId)
        {
            /*
             * We fetch the data related to this vendor.
             */
            $sQuery = "SELECT *
                       FROM appCategory
                       WHERE catId = '?' ORDER BY catName;";
            if($hResult = query_parameters($sQuery, $iCatId))
            {
                $oRow = query_fetch_object($hResult);
                if($oRow)
                {
                    $this->iCatId = $iCatId;
                    $this->iParentId = $oRow->catParent;
                    $this->sName = $oRow->catName;
                    $this->sDescription = $oRow->catDescription;
                }
            }

            /*
             * We fetch applicationsIds. 
             */
            $sQuery = "SELECT appId
                       FROM appFamily
                       WHERE catId = '?'
                       AND state = 'accepted' ORDER BY appName";
            if($hResult = query_parameters($sQuery, $iCatId))
            {
                while($oRow = query_fetch_object($hResult))
                {
                    $this->aApplicationsIds[] = $oRow->appId;
                }
            }

            /*
             * We fetch subcatIds. 
             */
            $sQuery = "SELECT catId
                       FROM appCategory
                       WHERE catParent = '?' ORDER BY catName;";
            if($hResult = query_parameters($sQuery, $iCatId))
            {
                while($oRow = query_fetch_object($hResult))
                {
                    $this->aSubcatsIds[] = $oRow->catId;
                }
            }
        }
    }


    /**
     * Creates a new category.
     */
    function create()
    {
        $hResult = query_parameters("INSERT INTO appCategory (catName, catDescription, catParent) ".
                                    "VALUES('?', '?', '?')",
                                    $this->sName, $this->sDescription, $this->iParentId);
        if($hResult)
        {
            $this->iCatId = query_appdb_insert_id();
            $this->category($this->iCatId);
            return true;
        }

        return false;
    }

    /**
     * Update category.
     * Returns true on success and false on failure.
     */
    function update()
    {
        if(!query_parameters("UPDATE appCategory SET catName = '?', catDescription = '?', catParent = '?' WHERE catId = '?'",
                             $this->sName, $this->sDescription, $this->iParentId, $this->iCatId))
            return false;

        return true;
    }


    /**    
     * Deletes the category from the database. 
     */
    function delete()
    {
        if(!$this->canEdit())
            return false;

        if(sizeof($this->aApplicationsIds)>0)
            return FALSE;

        $sQuery = "DELETE FROM appCategory 
                    WHERE catId = '?' 
                    LIMIT 1";
        query_parameters($sQuery, $this->iCatId);

        return true;
    }

    function objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction)
    {
        return new mailOptions();
    }

    function objectGetChildren()
    {
        /* We don't have any (or we do, sort of, but we don't use them for anything at the moment) */
                return array();
    }

    /* Get a category's subcategory objects.  Names are indented according
       to subcategory level */
    function getSubCatList($iLevel = 0)
    {
        $aOut = array();
        $iId = $this->iCatId ? $this->iCatId : 0;

        $sIndent = '';
        for($i = 0; $i < $iLevel; $i++)
            $sIndent .= '&nbsp; &nbsp;';

        $hResult = query_parameters("SELECT * FROM appCategory WHERE catParent = '?'
                                     ORDER BY catName", $iId);

        while($oRow = query_fetch_object($hResult))
        {
            $oCat = new category($oRow->catId);
            $oCat->sName = $sIndent.$oCat->sName;
            $aOut[] = $oCat;
            $aOut = array_merge($aOut, $oCat->getSubCatList($iLevel + 1));
        }
        return $aOut;
    }

    /* Get all category objects, ordered and with category names indented
       according to subcategory level.
       Optionally includes the 'Main' top category. */
    static function getOrderedList($bIncludeMain = false)
    {
        $oCat = new category();

        if(!$bIncludeMain)
            return $oCat->getSubCatList(0);

        $oCat->sName = 'Main';
        $aCats = array($oCat);
        $aCats = array_merge($aCats, $oCat->getSubCatList(1));

        return $aCats;
    }

    /* Returns an SQL statement that will match items in the current category
       and all sub-categories */
    public function getSqlQueryPart()
    {
        $sRet = '';
        $aSubCats = $this->getSubCatList();
        $sRet .= " ( catId = '{$this->iCatId}' ";
        foreach($aSubCats as $oCat)
        {
            $iCatId = $oCat->objectGetId();
            $sRet .= " OR catId = '$iCatId' ";
        }
        $sRet .= ") ";

        return $sRet;
    }

    function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        /* We don't send notification mails */
                return array(null, null, null);
    }

    /**
     * returns a path like:
     *
     *     { ROOT, Games, Simulation }
     */
    function getCategoryPath()
    {
        $aPath = array();
        $iCatId  = $this->iCatId;

        /* loop, working up through categories until we have no parent */
        while($iCatId != 0)
        {
            $hResult = query_parameters("SELECT catName, catId, catParent FROM appCategory WHERE catId = '?'",
                                       $iCatId);
            if(!$hResult || query_num_rows($hResult) != 1)
                break;
            $oCatRow = query_fetch_object($hResult);
            $aPath[] = array($oCatRow->catId, $oCatRow->catName);
            $iCatId = $oCatRow->catParent;
        }
        $aPath[] = array(0, "ROOT");
        return array_reverse($aPath);
    }

    /* return the total number of applications in this category */
    function getApplicationCount($depth = null)
    {
        $MAX_DEPTH = 5;

        if($depth)
            $depth++;
        else
            $depth = 0;

        /* if we've reached our max depth, just return 0 and stop recursing */
        if($depth >= $MAX_DEPTH)
            return 0;

        $totalApps = 0;

        /* add on all apps in each category this category includes */
        if($this->aSubcatsIds)
        {
            while(list($i, $iSubcatId) = each($this->aSubcatsIds))
            {
                $subCat = new Category($iSubcatId);
                $totalApps += $subCat->getApplicationCount($depth);
            }
        }

        $totalApps += sizeof($this->aApplicationsIds); /* add on the apps at this category level */
        
        return $totalApps;
    }

    /**
     * create the Category: line at the top of appdb pages$
     */
    function make_cat_path($aPath, $iAppId = '', $iVersionId = '')
    {
        $sStr = "";
        $iCatCount = 0;
        while(list($iCatIdx, list($iCatId, $sName)) = each($aPath))
        {
            if($sName == "ROOT")
                $sCatname = "Main";
            else
                $sCatname = $sName;

            if ($iCatCount > 0) $sStr .= " &gt; ";
            $sStr .= html_ahref($sCatname,"objectManager.php?sClass=category&iId=$iCatId&sAction=view&sTitle=Browse+Applications");
            $iCatCount++;
        }

        if($iAppId)
        {
            $oApp = new Application($iAppId);
            if($iVersionId)
            {
                $oVersion = new Version($iVersionId);
                $sStr .= " &gt; ".$oApp->objectMakeLink();
                $sStr .= " &gt; ".$oVersion->sName;
            } else
            {
                $sStr .= " &gt; ".$oApp->sName;
            }
        }

        return $sStr;
    }

    public function objectGetState()
    {
        // We currenly don't queue categories
        return 'accepted';
    }

    function objectGetId()
    {
        return $this->iCatId;
    }

    public function objectMakeLink()
    {
        return '<a href="'.$this->objectMakeUrl()."\">{$this->sName}</a>'";
    }

    public function objectMakeUrl()
    {
        return BASE."objectManager.php?sClass=category&sAction=view&iId={$this->iCatId}&sTitle=Browse+Applications";
    }

    public function objectAllowNullId($sAction)
    {
        switch($sAction)
        {
            case 'view':
                return true;

            default:
                return false;
        }
    }

    function objectGetSubmitterId()
    {
        /* We don't log that */
        return 0;
    }

    function objectGetCustomVars($sAction)
    {
        switch($sAction)
        {
            case 'add':
                return array('iParentId');

            default:
                return null;
        }
    }

    function outputEditor($aValues = null)
    {
        $aCategories = category::getOrderedList(true);
        $aCatNames = array();
        $aCatIds = array();

        $iParentId = $this->iParentId;
        if(!$iParentId && $aValues)
            $iParentId = getInput('iParentId', $aValues);

        foreach($aCategories as $oCategory)
        {
            $aCatNames[] = $oCategory->sName;
            $aCatIds[] = $oCategory->objectGetId();
        }

        echo "<table border=\"0\" width=\"100%\" cellspacing=\"0\" cellpadding=\"2\">
                <tr>
                <td width=\"15%\" class=\"box-label\"><b>Category name</b></td>
                <td class=\"box-body\">
                <input type=\"text\" size=\"50\" name=\"sName\" value=\"".$this->sName."\"> 
                </td>
                </tr>
                <tr>
                <td width=\"15%\" class=\"box-label\"><b>Description</b></td>
                <td class=\"box-body\">
                <input type=\"text\" size=\"50\" name=\"sDescription\" value=\"".$this->sDescription."\"> 
                </td>
                </tr>
                <tr>
                <td width=\"15%\" class=\"box-label\"><b>Parent</b></td>
                <td class=\"box-body\">
                ".html_select("iParentId",$aCatIds,$iParentId, $aCatNames)." 
                </td>
                </tr>
                </table>";
    }

    function allowAnonymousSubmissions()
    {
        return FALSE;
    }

    function getOutputEditorValues($aClean)
    {
        $this->sName = $aClean['sName'];
        $this->iParentId = $aClean['iParentId'];
        $this->sDescription = $aClean['sDescription'];
    }

    function mustBeQueued()
    {
        return $_SESSION['current']->hasPriv('admin');
    }

    function canEdit()
    {
        return $_SESSION['current']->hasPriv('admin');
    }

    /**
     * display the full path of the Category we are looking at
     */
    function displayPath($appId, $versionId = '')
    {
        $sCatFullPath = Category::make_cat_path($this->getCategoryPath(), $appId, $versionId);
        echo html_frame_start("",'98%','',2);
        echo "<p><b>Category: ". $sCatFullPath ."</b><br>\n";
        echo html_frame_end();
    }

    public function display()
    {
        // list sub categories
        $sCatFullPath = Category::make_cat_path($this->getCategoryPath());
        $aSubs = $this->aSubcatsIds;

        echo "<div class='default_container'>\n";

        // Allow editing categories
        if($this->canEdit())
        {
            $oM = new objectManager('category', '', $this->iCatId);
            $oM->setReturnTo($this->objectMakeUrl());
            echo "<p>\n";
            echo '<a href="'.$oM->makeUrl('add', null, 'Add Category')."&iParentId={$this->iCatId}\">Add</a>";;
            if($this->iCatId) // We can't edit the 'Main' category
            {
                echo ' &nbsp; &nbsp; ';
                echo '<a href="'.$oM->makeUrl('edit', $this->iCatId, 'Edit Category').'">Edit</a>';
                echo ' &nbsp; &nbsp; ';
                echo '<a href="'.$oM->makeUrl('delete', $this->iCatId, 'Delete Category').'">Delete</a>';
            }
            echo "</p>\n";
        }

        // Output sub-categories
        if($aSubs)
        {
            echo html_frame_start("",'98%','',2);
            echo "<p><b>Category: ". $sCatFullPath ."</b><br>\n";
            echo html_frame_end();

            echo html_frame_start("","98%","",0);

            $oTable = new Table();
            $oTable->SetWidth("100%");
            $oTable->SetBorder(0);
            $oTable->SetCellPadding(3);
            $oTable->SetCellSpacing(1);

            $oTableRow = new TableRow();
            $oTableRow->SetClass("color4");
            $oTableRow->AddTextCell("Sub Category");
            $oTableRow->AddTextCell("Description");
            $oTableRow->AddTextCell("No. Apps");
            $oTable->SetHeader($oTableRow);
            
            while(list($i,$iSubcatId) = each($aSubs))
            {
                $oSubCat= new Category($iSubcatId);

                //set row color
                $sColor = ($i % 2) ? "color0" : "color1"; 

                $oTableRowHighlight = GetStandardRowHighlight($i);

                $sUrl = $oSubCat->objectMakeUrl();

                $oTableRowClick = new TableRowClick($sUrl);
                $oTableRowClick->SetHighlight($oTableRowHighlight);

                //get number of apps in this sub-category
                $iAppcount = $oSubCat->getApplicationCount();

                //format desc
                $sDesc = substr(stripslashes($oSubCat->sDescription),0,70);

                //display row
                $oTableRow = new TableRow();
                $oTableRow->SetClass($sColor);
                $oTableRow->SetRowClick($oTableRowClick);

                $oTableCell = new TableCell($oSubCat->sName);
                $oTableCell->SetCellLink($sUrl);
                $oTableRow->AddCell($oTableCell);
                $oTableRow->AddTextCell("$sDesc &nbsp;");
                $oTableRow->AddTextCell("$iAppcount &nbsp;");

                $oTable->AddRow($oTableRow);
            }

            // output the table
            echo $oTable->GetString();

            echo html_frame_end( count($aSubs) . ' categories');
        }


        // list applications in this category
        $aApps = $this->aApplicationsIds;
        if($aApps)
        {
            echo html_frame_start("",'98%','',2);
            echo "<p><b>Category: ". $sCatFullPath ."</b><br>\n";
            echo html_frame_end();
            
            echo html_frame_start("","98%","",0);

            $oTable = new Table();
            $oTable->SetWidth("100%");
            $oTable->SetBorder(0);
            $oTable->SetCellPadding(3);
            $oTable->SetCellSpacing(1);

            $oTableRow = new TableRow();
            $oTableRow->SetClass("color4");
            $oTableRow->AddTextCell("Application name");
            $oTableRow->AddTextCell("Description");
            $oTableRow->AddTextCell("No. Versions");

            $oTable->SetHeader($oTableRow);
                    
            while(list($i, $iAppId) = each($aApps))
            {
                $oApp = new Application($iAppId);

                //set row color
                $sColor = ($i % 2) ? "color0" : "color1";

                $oTableRowHighlight = GetStandardRowHighlight($i);

                $sUrl = $oApp->objectMakeUrl();

                $oTableRowClick = new TableRowClick($sUrl);
                $oTableRowClick->SetHighlight($oTableRowHighlight);
                
                //format desc
                $sDesc = util_trim_description($oApp->sDescription);
                
                //display row
                $oTableRow = new TableRow();
                $oTableRow->SetRowClick($oTableRowClick);
                $oTableRow->SetClass($sColor);
                $oTableRow->AddTextCell($oApp->objectMakeLink());
                $oTableRow->AddTextCell("$sDesc &nbsp;");
                $oTableRow->AddTextCell(sizeof($oApp->aVersionsIds));

                $oTable->AddRow($oTableRow);
            }
            
            // output table
            echo $oTable->GetString();

            echo html_frame_end( count($aApps) . " applications in this category");
        }

        // Show a message if this category is empty
        if(!$aApps && !$aSubs)
        {
            echo html_frame_start("",'98%','',2);
            echo "<p><b>Category: ". $sCatFullPath ."</b><br>\n";
            echo html_frame_end();

            echo html_frame_start('','90%','',2);
            echo 'This category has no sub-categories or applications';
            echo html_frame_end();
        }
    }
}

?>
