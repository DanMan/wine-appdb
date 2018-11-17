<?php
$aClean = array();
filter_perform_filtering();

/* perform input variable filtering */
function filter_perform_filtering()
{
    // if filtering failed post the error
    $sResult = filter_gpc();
    if($sResult)
    {
        util_show_error_page_and_exit($sResult);
    }
}

/*
 * Make all get/post/cookies variable clean based on their names.
 * Returns an error string if failure occurs, null if successful
 */
function filter_gpc()
{
    global $aClean;
    $sErrorSuggestion = " Please try clearing out your cookies. If the problem continues ". 
                        " please email ".APPDB_OWNER_EMAIL." with the error text.";

    $aKeys = array_keys($_REQUEST);
    for($i=0; $i < sizeof($aKeys); $i++)
    {
        // NOTE: useful for debugging
        //echo "'".$aKeys[$i]."' = '".$_REQUEST[$aKeys[$i]]."'\n";

        // Special cases for variables that don't fit our filtering scheme
        // don't filter the AppDB session cookie and MAX_FILE_SIZE

        // NOTE: we must use === when comparing the return value of strpos
        //       against a value, otherwise if strpos() returns false indicating that
        //       the value wasn't found strpos(something) == 0 will still be true
        if(($aKeys[$i] == "cx")  ||  // google custom search variable
           ($aKeys[$i] == "cof") ||  // google custom search variable
           ($aKeys[$i] == "q")   ||  // google custom search variable
           ($aKeys[$i] == "g-recaptcha-response")) // google reCaptcha variable
        {
            // copy the key over to the clean array
            // NOTE: we do not strip html tags or trim any Xinha variables
            //       because Xinha is a html editor and removing html tags
            //       would break the ability to use Xinha to create or edit html
            $aClean[$aKeys[$i]] = $_REQUEST[$aKeys[$i]];
            continue; // go to the next entry
        } else if($aKeys[$i] == "whq_appdb" || ($aKeys[$i] == "MAX_FILE_SIZE")
                  || ($aKeys[$i] == "PHPSESSID")
                  || (strpos($aKeys[$i], "pref_") === 0)) // other variables
        {
            // copy the key over to the clean array after stripping tags and trimming
            $aClean[$aKeys[$i]] = trim(strip_tags($_REQUEST[$aKeys[$i]]));
            continue; // go to the next entry
        }

        switch($aKeys[$i][0])
        {
            case "i": // integer
            case "f": // float
                if(is_numeric($_REQUEST[$aKeys[$i]]))
                    $aClean[$aKeys[$i]] = $_REQUEST[$aKeys[$i]];
                else if(empty($_REQUEST[$aKeys[$i]]))
                    $aClean[$aKeys[$i]] = 0;
                else if(is_numeric(trim($_REQUEST[$aKeys[$i]]))) // try clearing whitespace
                    $aClean[$aKeys[$i]] = trim($_REQUEST[$aKeys[$i]]);
                else
                    return "Fatal error: ".$aKeys[$i]." should be a numeric value. ".
                           $sErrorSuggestion;
            break;
            case "b": // boolean
                if($_REQUEST[$aKeys[$i]]=="true" || $_REQUEST[$aKeys[$i]]=="false")
                    $aClean[$aKeys[$i]] = $_REQUEST[$aKeys[$i]];
                else
                    return "Fatal error: ".$aKeys[$i]." should be a boolean value. ".
                           $sErrorSuggestion;
            break;
            case "s": // string
                switch($aKeys[$i][1])
                {
                     case "h": // HTML string
                         $aClean[$aKeys[$i]] = trim($_REQUEST[$aKeys[$i]]);
                         // if there is no content and no image, make the variable empty
                         if(strip_tags($aClean[$aKeys[$i]],'<img>')=="")
                             $aClean[$aKeys[$i]] = "";
                     break;
                     default: // normal string (no HTML)
                          $aClean[$aKeys[$i]] = trim(strip_tags($_REQUEST[$aKeys[$i]]));
                     break;
                }
            break;
            case "a": // array
                // store the value if it is an array
                if(is_array($_REQUEST[$aKeys[$i]]))
                    $aClean[$aKeys[$i]] = $_REQUEST[$aKeys[$i]];
            break;
            default:
                // type not recognized, skip it
                break;
        }
    }

    /* null out all input data so we can be assured that */
    /* no unfiltered values are being used */
    $_REQUEST = array();
    $_POST = array();
    $_GET = array();
    if(APPDB_DONT_CLEAR_COOKIES_VAR != "1")
        $_COOKIES = array();

    return null;
}

