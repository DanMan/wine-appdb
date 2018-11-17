<?php

// classes for managing tables and data related to tables

class TableRowClick
{
  var $shUrl;

  public function __construct($shUrl)
  {
    $this->shUrl = $shUrl;
  }

  public function GetString()
  {
    return " data-donav=\"{$this->shUrl}\"";
  }
}

class TableCell
{
  private $sCell;
  private $sStyle;
  private $sClass;
  private $sAlign;  // align="$sAlign" will be output if this is not null
  private $sValign; // valign="$sValign" will be output if this is not null
  private $sWidth;  // width="$sWidth"
  private $sUrl;    // wraps the cell contents in an anchor tag if $sUrl is not null
  private $bBold;   // if true will output the cell contents as bold

  // NOTE: We specifically have limited the parameters to the constructor
  //       to only the contents of the cell. Additional parameters, while
  //       appearing convienent, make the parameters confusing
  //       Use accessors to set additional parameters.
  public function __construct($sCellContents)
  {
    $this->sCellContents = $sCellContents;
    $this->sStyle = null;
    $this->sClass = null;
    $this->sAlign = null;
    $this->sValign = null;
    $this->sWidth = null;
    $this->bBold = false;
  }

  public function SetCellContents($sCellContents)
  {
    $this->sCellContents = $sCellContents;
  }

  public function SetStyle($sStyle)
  {
    $this->sStyle = $sStyle;
  }

  public function SetClass($sClass)
  {
    $this->sClass = $sClass;
  }

  public function SetAlign($sAlign)
  {
    $this->sAlign = $sAlign;
  }

  public function SetValign($sValign)
  {
    $this->sValign = $sValign;
  }

  public function SetWidth($sWidth)
  {
    $this->sWidth = $sWidth;
  }

  public function SetCellLink($sUrl)
  {
    $this->sUrl = $sUrl;
  }

  public function SetBold($bBold)
  {
    $this->bBold = $bBold;
  }

  public function GetString()
  {
    $sStr = "<td";

    if($this->sClass)
      $sStr.=" class=\"".$this->sClass."\"";

    if($this->sStyle)
      $sStr.=" style=\"".$this->sStyle."\"";

    if($this->sAlign)
      $sStr.=" align=\"".$this->sAlign."\"";

    if($this->sValign)
      $sStr.=" valign=\"".$this->sValign."\"";

    if($this->sWidth)
      $sStr.=" width=\"".$this->sWidth."\"";

    $sStr.=">";

    // if we have a url, output the start of the anchor tag
    if($this->sUrl)
      $sStr.='<a href="'.$this->sUrl.'">';

    if($this->bBold)
      $sStr.='<b>';

    // output the contents of the cell
    $sStr.=$this->sCellContents;

    if($this->bBold)
      $sStr.='</b>';

    // if we have a url, close the anchor tag
    if($this->sUrl)
      $sStr.='</a>';

    $sStr.="</td>";

    return $sStr;
  }
}

class TableRow
{
    protected $aTableCells; // array that contains the cells for the table row
    private $sStyle; // CSS style to be used
    private $sClass; // CSS class to be used
    private $sValign; // valign="$sValign" - if this variable is set

    private $oTableRowClick; // information about whether the table row is clickable etc

    public function __construct()
    {
      $this->aTableCells = array();
      $this->sStyle = null;
      $this->sClass = null;
      $this->sValign = null;
      $this->oTableRowClick = null;
    }

    public function AddCell(TableCell $oTableCell)
    {
      $this->aTableCells[] = $oTableCell;
    }

    public function AddCells($aTableCells)
    {
      foreach($aTableCells as $oTableCell)
      {
        $this->AddCell($oTableCell);
      }
    }

    public function AddTextCell($sCellText)
    {
      $this->AddCell(new TableCell($sCellText));
    }

    public function SetStyle($sStyle)
    {
      $this->sStyle = $sStyle;
    }

    public function SetClass($sClass)
    {
      $this->sClass = $sClass;
    }

    public function SetValign($sValign)
    {
      $this->sValign = $sValign;
    }

    public function SetRowClick($oTableRowClick)
    {
      $this->oTableRowClick = $oTableRowClick;
    }

    // get a string that contains the html representation
    // of this table row
    public function GetString()
    {
      // generate the opening of the tr element
      $sStr = "<tr";

      if($this->sClass)
        $sStr.= " class=\"$this->sClass\"";

      if($this->sStyle)
        $sStr.= " style=\"$this->sStyle\"";

      if($this->sValign)
        $sStr.= " valign=\"$this->sValign\"";

      if($this->oTableRowClick)
        $sStr.= " ".$this->oTableRowClick->GetString();
      
      $sStr.= ">"; // close the opening tr

      // process the td elements
      foreach($this->aTableCells as $oTableCell)
      {
        $sStr.=$oTableCell->GetString();
      }

      // close the table row
      $sStr.= "</tr>";

      return $sStr;
    }

    public function GetClass()
    {
      return $this->sClass;
    }

    public function GetTableRowClick()
    {
      return $this->oTableRowClick;
    }
}

/* Class for a sortable table row.  The user can click on the header for a sortable field, and it
   will alternate between sorting that by ascending/descending order and the default sorting */
class TableRowSortable extends TableRow
{
    private $aSortVars; /* Array of sort variables.  Not all fields have to be sortable.
                           This is paired with the aTableCells array from TableRow */

    public function __construct()
    {
        $this->aSortVars = array();
        parent::__construct();
    }

    /* Adds a table cell without sorting */
    public function AddCell(TableCell $oCell)
    {
        $this->aTableCells[] = $oCell;
        $this->aSortVars[] = '';
    }

    /* Adds a text cell without sorting */
    public function AddTextCell($shText)
    {
        $this->AddCell(new TableCell($shText));
    }

    /* Adds a text cell with a sorting var */
    public function AddSortableTextCell($shText, $sSortVar)
    {
        $this->aTableCells[] = new TableCell($shText);
        $this->aSortVars[] = $sSortVar;
    }

    /* Sets sorting info on all cells that are sortable */
    public function SetSortInfo(TableSortInfo $oSortInfo)
    {
        for($i = 0; $i < sizeof($this->aTableCells); $i++)
        {
            $sSortVar = $this->aSortVars[$i];

            if($sSortVar)
            {
                $bAscending = TRUE;

                if($this->aSortVars[$i] == $oSortInfo->sCurrentSort)
                {
                    if($oSortInfo->bAscending)
                        $bAscending = FALSE;
                    else
                        $sSortVar = '';

                    $this->aTableCells[$i]->sCellContents .= $oSortInfo->bAscending ?
                                                             ' &#9650;' : ' &#9660;';
                }

                $sAscending = $bAscending == TRUE ? 'true': 'false';
                $this->aTableCells[$i]->SetCellLink($oSortInfo->shUrl."sOrderBy=$sSortVar&amp;bAscending=$sAscending");
            }
        }
    }
}

/* Container for table sorting info, used to hold the current sort order */
class TableSortInfo
{
    var $sCurrentSort;
    var $bAscending;
    var $shUrl;

    public function __construct($shUrl, $sCurrentSort = '', $bAscending = TRUE)
    {
        $this->sCurrentSort = $sCurrentSort;
        $this->shUrl = $shUrl;
        $this->bAscending = $bAscending;
    }

    /* Parses an array of HTTP vars to determine current sort settings.
       Optionally checks the sort var against an array of legal values */
    public function ParseArray($aClean, $aLegalValues = null)
    {
        $sCurrentSort = key_exists('sOrderBy', $aClean) ? $aClean['sOrderBy'] : '';

        if($aLegalValues && array_search($sCurrentSort, $aLegalValues) === FALSE)
            return;

        $this->sCurrentSort = $sCurrentSort;
        $this->bAscending = key_exists('bAscending', $aClean) ?
                                 ($aClean['bAscending'] == 'false') ? false : true : true;
    }
}

// object manager table row, has additional parameters used by the object manager
// when outputting a table row
//TODO: php5 consider inheriting from HtmlTableRow since this class is really an
//  extension of that class
class OMTableRow
{
  private $oTableRow;
  private $bHasDeleteLink;
  private $bCanEdit;

  public function __construct($oTableRow)
  {
    $this->oTableRow = $oTableRow;
    $this->bHasDeleteLink = false;
    $this->bCanEdit = false;
  }

  public function SetHasDeleteLink($bHasDeleteLink)
  {
    $this->bHasDeleteLink = $bHasDeleteLink;
  }

  public function GetHasDeleteLink()
  {
    return $this->bHasDeleteLink;
  }

  public function SetRowClickable(TableRowClick $oTableRowClick)
  {
    $this->oTableRowClick = $oTableRowClick;
  }

  public function SetStyle($sStyle)
  {
    $this->oTableRow->SetStyle($sStyle);
  }

  // add a TableCell to an existing row
  public function AddCell($oTableCell)
  {
    $this->oTableRow->AddCell($oTableCell);
  }

  public function GetString()
  {
    return $this->oTableRow->GetString();
  }

  public function GetTableRow()
  {
    return $this->oTableRow;
  }
}

class Table
{
  private $oTableRowHeader;
  private $aTableRows;
  private $sClass;
  private $sWidth;
  private $iBorder;
  private $sAlign; // align="$sAlign" - deprecated in html standards
  private $iCellSpacing; // cellspacing="$iCellSpacing"
  private $iCellPadding; // cellpadding="$iCellPadding"

  public function __construct()
  {
    $this->oTableRowHeader = null;
    $this->aTableRows = array();
    $this->sClass = null;
    $this->sWidth = null;
    $this->iBorder = null;
    $this->sAlign = null;
    $this->iCellSpacing = null;
    $this->iCellPadding = null;
  }

  public function AddRow($oTableRow)
  {
    $this->aTableRows[] = $oTableRow;
  }

  public function SetHeader(TableRow $oTableRowHeader)
  {
    $this->oTableRowHeader = $oTableRowHeader;
  }

  public function SetClass($sClass)
  {
    $this->sClass = $sClass;
  }

  public function SetWidth($sWidth)
  {
    $this->sWidth = $sWidth;
  }

  public function SetBorder($iBorder)
  {
    $this->iBorder = $iBorder;
  }

  public function SetAlign($sAlign)
  {
    $this->sAlign = $sAlign;
  }

  public function SetCellSpacing($iCellSpacing)
  {
    $this->iCellSpacing = $iCellSpacing;
  }

  public function SetCellPadding($iCellPadding)
  {
    $this->iCellPadding = $iCellPadding;
  }

  public function GetString()
  {
    $sStr = "<table";

    if($this->sClass)
      $sStr.= ' class="'.$this->sClass.'"';

    if($this->sWidth)
      $sStr.= ' width="'.$this->sWidth.'"';

    if($this->iBorder !== null)
      $sStr.= ' border="'.$this->iBorder.'"';

    if($this->sAlign)
      $sStr.= ' align="'.$this->sAlign.'"';

    if($this->iCellSpacing !== null)
      $sStr.= ' cellspacing="'.$this->iCellSpacing.'"';

    if($this->iCellPadding !== null)
      $sStr.= ' cellpadding="'.$this->iCellPadding.'"';

    $sStr.= ">"; // close the open table element

    if($this->oTableRowHeader)
    {
      $sStr.="<thead>";
      $sStr.= $this->oTableRowHeader->GetString();
      $sStr.="</thead>";
    }

    foreach($this->aTableRows as $oTableRow)
    {
      $sStr.= $oTableRow->GetString();
    }

    $sStr.= "</table>";

    return $sStr;
  }
}

// done
