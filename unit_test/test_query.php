<?php

/* unit tests for functions in include/query.php */

require_once("path.php");
require_once("test_common.php");

/* test query_parameters() */
/* query_parameters() is key to protecting sql queries from injection attacks */
/* while also being simple and easy to use */
function test_query_parameters()
{
    test_start(__FUNCTION__);

    /* see that queries without parameters work properly */
    $sQuery = "SELECT count(*) as count from user_list";
    $hResult = query_parameters($sQuery);
    if(!$hResult)
    {
        echo "sQuery of '".$sQuery."' failed but should have succeeded\n";
        return false;
    }

    /* see that '?' marks in queries are replaced with parameters */
    $sQuery = "SELECT count(*) as count from ?";
    $hResult = query_parameters($sQuery, "user_list");
    if(!$hResult)
    {
        echo "sQuery of '".$sQuery."' failed but should have succeeded\n";
        return false;
    }

    $oRow = query_fetch_object($hResult);
    $iUserCount = $oRow->count;

    /* see that '~' strings are replaced with parameters */
    /* NOTE: if the second parameter is quoted this query will produce */
    /*  a different number of results than if it was */
    /* NOTE: The second parameter is an example of a SQL injection attack */
    $sQuery = "SELECT count(*) as count from ~ where userid='~'";
    $hResult = query_parameters($sQuery, "user_list", "1' OR 1='1");
    if($hResult)
    {
        $oRow = query_fetch_object($hResult);
        if($iUserCount != $oRow->count)
        {
            echo "sQuery of '".$sQuery."' returned ".$oRow->count." entries instead of the expected ".$iUserCount."\n";
            return false;
        }

        if($iUserCount == 0)
        {
            echo "Because iUserCount is zero we can't know if this test passed or failed\n";
        }
    } else
    {
        echo "sQuery of '".$sQuery."' failed but should have succeeded\n";
        return false;
    }

    /**
     * test that '&' in a query is properly replaced by the contents from a file
     */
    /* write to a file that we will use for the test of '&' */
    $sFilename = "/tmp/test_query.txt";
    $hFile = fopen($sFilename, "wb");
    if($hFile)
    {
        fwrite($hFile, "user_list");
        fclose($hFile);

        $sQuery = "SELECT count(*) as count from &";
        $hResult = query_parameters($sQuery, $sFilename);
        unlink($sFilename); /* delete the temporary file we've created */
        if(!$hResult)
        {
            echo "sQuery of '".$sQuery."' failed but should have succeeded\n";
            return false;
        }
    } else
    {
        echo "Could not open '".$sFilename."' for writing to complete the '&' test\n";
    }
  
    /**
     * test that queries with slashes in them are parameterized properly
     * NOTE: this test exists because query_parameters() at one point did not work
     *       properly with slashes in the query, they were incorrectly being recognized
     *       as tokens that should be replaced with parameters
     */
    $sQuery = "SELECT count(*) as count, '".query_escape_string("\r\n")."' as x from ?";
    $hResult = query_parameters($sQuery, "user_list");
    if(!$hResult)
    {
        echo "sQuery of '".$sQuery."' failed but should have succeeded\n";
        return false;
    }

    /* test that queries with too many parameters are rejected */
    $sQuery = "SELECT count(*) as count from ?";
    $hResult = query_parameters($sQuery, "user_list", "12");
    if($hResult)
    {
        echo "sQuery of '".$sQuery."' succeeded but should have failed for too many parameters\n";
        return false;
    }

    /* test that queries with too few parameters are rejected */
    $sQuery = "SELECT count(*) as count from ?";
    $hResult = query_parameters($sQuery);
    if($hResult)
    {
        echo "sQuery of '".$sQuery."' succeeded but should have failed for too few parameters\n";
        return false;
    }

    return true;
}


if(!test_query_parameters())
{
    echo "test_query_parameters() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_query_parameters() passed\n";
}



