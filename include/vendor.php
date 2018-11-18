<?php
/**********************************/
/* this class represents a vendor */
/**********************************/

/**
 * Vendor class for handling developers.
 */
class Vendor {
    var $iVendorId;
    var $sName;
    var $sWebpage;
    private $sState;
    var $aApplicationsIds;  // an array that contains the appId of every application linked to this vendor

    /**    
     * constructor, fetches the data.
     */
    function __construct($iVendorId = null, $oRow = null)
    {
        // we are working on an existing vendor
        if(!$iVendorId && !$oRow)
            return;

        if(!$oRow)
        {
            /*
                * We fetch the data related to this vendor.
                */
            $sQuery = "SELECT *
                        FROM vendor
                        WHERE vendorId = '?'";
            if($hResult = query_parameters($sQuery, $iVendorId))
                $oRow = query_fetch_object($hResult);
        }

        if($oRow)
        {
            $this->iVendorId = $oRow->vendorId;
            $this->sName = $oRow->vendorName;
            $this->sWebpage = $oRow->vendorURL;
            $this->sState = $oRow->state;
        }

        /*
            * We fetch applicationsIds. 
            */
        $sQuery = "SELECT appId
                    FROM appFamily
                    WHERE vendorId = '?' ORDER by appName";
        if($hResult = query_parameters($sQuery, $this->iVendorId))
        {
            while($oRow = query_fetch_object($hResult))
            {
                $this->aApplicationsIds[] = $oRow->appId;
            }
        }
    }

    /**
     * Creates a new vendor.
     *
     * NOTE: If creating a vendor with the same name as an existing vendor
     *       we retrieve the existing vendors information and return true,
     *       even though we didn't create the vendor, this makes it easier
     *       for the user of the vendor class.
     */
    function create()
    {
        /* Check for duplicates */
        $hResult = query_parameters("SELECT * FROM vendor WHERE vendorName = '?' AND state != 'deleted'",
                                   $this->sName);
        if($hResult && $oRow = query_fetch_object($hResult))
        {
            if(query_num_rows($hResult))
            {
                Vendor::__construct($oRow->vendorId);

                if(!$this->mustBeQueued())
                    $this->unQueue();

                /* Even though we did not create a new vendor, the caller is provided
                with an id and can proceed as normal, so we return TRUE */
                return TRUE;
            }
        }

        $hResult = query_parameters("INSERT INTO vendor (vendorName, vendorURL, state) ".
                                    "VALUES ('?', '?', '?')",
                                        $this->sName, $this->sWebpage,
                                        $this->mustBeQueued() ? 'queued' : 'accepted');
        if($hResult)
        {
            $this->iVendorId = query_appdb_insert_id();
            Vendor::__construct($this->iVendorId);
            return true;
        }
        else
        {
            addmsg("Error while creating a new developer.", "red");
            return false;
        }
    }

    /**
     * Un-queue vendor
     * Returns TRUE or FALSE
     */
    function unQueue()
    {
        $hResult = query_parameters("UPDATE vendor SET state = '?' WHERE vendorId = '?'",
                                       'accepted', $this->iVendorId);

        if(!$hResult)
            return FALSE;

        $this->sState = 'accepted';

        return TRUE;
    }

    /**
     * Update vendor.
     * Returns true on success and false on failure.
     */
    function update()
    {
        if(!$this->iVendorId)
            return $this->create();

        if($this->sName)
        {
            if (!query_parameters("UPDATE vendor SET vendorName = '?' WHERE vendorId = '?'",
                                  $this->sName, $this->iVendorId))
                return false;
        }

        if($this->sWebpage)
        {
            if (!query_parameters("UPDATE vendor SET vendorURL = '?' WHERE vendorId = '?'",
                                  $this->sWebpage, $this->iVendorId))
                return false;
        }
        return true;
    }

    /**
    * Remove the vendor from the database. 
    */
    function purge()
    {
        if(sizeof($this->aApplicationsIds)>0)
        {
            return FALSE;
        } else
        {
            $sQuery = "DELETE FROM vendor 
                    WHERE vendorId = '?' 
                    LIMIT 1";
            if(query_parameters($sQuery, $this->iVendorId))
            {
                return TRUE;
            }

            return FALSE;
        }

        return false;
    }

    /**
     * Flag the vendor as deleted
     */
    function delete()
    {
        if(sizeof($this->aApplicationsIds)>0)
        {
            return FALSE;
        } else
        {
            $sQuery = "UPDATE vendor SET state = 'deleted'
                       WHERE vendorId = '?' 
                       LIMIT 1";
            if(query_parameters($sQuery, $this->iVendorId))
            {
                return TRUE;
            }

            return FALSE;
        }

        return false;
    }

    function checkOutputEditorInput($aClean)
    {
        if(!getInput('sVendorName', $aClean))
            return '<li>You need to enter the developer\'s name</li>';
    }

    function outputEditor()
    {
      $oTable = new Table();
      $oTable->SetWidth("100%");
      $oTable->SetBorder(0);
      $oTable->SetCellPadding(2);
      $oTable->SetCellSpacing(0);

      // name
      $oTableRow = new TableRow();

      $oTableCell = new TableCell("Developer name:");
      $oTableCell->SetAlign("right");
      $oTableCell->SetClass("color0");
      $oTableCell->SetBold(true);
      $oTableRow->AddCell($oTableCell);

      $oTableCell = new TableCell('<input type=text name="sVendorName" value="'.$this->sName.'" size="60">');
      $oTableCell->SetClass("color0");
      $oTableRow->AddCell($oTableCell);

      $oTable->AddRow($oTableRow);

      // Url
      $oTableRow = new TableRow();

      $oTableCell = new TableCell("Developer URL:");
      $oTableCell->SetAlign("right");
      $oTableCell->SetClass("color0");
      $oTableCell->SetBold(true);
      $oTableRow->AddCell($oTableCell);

      $oTableCell = new TableCell('<input type=text name="sVendorWebpage" value="'.$this->sWebpage.'" size="60">');
      $oTableCell->SetClass("color0");
      $oTableRow->AddCell($oTableCell);

      $oTable->AddRow($oTableRow);

      echo $oTable->GetString();

      echo  '<input type="hidden" name="iVendorId" value="'.$this->iVendorId.'">',"\n";
    }

    public static function objectGetSortableFields()
    {
        return array('vendorName');
    }

    public static function objectGetDefaultSort()
    {
        return 'vendorName';
    }

    function objectGetFilterInfo()
    {
        $oFilter = new FilterInterface();

        $oFilter->AddFilterInfo('vendorName', 'Name', array(FILTER_CONTAINS, FILTER_STARTS_WITH, FILTER_ENDS_WITH), FILTER_VALUES_NORMAL);
        return $oFilter;
    }

    function objectGetEntries($sState, $iRows = 0, $iStart = 0, $sOrderBy = 'vendorName', $bAscending = TRUE, $oFilter = null)
    {
        /* Not implemented */
        if($sState == 'rejected')
            return FALSE;

        $sWhereFilter = $oFilter ? $oFilter->getWhereClause() : '';
        $sOrder = $bAscending ? 'ASC' : 'DESC';

        if($sWhereFilter)
            $sWhereFilter = " AND $sWhereFilter";

        if(!$iRows)
            $iRows = Vendor::objectGetEntriesCount($sState, $oFilter);

        $hResult = query_parameters("SELECT * FROM vendor WHERE state = '?' $sWhereFilter
                                     ORDER BY $sOrderBy $sOrder LIMIT ?,?",
                                     $sState, $iStart, $iRows);

        if(!$hResult)
            return FALSE;

        return $hResult;
    }

    function objectGetHeader()
    {
        $oTableRow = new TableRowSortable();

        $oTableRow->AddSortableTextCell('Name', 'vendorName');

        $oTableCell = new TableCell('Applications');
        $oTableRow->AddCell($oTableCell);

        $oTableRow->AddTextCell("Website");

        return $oTableRow;
    }

    // returns an OMTableRow instance
    function objectGetTableRow()
    {
        $bDeleteLink = sizeof($this->aApplicationsIds) ? FALSE : TRUE;

        // create the html table row
        $oTableRow = new TableRow();
        $oTableRow->AddTextCell($this->objectMakeLink());

        $oTableCell = new TableCell(sizeof($this->aApplicationsIds));
        $oTableRow->AddCell($oTableCell);

        $oTableCell = new TableCell($this->sWebpage);
        $oTableCell->SetCellLink($this->sWebpage);
        $oTableRow->AddCell($oTableCell);

        // create the object manager specific row
        $oOMTableRow = new OMTableRow($oTableRow);
        $oOMTableRow->SetHasDeleteLink($bDeleteLink);

        return $oOMTableRow;
    }

    public function objectGetState()
    {
        return $this->sState;
    }

    function canEdit()
    {
        if($_SESSION['current']->hasPriv("admin"))
            return TRUE;
        else
            return FALSE;
    }

    function mustBeQueued()
    {
        if($_SESSION['current']->hasPriv("admin"))
            return FALSE;
        else
            return TRUE;
    }

    function objectGetMailOptions($sAction, $bMailSubmitter, $bParentAction)
    {
        return new mailOptions();
    }

    function objectGetChildren($bIncludeDeleted = false)
    {
        /* We don't have any */
        return array();
    }

    function objectGetMail($sAction, $bMailSubmitter, $bParentAction)
    {
        /* We don't send notification mails */
        return array(null, null, null);
    }

    function objectGetSubmitterId()
    {
        /* We don't record the submitter id */
        return NULL;
    }

    function getOutputEditorValues($aClean)
    {
        $this->sName = $aClean['sVendorName'];
        $this->sWebpage = $aClean['sVendorWebpage'];
    }

    function display()
    {
        echo 'Developer name: '.$this->sName,"\n";
        if($this->canEdit())
        {
            echo "[<a href=\"".$_SERVER['PHP_SELF']."?sClass=vendor&amp;sAction=edit&amp;".
                 "iId=$this->iVendorId&amp;sTitle=Edit%20Developer\">edit</a>]";
        }

        echo '<br>',"\n";
        if ($this->sWebpage)
        {
            echo 'Developer URL:  <a href="'.$this->sWebpage.'">'.
                 $this->sWebpage.'</a> <br>',"\n";
        }


        if($this->aApplicationsIds)
        {
            echo '<br>Applications by '.$this->sName.'<br><br>',"\n";

            $oTable = new Table();

            $oRow = new TableRow();
            $oRow->AddTextCell('Name');
            $oRow->AddTextCell('Rating');
            $oRow->AddTextCell('Tested Wine version');
            $oRow->SetClass('color4');
            $oRow->SetStyle('color: #ffffff;');
            $oTable->AddRow($oRow);

            $i = 0;

            foreach($this->aApplicationsIds as $iAppId)
            {
                $oApp  = new Application($iAppId);

                if($oApp->objectGetState() != 'accepted')
                    continue;

                $i++;
                $oRow = new TableRow();
                $oRow->SetClass(($i % 2) ? 'color0' : 'color1');
                $oRow->AddTextCell($oApp->objectMakeLink());
                $oRow->AddTextCell('');
                $oRow->AddTextCell('');
                $oTable->AddRow($oRow);

                $aVersions = $oApp->objectGetChildrenClassSpecific('version');
                
                foreach($aVersions as $oVersion)
                {
                    if($oVersion->objectGetState() != 'accepted')
                        continue;
                        
                    $i++;
                    $oRow = new TableRow();
                    $oRow->SetClass(($i % 2) ? 'color0' : 'color1');
                    $oRow->AddTextCell(' &nbsp; '.$oVersion->objectMakeLink());
                    
                    $oCell = new TableCell($oVersion->sTestedRating);
                    $oCell->setClass($oVersion->sTestedRating);
                    $oRow->AddCell($oCell);

                    $oCell = new TableCell($oVersion->sTestedRelease);
                    $oCell->setClass($oVersion->sTestedRating);
                    $oRow->AddCell($oCell);

                    $oTable->AddRow($oRow);
                }
                
            }
            echo $oTable->GetString(),"\n";
        }
    }

    public function objectGetClassDisplayName()
    {
        return 'developer';
    }

    /* Make a URL for viewing the specified vendor */
    function objectMakeUrl()
    {
        $oManager = new objectManager("vendor", "View Developer");
        return $oManager->makeUrl("view", $this->iVendorId);
    }

    /* Make a HTML link for viewing the specified vendor */
    function objectMakeLink()
    {
        return "<a href=\"".$this->objectMakeUrl()."\">$this->sName</a>";
    }

    function objectGetEntriesCount($sState, $oFilter = null)
    {
        /* Not implemented */
        if($sState == 'rejected')
            return FALSE;

        $sWhereClause = $oFilter ? $oFilter->getWhereClause() : '';
        if($sWhereClause)
            $sWhereClause = " AND $sWhereClause";

        $hResult = query_parameters("SELECT COUNT(vendorId) as count FROM vendor WHERE state = '?' $sWhereClause",
                                     $sState);

        if(!$hResult)
            return FALSE;

        if(!$oRow = query_fetch_object($hResult))
            return FALSE;

        return $oRow->count;
    }

    function allowAnonymousSubmissions()
    {
        return FALSE;
    }

    function objectMoveChildren($iNewId)
    {
        /* Keep track of how many children we have modified */
        $iCount = 0;

        foreach($this->aApplicationsIds as $iAppId)
        {
            $oApp = new application($iAppId);
            $oApp->iVendorId = $iNewId;
            if($oApp->update(TRUE))
                $iCount++;
            else
                return FALSE;
        }

        return $iCount;
    }

    function objectGetId()
    {
        return $this->iVendorId;
    }

    function objectGetItemsPerPage($sState = 'accepted')
    {
        $aItemsPerPage = array(25, 50, 100, 200);
        $iDefaultPerPage = 25;
        return array($aItemsPerPage, $iDefaultPerPage);
    }

    function objectShowAddEntry()
    {
        return TRUE;
    }
}


