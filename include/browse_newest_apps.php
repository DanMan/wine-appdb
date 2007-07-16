<?php

// class lists the newest applications and versions in the database

class browse_newest_apps
{
  var $iAppId;

  // constructor doesn't need to perform any queries. we provide a constructor for
  // browse_newest_apps because the objectManager requires an instance for some methods
  function browse_newest_apps($iAppId = null, $oRow = null)
  {
    if(!$iAppId && !$oRow)
      return;

    if(!$oRow)
    {
      $this->iAppId = $iAppId;
    }

    if($oRow)
    {
      $this->iAppId = $oRow->appId;
    }
  }

  function objectGetEntries($bQueued, $bRejected, $iRows = 0, $iStart = 0)
  {
    // We don't implement queues or rejected applications
    if($bQueued || $bRejected)
      return false;


    // if row limit is 0 we want to fetch all rows
    if(!$iRows)
    {
        $iRows = browse_newest_apps::objectGetEntriesCount($bQueued, $bRejected);
    }

    $sQuery = "SELECT appId, appName, description, submitTime FROM appFamily WHERE".
      " queued = '?' ORDER BY appId DESC LIMIT ?,?";

    return query_parameters($sQuery, $bQueued ? "true" : "false",
                            $iStart, $iRows);
  }

  function objectGetEntriesCount($bQueued, $bRejected)
  {
    // We don't implement queues or rejected applications
    if($bQueued || $bRejected)
    {
      return 0;
    }

    return application::objectGetEntriesCount($bQueued, $bRejected);
  }

  function objectGetHeader()
  {
    $aCells = array(
                    array("Submission Date", "color=\"white\""),
                    array("Application", "color=\"white\""),
                    array("Description", "color=\"white\""));

    return $aCells;
  }

  function objectGetTableRow()
  {
    $oApp = new application($this->iAppId);
    $aCells = array(
                    array(print_short_date(mysqltimestamp_to_unixtimestamp($oApp->sSubmitTime)),
                          "width=\"20%\""),
                    $oApp->objectMakeLink(),
                    util_trim_description($oApp->sDescription));


    $oTableRow = new TableRow($aCells);
    return $oTableRow;
  }

  function objectGetItemsPerPage($bQueued = false)
  {
    $aItemsPerPage = array(25, 50, 100, 200);
    $iDefaultPerPage = 25;
    return array($aItemsPerPage, $iDefaultPerPage);
  }

  function objectGetId()
  {
    return $this->iAppId;
  }

  // stub implementation
  function allowAnonymousSubmissions()
  {
    return false;
  }

  // stub canEdit() out, no one can edit these entries
  function canEdit()
  {
    return false;
  }

  // stub implementation
  function display()
  {
  }

  // stub implementation
  function outputEditor()
  {
  }

  // stub implementation
  function getOutputEditorValues($aValues)
  {
  }

  // stub implementation
  function objectMakeLink()
  {
    $oApp = new Application($this->iAppId);
    return $oApp->objectMakeLink();
  }

  // stub implementation
  function objectMakeUrl()
  {
  }

  // stub implementation
  function mustBeQueued()
  {
    return false;
  }
}

?>
