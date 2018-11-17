<?php

require_once("path.php");
require_once(BASE."include/incl.php");

function test_error_log_log_error()
{
    error_log::flush(); /* flush the error log */

    error_log::log_error(ERROR_SQL, "This is a sql error");
    error_log::log_error(ERROR_GENERAL, "This is a general error");

    /* make sure the error log count matches what we expect */
    $iExpected = 2;
    $iActual = error_log::getEntryCount();
    if($iActual != $iExpected)
    {
        echo "Expected error_log::getEntryCount() of ".$iExpected." got ".$iActual;
        return false;
    }

    error_log::flush(); /* flush the error log */

    /* make sure the error log count matches what we expect */
    $iExpected = 0;
    $iActual = error_log::getEntryCount();
    if($iActual != $iExpected)
    {
        echo "Expected error_log::getEntryCount() of ".$iExpected." got ".$iActual;
        return false;
    }

    return true;
}


if(!test_error_log_log_error())
{
    echo "test_error_log_log_error() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_error_log_log_error() passed\n";
}


