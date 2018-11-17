<?php

/* Main test function.  To add new tests include_once() */
/* your test file here */

/* TODO: test the rest of the classes we have */

require_once("path.php");
require_once(BASE.'include/incl.php');

error_reporting(E_ALL);

// disable emailing
if(!defined("DISABLE_EMAIL"))
   define("DISABLE_EMAIL", true);

// default to the tests being successful
$bTestSuccess = true;

// purge any session messages currently in the database
purgeSessionMessages();

// retrieve counts of each of the tables so we can check again
// at the end of the
$oStartingTableCounts = new table_counts();

include_once("test_user.php");
echo "\n";

include_once("test_query.php");
echo "\n";
include_once("test_image.php");
echo "\n";
include_once("test_application.php");
echo "\n";
include_once("test_error_log.php");
echo "\n";
include_once("test_filter.php");
echo "\n";
include_once("test_url.php");
echo "\n";
include_once("test_om_objects.php");
echo "\n";
include_once("test_appData.php");
echo "\n";
include_once("test_testData.php");
echo "\n";
include_once("test_voting.php");

// purge any session messages we generated during the test
purgeSessionMessages();

// retrieve counts of each of the tables after our tests
$oEndingTableCounts = new table_counts();

// see if our table counts match
if(!$oEndingTableCounts->IsEqual($oStartingTableCounts))
{
  $bTestSuccess = false;
  echo "\n\nStarting and ending table counts do not match\n";

  $oStartingTableCounts->OutputSideBySide($oEndingTableCounts,
                                          "Starting",
                                          "Ending");
} else
{
  echo "\n\nTable counts match from the start and end of the test suite.\n";
}

if($bTestSuccess == true)
{
  echo "\nAll tests were successful\n";
} else
{
  echo "\nSome test(s) failed!\n";
}



// keep track of the counts of various tables
class table_counts
{
    // the tables we should count, set in the constructor
    var $aTablesToCount;

    // the counts of the tables
    var $aTableCounts;

    function __construct()
    {
        $this->aTablesToCount = array('appBundle',
                                      'appCategory',
                                      'appComments',
                                      'appData',
                                      'appFamily',
                                      'appMaintainers',
                                      'appMonitors',
                                      'appNotes',
                                      'appVersion',
                                      'appVotes',
                                      'buglinks',
                                      'distributions',
                                      'prefs_list',
                                      'testResults',
                                      'user_list',
                                      'user_privs',
                                      'vendor');

        $this->update_counts();
    }

    // update the count for each table
    function update_counts()
    {
        $this->aTableCounts = array();

        foreach($this->aTablesToCount as $sTable)
        {
            $sQuery = "select count(*) as cnt from ?;";
            $hResult = query_parameters($sQuery, $sTable);
            $oRow = query_fetch_object($hResult);

            $this->aTableCounts[] = $oRow->cnt;
        }
    }

    // returns 'true' if equal, 'false' if not
    function IsEqual($oTableCounts)
    {
        $iIndex = 0;
        for($iIndex = 0; $iIndex < count($this->aTableCounts); $iIndex++)
        {
            if($this->aTableCounts[$iIndex] != $oTableCounts->aTableCounts[$iIndex])
            {
                return false;
            }
        }

        return true;
    }

    function OutputSideBySide($oTableCounts, $sFirstTableCountName,
                              $sSecondTableCountName)
    {
        $iIndex = 0;

        // output the header
        printf("%20s%20s%20s\n",
               "Table name",
               $sFirstTableCountName,
               $sSecondTableCountName);

        for($iIndex = 0; $iIndex < count($this->aTableCounts); $iIndex++)
        {
            printf("%20s%20s%20s",
                   $this->aTablesToCount[$iIndex],
                   $this->aTableCounts[$iIndex],
                   $oTableCounts->aTableCounts[$iIndex]);
            if($this->aTableCounts[$iIndex] != $oTableCounts->aTableCounts[$iIndex])
            {
                echo "  <-- Mismatch";
            }
            echo "\n";
        }
    }

    function output()
    {
        $iIndex = 0;
        for($iIndex = 0; $iIndex < count($this->aTableCounts); $iIndex++)
        {
            $sTableName = $this->aTablesToCount[$iIndex];
            $iTableCount = $this->aTableCounts[$iIndex];
            echo "$sTableName count of $iTableCount\n";
        }
    }
}


?>
