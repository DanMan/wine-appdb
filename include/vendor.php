<?php
/**********************************/
/* this class represents a vendor */
/**********************************/

/**
 * Vendor class for handling vendors.
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
    function Vendor($iVendorId = null, $oRow = null)
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
        $hResult = query_parameters("SELECT * FROM vendor WHERE vendorName = '?'",
                                   $this->sName);
        if($hResult && $oRow = query_fetch_object($hResult))
        {
            if(query_num_rows($hResult))
            {
                $this->vendor($oRow->vendorId);

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
            $this->vendor($this->iVendorId);
            return true;
        }
        else
        {
            addmsg("Error while creating a new vendor.", "red");
            return false;
        }
    }

    /**
     * Un-queue vendor
     * Returns TRUE or FALSE
     */
    function unQueue()
    {
        if(!$this->canEdit())
            return FALSE;

        $hResult = query_parameters("UPDATE vendor SET state = '?' WHERE vendorId = '?'",
                                       'accepted', $this->iVendorId);

        if(!$hResult)
            return FALSE;

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
            $this->sName = $sName;
        }

        if($this->sWebpage)
        {
            if (!query_parameters("UPDATE vendor SET vendorURL = '?' WHERE vendorId = '?'",
                                  $this->sWebpage, $this->iVendorId))
                return false;
            $this->sWebpage = $sWebpage;
        }
        return true;
    }


    /**
     * Deletes the vendor from the database. 
     */
    function delete()
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

    function outputEditor()
    {
      $oTable = new Table();
      $oTable->SetWidth("100%");
      $oTable->SetBorder(0);
      $oTable->SetCellPadding(2);
      $oTable->SetCellSpacing(0);

      // name
      $oTableRow = new TableRow();

      $oTableCell = new TableCell("Vendor Name:");
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

      $oTableCell = new TableCell("Vendor URL:");
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

    function objectGetEntries($bQueued, $bRejected, $iRows = 0, $iStart = 0, $sOrderBy = 'vendorName', $bAscending = TRUE)
    {
        /* Vendor queueing is not implemented yet */
        if($bQueued)
            return FALSE;

        /* Not implemented */
        if($bRejected)
            return FALSE;

        $sOrder = $bAscending ? 'ASC' : 'DESC';

        if(!$iRows)
            $iRows = Vendor::objectGetEntriesCount($bQueued, $bRejected);

        $hResult = query_parameters("SELECT * FROM vendor
                       ORDER BY $sOrderBy $sOrder LIMIT ?,?",
                           $iStart, $iRows);

        if(!$hResult)
            return FALSE;

        return $hResult;
    }

    function objectGetHeader()
    {
        $oTableRow = new TableRowSortable();
        
        $oTableRow->AddSortableTextCell('Name', 'vendorName');

        $oTableRow->AddTextCell("Website");

        $oTableCell = new TableCell("Linked apps");
        $oTableCell->SetAlign("right");
        $oTableRow->AddCell($oTableCell);

        return $oTableRow;
    }

    // returns an OMTableRow instance
    function objectGetTableRow()
    {
        $bDeleteLink = sizeof($this->aApplicationsIds) ? FALSE : TRUE;

        // create the html table row
        $oTableRow = new TableRow();
        $oTableRow->AddTextCell($this->objectMakeLink());

        $oTableCell = new TableCell($this->sWebpage);
        $oTableCell->SetCellLink($this->sWebpage);
        $oTableRow->AddCell($oTableCell);

        $oTableCell = new TableCell(sizeof($this->aApplicationsIds));
        $oTableCell->SetAlign("right");
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

    function objectGetChildren()
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
        echo 'Vendor Name: '.$this->sName,"\n";
        if($this->canEdit())
        {
            echo "[<a href=\"".$_SERVER['PHP_SELF']."?sClass=vendor&sAction=edit&".
                 "iId=$this->iVendorId&sTitle=Edit%20Vendor\">edit</a>]";
        }

        echo '<br />',"\n";
        if ($this->sWebpage)
        {
            echo 'Vendor URL:  <a href="'.$this->sWebpage.'">'.
                 $this->sWebpage.'</a> <br />',"\n";
        }


        if($this->aApplicationsIds)
        {
            echo '<br />Applications by '.$this->sName.'<br /><ol>',"\n";
            foreach($this->aApplicationsIds as $iAppId)
            {
                $oApp  = new Application($iAppId);

                if($oApp->sQueued == "false")
                    echo '<li>'.$oApp->objectMakeLink().'</li>',"\n";
            }
            echo '</ol>',"\n";
        }
    }

    /* Make a URL for viewing the specified vendor */
    function objectMakeUrl()
    {
        $oManager = new objectManager("vendor", "View Vendor");
        return $oManager->makeUrl("view", $this->iVendorId);
    }

    /* Make a HTML link for viewing the specified vendor */
    function objectMakeLink()
    {
        return "<a href=\"".$this->objectMakeUrl()."\">$this->sName</a>";
    }

    function objectGetEntriesCount($bQueued, $bRejected)
    {
        /* Not implemented */
        if($bQueued)
            return FALSE;

        /* Not implemented */
        if($bRejected)
            return FALSE;

        $hResult = query_parameters("SELECT COUNT(vendorId) as count FROM vendor");

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

    function objectGetItemsPerPage($bQueued = false)
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

?>
