<?php

/* unit tests for input filtering routines */

require_once("path.php");
require_once("test_common.php");
require_once(BASE."include/incl.php");


// Test that we can filter properly, that filtering errors result in error output
// and that we properly preserve html tags in html strings and strip html tags from
// normal strings
function test_filter()
{
    global $aClean; // the array where filtered variables will be stored

    //*********************************************************
    // test that filtering properly fails when given an integer
    // that doesn't contain an integer value
    $_REQUEST = array(); // clear out the array
    $_REQUEST['iInteger'] = 100;
    $_REQUEST['iNotAnInteger'] = "asfasdflskjf"; // this value should cause filtering to
                                                 // fail since it isn't an integer bug has
                                                 // the integer prefix of 'i'

    $sResult = filter_gpc();
    if(!$sResult)
    {
        error("filter_gpc() succeeded when it should have failed due to invalid input!");
        return false;
    }


    //***************************************************************
    // test that filtering succeeds when given valid values to filter
    $sString = "some string";
    $iInteger = 12345;
    $_REQUEST = array(); // clear out the array
    $_REQUEST['sString'] = $sString;
    $_REQUEST['iInteger'] = $iInteger;

    // filter the variables and make sure that we don't have a return value
    // ie, that filtering succeeded
    $sResult = filter_gpc();
    if($sResult)
    {
        error("sResult is '$sResult' but we expected success and no return value");
        return false;
    }

    // make sure the values match what we expect
    if($aClean['sString'] != $sString)
    {
        error("Expected aClean['sString'] to be '".$sString."' but instead it was '".$aClean['sString']."'");
        return false;
    }

    if($aClean['iInteger'] != $iInteger)
    {
        error("Expected aClean['iInteger'] to be '".$iInteger."' but instead it was '".$aClean['iInteger']."'");
        return false;
    }


    //*************************************************************
    // test that filtering html works properly, preserving the tags
    $_REQUEST = array(); // clear out the array
    $shHtml = "<pre>This is some html</pre>";
    $_REQUEST['shHtml'] = $shHtml;

    // filter the variables and make sure that we don't have a return value
    // ie, that filtering succeeded
    $sResult = filter_gpc();
    if($sResult)
    {
        error("sResult is '$sResult' but we expected success and no return value");
        return false;
    }

    // expect that the filtered value will be equal
    if($aClean['shHtml'] != $shHtml)
    {
        error("Expected aClean['shHtml'] to be '".$shHtml."' but instead it was '".$aClean['shHtml']."'");
        return false;
    }


    //*****************************************************************************
    // test that filtering strings with html results in the tags being stripped out 
    $_REQUEST = array(); // clear out the array
    $sHtml = "<pre>This is some html</pre>";
    $_REQUEST['sHtml'] = $sHtml;

    // filter the variables and make sure that we don't have a return value
    // ie, that filtering succeeded
    $sResult = filter_gpc();
    if($sResult)
    {
        error("sResult is '$sResult' but we expected success and no return value");
        return false;
    }

    // expect that $aClean value has been modified during filtering so these
    // shouldn't be equal unless something has failed
    if($aClean['sHtml'] == $sHtml)
    {
        error("Expected aClean['shHtml'] to be '".$sHtml."' but instead it was '".$aClean['sHtml']."'");
        return false;
    }

    // make sure all html has been stripped
    if(strip_tags($aClean['sHtml']) != $aClean['sHtml'])
    {
        error("Expected all html to be stripped already but we were able to strip this '".$aClean['sHtml']
              ."' into '".strip_tags($aClean['sHtml'])."'");
        return false;
    }

    return true;
}


/*************************/
/* Main test routines */

if(!test_filter())
{
    echo "test_filter() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_filter() passed\n";
}



