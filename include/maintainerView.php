<?php

/**
 * Class to show an overview of a user's maintainership, including apps maintained
 * and their ratings
 */
class maintainerView
{
    var $iUserId;
    var $bViewingSelf;

    function __construct($iUserId = null, $oRow = null)
    {
        if(!$oRow)
        {
            if(!$iUserId)
                $this->iUserId = $_SESSION['current']->iUserId;
            else
                $this->iUserId = $iUserId;
        } else
        {
            $this->iUserId = $oRow->userId;
        }

        if(!$iUserId || $this->iUserId == $_SESSION['current']->iUserId)
            $this->bViewingSelf = true;
        else
            $this->bViewingSelf = false;
    }

    function objectGetId()
    {
        return $this->iUserId;
    }

    /* We don't queue this class or process it in any way */
    function objectGetState()
    {
        return 'accepted';
    }

    public function objectGetHeader()
    {
        $oTableRow = new TableRow();
        $oTableRow->AddTextCell('Maintainer');
        $oTableRow->AddTextCell('Login');
        $oTableRow->AddTextCell('Submission date');
        $oTableRow->AddTextCell('Last connected');
        $oTableRow->AddTextCell('Application');
        $oTableRow->AddTextCell('Version');
        $oTableRow->AddTextCell('Action');
        $oTableRow->SetClass('color3');
        $oTableRow->SetStyle('color: white;');

        return $oTableRow;
    }

    public function canEdit()
    {
        return $_SESSION['current']->hasPriv('admin');
    }

    function objectWantCustomDraw($sWhat, $sQueued)
    {
        switch($sWhat)
        {
            case 'table':
                if($sQueued == 'false')
                    return true;
                break;
        }

        return false;
    }

    public function objectGetTableRow()
    {
        $oRow = new TableRow();

        return $oRow;
    }

    public function objectDrawCustomTable($hResult, $sQueued, $oM)
    {
        echo '<div align="center"><a href="'.BASE.'contact.php?sRecipientGroup=maintainers">E-mail all maintainers</a></div>';

        $oTable = new Table();
        $oNewOM = new objectManager('maintainer');

        $oTable->SetCellPadding(3);
        $oTable->SetCellSpacing(0);
        $oTable->SetWidth('100%');

        $oHeader = $this->objectGetHeader();
        $oTable->AddRow($oHeader);

        $shReturnTo = BASE.'objectManager.php?sClass=maintainerView&sTitle=View+Maintainers'.$oM->makeUrlPart();
        $oNewOM->setReturnTo($shReturnTo);

        while($oRow = query_fetch_object($hResult))
        {
            $oTableRow = new TableRow();
            $oMaintainerView = new maintainerView(null, $oRow);
            $iUserId = $oMaintainerView->iUserId;
            $oUser = new user($oMaintainerView->iUserId);
            $sStyle = 'border-top: thin solid; border-bottom: thin solid;';

            $oCell = new TableCell('Maintainer summary');
            $oCell->SetStyle($sStyle.' border-left: thin solid;');
            $oTableRow->AddCell($oCell);

            $oCell = new TableCell('');
            $oCell->SetStyle($sStyle.' border-left: thin solid;');
            $oTableRow->AddCell($oCell);
                        
            $oCell = new TableCell('');
            $oCell->SetStyle($sStyle.' border-left: thin solid;');
            $oTableRow->AddCell($oCell);
                          
            $oCell = new TableCell('');
            $oCell->SetStyle($sStyle.' border-left: thin solid;');
            $oTableRow->AddCell($oCell);
            
            $iMaintainedApps = maintainer::GetMaintainerCountForUserId($iUserId, true);    
            $sPlural = ($iMaintainedApps == 1) ? '' : 's';
            $oCell = new TableCell($iMaintainedApps ? "$iMaintainedApps application$sPlural" : '&nbsp;');
            $oCell->SetStyle($sStyle);
            $oTableRow->AddCell($oCell);

            $iMaintainedVersions = maintainer::GetMaintainerCountForUserId($iUserId, false);    
            $sPlural = ($iMaintainedVersions == 1) ? '' : 's';
            $oCell = new TableCell($iMaintainedVersions ? "$iMaintainedVersions version$sPlural" : '&nbsp;');
            $oCell->SetStyle($sStyle);
            $oTableRow->AddCell($oCell);

            $oCell = new TableCell('&nbsp;');
            $oCell->SetStyle($sStyle.' border-right: thin solid;'       );
            $oTableRow->AddCell($oCell);

            $oTableRow->SetClass('color4');
            $oTable->AddRow($oTableRow);
            
            /* Show all apps/versions that the user maintains */
            $hAppResult = query_parameters("SELECT * FROM appMaintainers WHERE userId = '?'", $oMaintainerView->iUserId);
            for($i = 0; $oAppRow = query_fetch_object($hAppResult); $i++)
            {
                $oMaintainer = new maintainer(null, $oAppRow);
                $oTableRow = new TableRow();

                $oTableRow->SetClass($i % 2 ? 'color0' : 'color1');     

                if($oMaintainer->bSuperMaintainer)
                {
                    $oApp = new application($oMaintainer->iAppId);
                    $sVersionText = '*';
                } else
                {
                    $oVersion = new version($oMaintainer->iVersionId);
                    $oApp = new application($oVersion->iAppId);
                    $sVersionText = $oVersion->sName;
                }

                $oTableRow->AddTextCell($oUser->objectMakeLink());
                $oTableRow->AddTextCell($oUser->sEmail);
                $oTableRow->AddTextCell(print_date(mysqldatetime_to_unixtimestamp($oMaintainer->aSubmitTime)));
                $oTableRow->AddTextCell(print_date(mysqldatetime_to_unixtimestamp($oUser->sStamp)));
                $oTableRow->AddTextCell($oApp->objectMakeLink());
                $oTableRow->AddTextCelL($sVersionText);

                $oTableRow->AddTextCell('<a href="'.$oNewOM->makeUrl('delete', $oMaintainer->objectGetId()).'
                    "class="btn btn-default btn-sm"><i class="fa fa-trash"></i> delete</a>');

                $oTable->AddRow($oTableRow);
            }
        }

        echo $oTable->GetString();    
     }

    function objectGetItemsPerPage($sState = 'accepted')
    {
        $aItemsPerPage = array(25, 50, 100, 200);
        $iDefaultPerPage = 25;
        return array($aItemsPerPage, $iDefaultPerPage);
    }

    public function objectGetEntries($sState, $iRows = null, $iStart = 0, $sOrderBy = '', $bAscending = true, $oFilters = null)
    {
        if(!$_SESSION['current']->hasPriv('admin'))
            return false;

        $sLimit = objectManager::getSqlLimitClause($iRows, $iStart, 'maintainerView');

       // get available maintainers
        $sQuery = "SELECT * FROM appMaintainers, user_list WHERE appMaintainers.userId = user_list.userid";
        $sQuery.= " AND state='accepted' GROUP BY appMaintainers.userid ORDER BY realname, email $sLimit"; 
        $hResult = query_parameters($sQuery);

        return $hResult;
    }

    public function objectGetEntriesCount($sState)
    {
        if(!$_SESSION['current']->hasPriv('admin'))
            return false;

        $sQuery = "SELECT COUNT(DISTINCT userId) as count FROM appMaintainers WHERE state = '?'";
        $hResult = query_parameters($sQuery, $sState);

        if(!$hResult)
            return $hResult;

        $oRow = query_fetch_object($hResult);

        return $oRow->count;
    }

    function addVersionRatingInfo($oTableRow, $oVersion)
    {
        $oTableRow->AddTextCell($oVersion->objectMakeLink());

        /* Rating info */
        $oTableCell = new TableCell($oVersion->sTestedRating);
        $oTableCell->SetClass($oVersion->sTestedRating);
        $oTableRow->AddCell($oTableCell);
        $oTableCell = new TableCell($oVersion->sTestedRelease);
        $oTableCell->SetClass($oVersion->sTestedRating);
        $oTableRow->AddCell($oTableCell);

        /* Get test reports submitted by the user */
        $aTestData = testData::getTestResultsForUser($this->iUserId,
                                                     $oVersion->iVersionId);

        if(sizeof($aTestData))
        {
            $oTest = $aTestData[0];
            $sUserRating = $oTest->sTestedRating;
            $sUserRelease = $oTest->sTestedRelease;
        } else
        {
            $sUserRating = '';
            $sUserRelease = '';
        }

        $oTableCell = new TableCell($sUserRating);
        $oTableCell->SetClass($sUserRating);
        $oTableRow->AddCell($oTableCell);
        $oTableCell = new TableCell($sUserRelease);
        $oTableCell->SetClass($sUserRating);
        $oTableRow->AddCell($oTableCell);

        return $oTableRow;
    }

    /* Shows a detailed vis of the user's maintained applications,
       including last tested release & rating */
    function display()
    {
        $oUser = new user($this->iUserId);

        $aMaintainedApps = maintainer::getAppsMaintained($oUser);
        if(!$aMaintainedApps || !sizeof($aMaintainedApps))
        {
            if($this->bViewingSelf)
                echo '<p>You do not maintain any apps</p>';
            else
                echo "<p>{$oUser->sRealname} does not maintain any apps</p>";
            return;
        }

        if($this->bViewingSelf)
            echo '<p>Viewing your maintained apps</p>';
        else
            echo "<p>Viewing {$oUser->sRealname}'s maintained apps</p>";

        $oTable = new Table();
        $oTableRow = new TableRow();
        $oTableRow->setClass('color4');
        $oTable->setCellSpacing(0);
        $oTable->setCellPadding(3);
        $oTableRow->AddTextCell('Application');
        $oTableRow->AddTextCell('Version');
        $oTableRow->AddTextCell('Current Rating');
        $oTableRow->AddTextCell('Current Version');
        $oTableRow->AddTextCell($this->bViewingSelf ? 'Your Rating' : "User's Rating");
        $oTableRow->AddTextCell($this->bViewingSelf ? 'Your Version' : "User's Version");
        $oTable->AddRow($oTableRow);

        $i = 1;
        while(list($iIndex, list($iAppId, $iVersionId, $bSuperMaintainer)) = each($aMaintainedApps))
        {
            $oApp = new application($iAppId);
            $aVersions = array();

            $oTableRow = new TableRow();
            $oTableRow->AddTextCell($oApp->objectMakeLink());

            $oTableRow->SetClass(($i % 2) ? 'color0' : 'color1');
            $i++;

            if($iVersionId)
            {
                $oVersion = new version($iVersionId);
                $oTableRow = maintainerView::addVersionRatingInfo($oTableRow, $oVersion);
                $oTable->AddRow($oTableRow);
            } else
            {
                $aVersions = $oApp->getVersions(true);
                $oTableRow->AddTextCell('*');
                $oTableRow->AddTextCell('');
                $oTableRow->AddTextCell('');
                $oTableRow->AddTextCell('');
                $oTableRow->AddTextCell('');
                $oTable->AddRow($oTableRow);
            }

            foreach($aVersions as $oVersion)
            {
                $oTableRow = new TableRow($oTableRow);
                $oTableRow->AddTextCell('');
                $oTableRow = maintainerView::addVersionRatingInfo($oTableRow, $oVersion);

                $oTableRow->SetClass(($i % 2) ? 'color0' : 'color1');
                $i++;

                $oTable->AddRow($oTableRow);
            }
        }
        echo $oTable->GetString();
    }
}


