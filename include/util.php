<?php
/**
 * misc global utility functions
 */

/**
 * display an error page
 */
function util_show_error_page_and_exit($text = null, $message = null)
{
    if (!$text) {
        $text = "You must be <a href=\"".login_url()."\">logged in</a> to perform that operation.";
    }
    header("HTTP/1.0 404 Object not found or user is not logged in");
    apidb_header("Oops");
    echo "<h1 class=\"text-danger\"><i class=\"fa fa-exclamation-triangle\"></i> $text</h1>\n";
    if (!empty($message))
    {
        echo "<hr>\n";
        echo "<p>$message</p>\n";
    }
    apidb_footer();
    exit;
}

/**
 * redirect to $url
 */
function util_redirect_and_exit($url)
{
    header("Location: ".html_entity_decode($url)); 
    exit;
}

function build_urlarg($vars)
{
    $arr = array();
    while(list($key, $val) = each($vars))
    {
        if(is_array($val))
        {
            while(list($idx, $value) = each($val))
            {
                //echo "Encoding $key / $value<br>";
                $arr[] = rawurlencode($key."[]")."=".rawurlencode($value);
            }
        } else
        {
            $arr[] = $key."=".rawurlencode($val);
        }
    }
    return implode("&", $arr);
}

/*
 * return all values of a mapping as an array
 */
function values($arr)
{
    $res = array();
    while(list($k, $v) = each($arr))
        $res[] = $v;
    return $res;
}

/* Display a login form */
function login_form($bShowHeader = true)
{
    global $aClean;
    $aClean['sReturnTo'] = urlencode($_SERVER['REQUEST_URI']);

    if($bShowHeader)
        apidb_header("Login");
    include(BASE."include/"."form_login.php");

    if($bShowHeader)
        apidb_footer();
}

// print the month, day, year, hour, minute, second
function print_date($sTimestamp)
{
    return date("F d Y  H:i:s", $sTimestamp);
}

// print the month, day and year
function print_short_date($sTimestamp)
{
    return date("F d Y", $sTimestamp);
}

function mysqltimestamp_to_unixtimestamp($sTimestamp)
{
    $sResult = query_get_server_info();
    $fVersion = substr($sResult, 0, 3);

    /* This differs between MySQL versions, newer ones are in the form
       yyyy-mm-dd hh:mm:ss */
    if($fVersion >= 4.1)
    {
        $iDay = substr($sTimestamp, 8, 2);
        $iMonth = substr($sTimestamp, 5, 2);
        $iYear = substr($sTimestamp, 0, 4);
        $iHours = substr($sTimestamp, 11, 2);
        $iMinutes = substr($sTimestamp, 14, 2);
        $iSeconds = substr($sTimestamp, 17, 2);
    } else
    /* The old ones are in the form yyyymmddhhmmss */
    {
        $iDay = substr($sTimestamp,6,2);
        $iMonth = substr($sTimestamp,4,2);
        $iYear = substr($sTimestamp,0,4);
        $iHours = substr($sTimestamp,8,2);
        $iMinutes = substr($sTimestamp,10,2);
        $iSeconds = substr($sTimestamp,12,2);
    }
    return mktime($iHours, $iMinutes, $iSeconds, $iMonth, $iDay, $iYear);
}

function mysqldatetime_to_unixtimestamp($sDatetime)
{
    return strtotime($sDatetime);
}

function htmlify_urls($text)
{
    //FIXME: wonder what the syntax is, this doesn't seem to work
    //    $text = strip_tags($text, "<a>,<b>,<i>,<ul>,<li>");

    // html-ify urls
    $urlreg = "([a-zA-Z]+://([^\t\r\n ]+))";
    $text = preg_replace($urlreg, "<a href=\"$0\">$1</a>", $text);

    $emailreg = "([a-zA-Z0-9_%+.-]+@[^\t\r\n ]+)";
    $text = preg_replace($emailreg, " <a href='mailto:$0'>$0</a>", $text);

    $text = str_replace("\n", "<br>", $text);

    return $text;
}


/* bugzilla functions */

// Returns an array containg the Wine versions stored in our Bugzilla DB
// If bReturnIds is true, version ids are returned instead of names
function get_bugzilla_versions($bReturnIds = false)
{
    $sFetchColumn = $bReturnIds ? 'id' : 'value';
    $aVersions = array();
    $sTable = BUGZILLA_DB.".versions";

    // The empty string will fetch the most recent versions
    $aBranches = array('');

    // Get a list of stable branches
    if(STABLE_BRANCHES)
        $aBranches = array_merge($aBranches, explode(',', STABLE_BRANCHES));

    foreach($aBranches as $sBranch)
    {
        $sWhere = "WHERE product_id =".BUGZILLA_PRODUCT_ID." AND value LIKE '$sBranch%'";
        $sQuery = "SELECT $sFetchColumn FROM $sTable $sWhere ORDER BY id desc limit 6";
        $hResult = query_bugzilladb($sQuery);
        if($hResult)
        {
            while(list($sValue) = query_fetch_row($hResult))
            {
                if(!in_array($sValue, $aVersions))
                    $aVersions[] = $sValue;
            }
        }
    }

    return $aVersions;
}

// Returns an array containing the IDs of the Wine versions stored in Bugzilla
function get_bugzilla_version_ids()
{
    return get_bugzilla_versions(true);
}

// $sVarname - name of the selection array that this function will output
//             this is the name to use to retrieve the selection on the form postback
// $sSelectedValue - the currently selected entry
// returns a string that contains the version list output
function make_bugzilla_version_list($sVarname, $sSelectedValue = '')
{
    $sStr = "";

    // build the list of versions
    $aVersions = get_bugzilla_versions();

    // build the selection array
    $sStr.= "<select name='$sVarname' class='form-control form-control-inline'>\n";
    $sStr.= "<option value=\"\">Choose ...</option>\n";
    $bFoundSelectedValue = false;
    foreach($aVersions as $sKey => $sValue)
    {
      if($sValue == $sSelectedValue)
      {
        $sStr.= "<option value=$sValue selected>$sValue\n";
        $bFoundSelectedValue = true;
      } else
      {
        $sStr.= "<option value=$sValue>$sValue\n";
      }
    }

    // if we didn't find the selected value and the selected value isn't empty
    // then we should add the selected value to the list because we could have pruned
    // the version that is to be selected
    if(!$bFoundSelectedValue && $sSelectedValue)
    {
      $sStr.= "<option value=$sSelectedValue selected>$sSelectedValue\n";
    }

    $sStr.= "</select>\n";

    return $sStr;
}

// returns a string containing the html for the maintainer rating list
function make_maintainer_rating_list($varname, $cvalue)
{
    $sTxt = "<select id='ratingSelect' name='$varname' class='form-control form-control-inline'>\n";
    $sTxt .= "<option value=\"\">Choose ...</option>\n";
    $aRating = array("Platinum", "Gold", "Silver", "Bronze", "Garbage");
    $iMax = count($aRating);

    for($i=0; $i < $iMax; $i++)
    {
        if($aRating[$i] == $cvalue)
            $sTxt .= "<option class=$aRating[$i] value=$aRating[$i] selected>$aRating[$i]\n";
        else
            $sTxt .= "<option class=$aRating[$i] value=$aRating[$i]>$aRating[$i]\n";
    }
    $sTxt .= "</select>&nbsp;<span id='hint'></span>&nbsp;\n";

    return $sTxt;
}

// Function to calculate script execution time.
function microtime_float ()
{
    list ($msec, $sec) = explode(' ', microtime());
    $microtime = (float)$msec + (float)$sec;
    return $microtime;
}

/* Get the element with specified key from an array if it is defined */
function getInput($sVar, $aInput)
{
    if(array_key_exists($sVar, $aInput))
        return $aInput[$sVar];

    return null;
}

/* Get the number of users in the database */
function getNumberOfComments()
{
    $hResult = query_parameters("SELECT count(*) as num_comments FROM appComments;");
    $oRow = query_fetch_object($hResult);
    return $oRow->num_comments;
}

/* return true if this word is in the list of words to ignore */
function isIgnoredWord($sWord)
{
    $ignore_words = array('I', 'a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com',
                          'de', 'en', 'for', 'from', 'how', 'in', 'is', 'it', 'la', 'of',
                          'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when',
                          'where', 'who', 'will', 'with', 'und', 'the', 'www', 'game');

    $found = false;

    /* search each item in the $ignore_words array */
    foreach($ignore_words as $ik=>$iv)
    {
        /* if we find a match we should flag it as so */
        if(strtoupper($sWord) == strtoupper($iv))
        {
            $found = true;
            break; /* break out of this foreach loop */
        }
    }

    return $found;
}

/* remove common words from $search_words to improve our searching results */
function cleanupSearchWords($search_words)
{
    /* trim off leading and trailing spaces in $search_words */
    /* to improve matching accuracy */
    $search_words = trim($search_words);

    $filtered_search = "";

    /* search each word in $search_words */
    $split_words = explode(" ", $search_words);

    $removed_words = '';
    foreach($split_words as $key=>$value)
    {
        /* see if this word is in the ignore list */
        /* we remove any of the words in the ignore_words array.  these are far too common */
        /* and will result in way too many matches if we leave them in */
        /* We will also remove any single letter search words */
        $found = isIgnoredWord($value);

        /* remove all single letters */
        if((strlen($value) == 1) && !is_numeric($value))
            $found = true;
        
        /* if we didn't find this word, keep it */
        if($found == false)
        {
            if($filtered_search)
                $filtered_search.=" $value";
            else
                $filtered_search="$value";
        } else
        {
            if($removed_words == "")
                $removed_words.="'".$value."'";
            else
                $removed_words.=", '".$value."'";
        }
    }

    /* replace the existing search with the filtered_search */
    $search_words = $filtered_search;

    return $search_words;
}

/* A common error for users is to submit a new app entry for a new app version,
   such as C&C Red Alert 2 Yuri's Revenge when we already have C&C Red Alert 2.
   Search for the first word in the search query.
   iExcludeAppId can be useful when showing a list of duplicate entries */
function searchForApplicationPartial($sSearchWords, $iExcludeAppId = null)
{
    /* This would yield too many results and stress MySQL */
    if(strlen($sSearchWords) < 4)
        return null;

    $sSearchWords = cleanupSearchWords($sSearchWords);

    /* The search string may have gotten shorter; even empty */
    if(strlen($sSearchWords) < 4)
        return null;

    $aWords = explode(' ', $sSearchWords);
    $sSearchString = '';
    $sEnsureExactWord = ''; // Used to ensure we don't match partial words when prepending
                            // a wildcard to the search string

    for($i = 0; $i < sizeof($aWords); $i++)
    {
        if($i)
            $sSearchString .= '%';
        $sSearchString .= $aWords[$i];
        if(strlen($aWords[$i]) > 4)
        {
            if($i < (sizeof($aWords) - 1))
                $sEnsureExactWord = ' ';
            break;
        }
    }

    $sExcludeApps = '';
    if($iExcludeAppId && is_numeric($iExcludeAppId))
        $sExcludeApps = " AND appId != '$iExcludeAppId' ";

    $hResult = query_parameters("SELECT * FROM appFamily WHERE state = 'accepted' AND
                                 (appName LIKE '?%' OR appName LIKE '?')$sExcludeApps", $sSearchString.$sEnsureExactWord, $sSearchString);

    return $hResult;
}

/* search the database and return a hResult from the query_appdb() */
function searchForApplication($search_words, $iExcludeAppId = null)
{
    /* This would yield too many results and stress MySQL */
    if(strlen($search_words) < 4)
        return null;

    /* cleanup search words */
    $search_words = cleanupSearchWords($search_words);

    /* The search string may have gotten shorter; even empty */
    if(strlen($search_words) < 4)
        return null;

    /* remove any search words less than 4 letters */
    $split_words = array();
    $split_search_words = explode(" ", $search_words);
    foreach($split_search_words as $key=>$value)
    {
        if(strlen($value) >= 4)
            array_push($split_words, $value);
    }

    $search_words = str_replace(' ', '%', query_escape_string($search_words));

    $sExcludeApps = '';
    if($iExcludeAppId && is_numeric($iExcludeAppId))
        $sExcludeApps = " AND appId != '$iExcludeAppId' ";

    /* base query */
    $sQuery = "SELECT *
           FROM appFamily
           WHERE appName != 'NONAME'
           AND appFamily.state = 'accepted'
           AND (appName LIKE '%?%'
           OR keywords LIKE '%?%'";

    $sQuery.=" ) $sExcludeApps ORDER BY appName";

    $hResult = query_parameters($sQuery, $search_words, $search_words);
    return $hResult;
}

function outputSearchTableForhResult($search_words, $hResult)
{
    if(($hResult == null) || (query_num_rows($hResult) == 0))
    {
        // do something
        echo html_frame_start();
        echo "No matches found for <b>'".urlencode($search_words)."'</b>\n";
        echo html_frame_end();
    }
    else
    {
        echo html_frame_start();
        echo "<table class=\"whq-table whq-table-full\">\n\n";

        echo "<thead><tr>\n";
        echo "    <td>Application Name</td>\n";
        echo "    <td>Description</td>\n";
        echo "    <td>No. Versions</td>\n";
        echo "</tr></thead><tbody>\n";

        while($oRow = query_fetch_object($hResult))
        {
            $oApp = new application($oRow->appId);

            //skip if a NONAME
            if ($oRow->appName == "NONAME") { continue; }

            //count versions
            $hResult2 = query_parameters("SELECT count(*) as versions FROM appVersion WHERE appId = '?' AND versionName != 'NONAME' and state = 'accepted'",
                                     $oRow->appId);
            $y = query_fetch_object($hResult2);

            //display row
            echo "<tr>\n";
            echo "    <td>".html_ahref($oRow->appName,$oApp->objectMakeUrl())."</td>\n";
            echo "    <td>".util_trim_description($oRow->description)."</td>\n";
            echo "    <td>{$y->versions} &nbsp;</td>\n";
            echo "</tr>\n\n";
        }

        echo "</tbody></table>\n";
        echo "<p><b>$c</b> match(es) found</p>";
        echo html_frame_end();
    }
}

/* pass in $isVersion of true if we are processing changes for an app version */
/* or false if processing changes for an application family */
function process_app_version_changes($bIsVersion)
{
    global $aClean;

    /* load up the version or application depending on which values are set */
    if($bIsVersion)
        $oVersion = new Version($aClean['iVersionId']);
    else
        $oApp = new Application($aClean['iAppId']);

    // commit changes of form to database
    if(($aClean['sSubmit'] == "Update Database") && $bIsVersion) /* is a version */
    {
        $oVersion->GetOutputEditorValues($aClean);
        $oVersion->update();
    } else if(($aClean['sSubmit'] == "Update Database") && !$bIsVersion) /* is an application */
    {
        $oApp->GetOutputEditorValues($aClean);
        $oApp->update();
    }
}

function perform_search_and_output_results($search_words)
{
    echo "<b>Searching for '".$search_words."' ";

    echo "<b>Like matches</b> ";
    $hResult = searchForApplication($search_words);
    outputSearchTableForhResult($search_words, $hResult);

    echo "<b>Partial matches</b> ";
    $hResult = searchForApplicationPartial($search_words);
    outputSearchTableForhResult($search_words, $hResult);
}

function display_page_range($iCurrentPage=1, $iPageRange=1, $iTotalPages=1, $sLinkurl=NULL)
{
    if($sLinkurl==NULL)
    {
        $sLinkurl = $_SERVER['PHP_SELF']."?";
    }
    /* display the links to each of these pages */
    $iCurrentPage = max(1,(min($iCurrentPage,$iTotalPages)));
    $iPageRange = min($iPageRange,$iTotalPages);

    if($iCurrentPage <= ceil($iPageRange/2))
    {
        $iStartPage = 1;
        $iEndPage = $iPageRange;
    } else
    {
        if($iCurrentPage + ($iPageRange/2) > $iTotalPages)
        {
            $iStartPage = $iTotalPages - $iPageRange;
            $iEndPage = $iTotalPages;
        } else
        {
            $iStartPage = $iCurrentPage - floor($iPageRange/2);
            $iEndPage = $iCurrentPage + floor($iPageRange/2);
        }
    }
    $iStartPage = max(1,$iStartPage);

    echo " ";

    if($iCurrentPage != 1)
    {
        echo "<a href=\"{$sLinkurl}&amp;iPage=1\" class=\"btn btn-default btn-sm btn-skinny\"><i class=\"fa fa-step-backward\"></i></a> ";
        $iPreviousPage = $iCurrentPage - 1;
        echo "<a href=\"{$sLinkurl}&amp;iPage=$iPreviousPage\" class=\"btn btn-default btn-sm btn-skinny\"><i class=\"fa fa-chevron-left\"></i></a> ";
    } else
    {
        echo "<button class=\"btn btn-default btn-sm btn-skinny\" disabled=\"disabled\"><i class=\"fa fa-step-backward text-muted\"></i></button> ".
             "<button class=\"btn btn-default btn-sm btn-skinny\" disabled=\"disabled\"><i class=\"fa fa-chevron-left text-muted\"></i></button> ";
    }
    /* display the desired range */
    for($iPg = $iStartPage; $iPg <= $iEndPage; $iPg++)
    {
        if($iPg != $iCurrentPage)
            echo "<a href=\"{$sLinkurl}&amp;iPage={$iPg}\" class=\"btn btn-default btn-sm btn-skinny\">{$iPg}</a> ";
        else
            echo "<button class=\"btn btn-default btn-sm btn-skinny\" disabled=\"disabled\"><span class=\"text-muted\">$iPg</span></button> ";
    }

    if($iCurrentPage < $iTotalPages)
    {
        $iNextPage = $iCurrentPage + 1;
        echo "<a href=\"{$sLinkurl}&amp;iPage={$iNextPage}\" class=\"btn btn-default btn-sm btn-skinny\"><i class=\"fa fa-chevron-right\"></i></a> ";
        echo "<a href=\"{$sLinkurl}&amp;iPage={$iTotalPages}\" class=\"btn btn-default btn-sm btn-skinny\"><i class=\"fa fa-step-forward\"></i></a> ";
    } else
    {
        echo "<button class=\"btn btn-default btn-sm btn-skinny\" disabled=\"disabled\"><i class=\"fa fa-chevron-right text-muted\"></i></button> ".
             "<button class=\"btn btn-default btn-sm btn-skinny\" disabled=\"disabled\"><i class=\"fa fa-step-forward text-muted\"></i></button> ";
    }
}

// Expand a path like /something/somedirectory/../ to /something
// from http://us2.php.net/realpath
function SimplifyPath($path) {
  $dirs = explode('/',$path);

  for($i=0; $i<count($dirs);$i++)
  {
   if($dirs[$i]=="." || $dirs[$i]=="")
   {
     array_splice($dirs,$i,1);
     $i--;
     continue;
   }

   if($dirs[$i]=="..")
   {
     $cnt = count($dirs);
     $dirs=Simplify($dirs, $i);
     $i-= $cnt-count($dirs);
   }
  }
  return implode('/',$dirs);
}

function Simplify($dirs, $idx)
{
  if($idx==0) return $dirs;

  if($dirs[$idx-1]=="..") Simplify($dirs, $idx-1);
  else  array_splice($dirs,$idx-1,2);

  return $dirs;
}
// End of snippet of code copied from php.net

/**
 * Cuts the link text to the specified number of chars if it's longer,
 * and adds an ellipsis
 */
function trimmed_link($shUrl, $iLength)
{
    $shText = $shUrl;
    $shEnd = '';
    if(strlen($shUrl) > $iLength)
    {
        $shText = substr($shUrl, 0, $iLength);
        $shText .= '...';
    }
    return "<a href=\"$shUrl\">$shText</a>";
}

/**
 * Remove html formatting from description and extract the first part of the description only.
 * This is to be used for search results, application summary tables, etc.
 */ 
function util_trim_description($sDescription)
{
    // 1) maybe it's an html description and lines are separated with tags
    $aReplace = array('<br>','<br />','</p><p>');
    $sDescription = str_replace($aReplace, "\n", $sDescription);

    // 2) let's split the dsecription into lines
    $aDesc = explode("\n",trim($sDescription));

    // 3) Avoid empty lines
    for($i = 0; $i < sizeof($aDesc); $i++)
        if(($sText = trim(strip_tags($aDesc[$i]))))
            return $sText;

    return '';
}

/* This allows us to pass on the current URL to the login form so that the user is returned
   to the current page once he has logged in */
function login_url()
{
    $sCurrentUrl = urlencode($_SERVER['REQUEST_URI']);
    $sLoginUrl = BASE."account.php?sCmd=login";

    /* If we are on the login page that means the URL already contains an sReturnTo value,
       and we don't want two.  Besides, there is little point in redirecting to the login page
       after login. */
    if(!strpos($sCurrentUrl, "sReturnTo") && !strpos($sCurrentUrl, "account.php"))
        $sLoginUrl .= "&amp;sReturnTo=".$sCurrentUrl;

    return $sLoginUrl;
}

// representation of an html color value
class color
{
  var $iRed;
  var $iGreen;
  var $iBlue;

  function color($iRed = 0, $iGreen = 0, $iBlue = 0)
  {
    $this->iRed = $iRed;
    $this->iGreen = $iGreen;
    $this->iBlue = $iBlue;
  }

  function SetColorByName($sColorName)
  {
    switch(strtolower($sColorName))
    {
    case "platinum":
      $this->iRed = 0xEC;
      $this->iGreen = 0xEC;
      $this->iBlue = 0xEC;
      break;
    case "gold":
      $this->iRed = 0xFF;
      $this->iGreen = 0xF6;
      $this->iBlue = 0x00;
      break;
    case "silver":
      $this->iRed = 0xC0;
      $this->iGreen = 0xC0;
      $this->iBlue = 0xC0;
      break;
    case "bronze":
      $this->iRed = 0xFC;
      $this->iGreen = 0xBA;
      $this->iBlue = 0x0A;
      break;
    case "garbage":
      $this->iRed = 0x99;
      $this->iGreen = 0x96;
      $this->iBlue = 0x66;
      break;
    case "white":
      $this->iRed = 0xff;
      $this->iGreen = 0xff;
      $this->iBlue = 0xff;
      break;
    case "color0":
      $this->iRed = 0xe0;
      $this->iGreen = 0xe0;
      $this->iBlue = 0xe0;
      break;
    case "color1":
      $this->iRed = 0xc0;
      $this->iGreen = 0xc0;
      $this->iBlue = 0xc0;
      break;
    default:
      break;
    }
  }

  // convert the color value into a html rgb hex string
  function GetHexString()
  {
    return sprintf("#%02X%02X%02X", $this->iRed, $this->iGreen, $this->iBlue);
  }

  // add $iAmount to each color value, maxing out at 0xFF, the largest
  // color value allowed
  function Add($iAmount)
  {
    if($this->iRed + $iAmount > 0xFF)
      $this->iRed = 0xFF;
    else
      $this->iRed += $iAmount;

    if($this->iGreen + $iAmount > 0xFF)
      $this->iGreen = 0xFF;
    else
      $this->iGreen += $iAmount;

    if($this->iBlue + $iAmount > 0xFF)
      $this->iBlue = 0xFF;
    else
      $this->iBlue += $iAmount;
  }
}

?>
