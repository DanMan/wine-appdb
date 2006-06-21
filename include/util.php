<?php

function makeSafe($var)
{
/* Disable addslashes() until we can use more finely grained filtering on user input */
/*    $var = trim(addslashes($var)); */
    return $var;
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
		    }
		else
		    $arr[] = $key."=".rawurlencode($val);
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


/*
 * format date
 */
function print_date($sTimestamp)
{
    return date("F d Y  H:i:s", $sTimestamp);
}

function mysqltimestamp_to_unixtimestamp($sTimestamp)
{
  $d = substr($sTimestamp,6,2); // day
  $m = substr($sTimestamp,4,2); // month
  $y = substr($sTimestamp,0,4); // year
  $hours = substr($sTimestamp,8,2); // year
  $minutes = substr($sTimestamp,10,2); // year
  $seconds = substr($sTimestamp,12,2); // year
  return mktime($hours,$minutes,$seconds,$m, $d, $y);
}

function mysqldatetime_to_unixtimestamp($sDatetime)
{
    sscanf($sDatetime, "%4s-%2s-%2s %2s:%2s:%2s",
           &$y, &$m, &$d,
           &$hours, &$minutes, &$seconds);
    return mktime($hours,$minutes,$seconds,$m, $d, $y);
}

function get_remote()
{
    global $REMOTE_HOST, $REMOTE_ADDR;

    if($REMOTE_HOST)
        $ip = $REMOTE_HOST;
    else
        $ip = $REMOTE_ADDR;

    return $ip;
}

function htmlify_urls($text)
{
    //FIXME: wonder what the syntax is, this doesn't seem to work
    //    $text = strip_tags($text, "<a>,<b>,<i>,<ul>,<li>");

    // html-ify urls
    $urlreg = "([a-zA-Z]+://([^\t\r\n ]+))";
    $text = ereg_replace($urlreg, "<a href=\"\\1\"> \\2 </a>", $text);

    $emailreg = "([a-zA-Z0-9_%+.-]+@[^\t\r\n ]+)";
    $text = ereg_replace($emailreg, " <a href='mailto:\\1'>\\1</a>", $text);

    $text = str_replace("\n", "<br>", $text);

    return $text;
}

// open file and display contents of selected tag
function get_xml_tag ($file, $mode = null)
{
    if ($mode and file_exists($file))
    {
        $fp = @fopen($file, "r");
        $data = fread($fp, filesize($file));
        @fclose($fp);
        if (eregi("<" . $mode . ">(.*)</" . $mode . ">", $data, $out))
        {
            return $out[1];
        }
    }
    else
    {
        return null;
    }
}

/* bugzilla functions */
function make_bugzilla_version_list($varname, $cvalue)
{
    $table = BUGZILLA_DB.".versions";
    $where = "WHERE product_id=".BUGZILLA_PRODUCT_ID;
    $sQuery = "SELECT value FROM $table $where ORDER BY value";

    $hResult = query_bugzilladb($sQuery);
    if(!$hResult) return;

    echo "<select name='$varname'>\n";
    echo "<option value=\"\">Choose ...</option>\n";
    while(list($value) = mysql_fetch_row($hResult))
    {
        if($value == "unspecified")
        {
            // We do not unspecified versions!!!
        } else
        {
            if($value == $cvalue)
                echo "<option value=$value selected>$value\n";
            else
                echo "<option value=$value>$value\n";
        }
    }
    echo "</select>\n";
}

function make_maintainer_rating_list($varname, $cvalue)
{
    
    echo "<select name='$varname'>\n";
    echo "<option value=\"\">Choose ...</option>\n";
    $aRating = array("Platinum", "Gold", "Silver", "Bronze", "Garbage");
    $iMax = count($aRating);

    for($i=0; $i < $iMax; $i++)
    {
        if($aRating[$i] == $cvalue)
            echo "<option class=$aRating[$i] value=$aRating[$i] selected>$aRating[$i]\n";
        else
            echo "<option class=$aRating[$i] value=$aRating[$i]>$aRating[$i]\n";
    }
    echo "</select>\n";
}

/* get the number of queued maintainers */
function getQueuedMaintainerCount()
{
    $sQuery = "SELECT count(*) as queued_maintainers FROM appMaintainerQueue";
    $hResult = query_appdb($sQuery);
    $oRow = mysql_fetch_object($hResult);
    return $oRow->queued_maintainers;
}

/* get the total number of maintainers and applications in the appMaintainers table */
function getMaintainerCount()
{
    $sQuery = "SELECT count(*) as maintainers FROM appMaintainers";
    $hResult = query_appdb($sQuery);
    $oRow = mysql_fetch_object($hResult);
    return $oRow->maintainers;
}

/* get the total number of vendors from the vendor table */
function getVendorCount()
{
    $sQuery = "SELECT count(*) as vendors FROM vendor";
    $hResult = query_appdb($sQuery);
    $oRow = mysql_fetch_object($hResult);
    return $oRow->vendors;
}

/* Get the number of users in the database */
function getNumberOfComments()
{
    $hResult = query_appdb("SELECT count(*) as num_comments FROM appComments;");
    $oRow = mysql_fetch_object($hResult);
    return $oRow->num_comments;
}

/* Get the number of versions in the database */
function getNumberOfVersions()
{
    $hResult = query_appdb("SELECT count(versionId) as num_versions FROM appVersion WHERE versionName != 'NONAME';");
    $oRow = mysql_fetch_object($hResult);
    return $oRow->num_versions;
}

/* Get the number of maintainers in the database */
function getNumberOfMaintainers()
{
    $hResult = query_appdb("SELECT DISTINCT userId FROM appMaintainers;");
    return mysql_num_rows($hResult);
}

/* Get the number of app familes in the database */
function getNumberOfAppFamilies()
{
    $hResult = query_appdb("SELECT count(*) as num_appfamilies FROM appFamily;");
    $oRow = mysql_fetch_object($hResult);
    return $oRow->num_appfamilies;
}

/* Get the number of images in the database */
function getNumberOfImages()
{
    $hResult = query_appdb("SELECT count(*) as num_images FROM appData WHERE type='image';");
    $oRow = mysql_fetch_object($hResult);
    return $oRow->num_images;
}

/* Get the number of queued bug links in the database */
function getNumberOfQueuedBugLinks()
{
    $hResult = query_appdb("SELECT count(*) as num_buglinks FROM buglinks WHERE queued='true';");
    if($hResult)
    {
      $oRow = mysql_fetch_object($hResult);
      return $oRow->num_buglinks;
    }
    return 0;
}

/* Get the number of bug links in the database */
function getNumberOfBugLinks()
{
    $hResult = query_appdb("SELECT count(*) as num_buglinks FROM buglinks;");
    if($hResult)
    {
      $oRow = mysql_fetch_object($hResult);
      return $oRow->num_buglinks;
    }
    return 0;
}

function lookupVendorName($vendorId)
{
    $sResult = query_appdb("SELECT * FROM vendor ".
               "WHERE vendorId = ".$vendorId);
    if(!$sResult || mysql_num_rows($sResult) != 1)
        return "Unknown vendor";

    $vendor = mysql_fetch_object($sResult);
    return $vendor->vendorName;
}

/* used by outputTopXRowAppsFromRating() to reduce duplicated code */
function outputTopXRow($oRow)
{
    $oVersion = new Version($oRow->versionId);
    $oApp = new Application($oVersion->iAppId);
    $img = get_screenshot_img(null, $oRow->versionId); // image
    echo '
    <tr class="white">
      <td><a href="appview.php?versionId='.$oRow->versionId.'">'.$oApp->sName.' '.$oVersion->sName.'</a></td>
        <td>'.trim_description($oApp->sDescription).'</td>
        <td>'.$img.'</td>
    </tr>';
}

/* Output the rows for the Top-X tables on the main page */
function outputTopXRowAppsFromRating($rating, $num_apps)
{
    /* list of appIds we've already output, so we don't output */
    /* them again when filling in any empty spots in the list */
    $appIdArray = array();

    $sQuery = "SELECT appVotes.appId AS appId, appVersion.versionId, COUNT( appVotes.appId ) AS c
           FROM appVotes, appVersion
           WHERE appVersion.maintainer_rating = '$rating'
           AND appVersion.appId = appVotes.appId
           GROUP BY appVotes.appId
           ORDER BY c DESC
           LIMIT $num_apps";
    $hResult = query_appdb($sQuery);
    $num_apps-=mysql_num_rows($hResult); /* take away the rows we are outputting here */
    while($oRow = mysql_fetch_object($hResult))
    {
        array_push($appIdArray, $oRow->appId); /* keep track of the apps we've already output */
        outputTopXRow($oRow);
    }

    /* if we have any empty spots in the list, get these from applications with images */
    $sQuery = "SELECT DISTINCT appVersion.appId as appId, appVersion.versionId
           FROM appVersion, appData
           WHERE appVersion.maintainer_rating = '$rating'
           AND appVersion.versionId = appData.versionId
           AND appData.type = 'image'
           AND appData.queued = 'false'";

    /* make sure we exclude any apps we've already output */
    foreach($appIdArray as $key=>$value)
        $sQuery.="AND appVersion.appId != '".$value."' ";

    $sQuery.=" LIMIT $num_apps";

    /* get the list that will fill the empty spots */
    $hResult = query_appdb($sQuery);
    while($oRow = mysql_fetch_object($hResult))
    {
        outputTopXRow($oRow);
    }
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
    $split_words = split(" ", $search_words);
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

/* search the database and return a hResult from the query_appdb() */
function searchForApplication($search_words)
{
    /* cleanup search words */
    $search_words = cleanupSearchWords($search_words);

    /* remove any search words less than 4 letters */
    $split_words = array();
    $split_search_words = split(" ", $search_words);
    foreach($split_search_words as $key=>$value)
    {
        if(strlen($value) >= 4)
            array_push($split_words, $value);
    }

    $vendorIdArray = array();

    /* find all of the vendors whos names or urls match words in our */
    /* search parameters */
    foreach ($split_words as $key=>$value)
    {
        $sQuery = "SELECT vendorId from vendor where vendorName LIKE '%".addslashes($value)."%'
                                       OR vendorURL LIKE '%".addslashes($value)."%'";
        $hResult = query_appdb($sQuery);
        while($oRow = mysql_fetch_object($hResult))
        {
            array_push($vendorIdArray, $oRow->vendorId);
        }
    }

    /* base query */
    $sQuery = "SELECT *
           FROM appFamily, vendor
           WHERE appName != 'NONAME'
           AND appFamily.vendorId = vendor.vendorId
           AND queued = 'false'
           AND (appName LIKE '%".addslashes($search_words)."%'
           OR keywords LIKE '%".addslashes($search_words)."%'";

    /* append to the query any vendors that we matched with */
    foreach($vendorIdArray as $key=>$value)
    {
        $sQuery.=" OR appFamily.vendorId=$value";
    }

    $sQuery.=" ) ORDER BY appName";

    $hResult = query_appdb($sQuery);
    return $hResult;
}

function searchForApplicationFuzzy($search_words, $minMatchingPercent)
{
    /* cleanup search words */
    $search_words = cleanupSearchWords($search_words);

    $foundAValue = false;
    $excludeAppIdArray = array();
    $appIdArray = array();

    /* add on all of the like matches that we can find */
    $hResult = searchForApplication($search_words);
    while($oRow = mysql_fetch_object($hResult))
    {
        array_push($excludeAppIdArray, $oRow->appId);
    }

    /* add on all of the fuzzy matches we can find */
    $sQuery = "SELECT appName, appId FROM appFamily WHERE queued = 'false'";
    foreach ($excludeAppIdArray as $key=>$value)
    {
        $sQuery.=" AND appId != '$value'";
    }
    $sQuery.=";";

    /* capitalize the search words */
    $search_words = strtoupper($search_words);

    $hResult = query_appdb($sQuery);
    while($oRow = mysql_fetch_object($hResult))
    {
        $oRow->appName = strtoupper($oRow->appName); /* convert the appname to upper case */
        similar_text($oRow->appName, $search_words, $similarity_pst);
        if(number_format($similarity_pst, 0) > $minMatchingPercent)
        {
            $foundAValue = true;
            array_push($appIdArray, $oRow->appId);
        }
    }

    if($foundAValue == false)
        return null;

    $sQuery = "SELECT * from appFamily WHERE ";

    $firstEntry = true;
    foreach ($appIdArray as $key=>$value)
    {
        if($firstEntry == true)
        {
            $sQuery.="appId='$value'";
            $firstEntry = false;
        } else
        {
            $sQuery.=" OR appId='$value'";
        }
    }
    $sQuery.=" ORDER BY appName;";

    $hResult = query_appdb($sQuery);
    return $hResult;
}

function outputSearchTableForhResult($search_words, $hResult)
{
    if(($hResult == null) || (mysql_num_rows($hResult) == 0))
    {
        // do something
        echo html_frame_start("","98%");
        echo "No matches found for '". urlencode($search_words) .  "'\n";
        echo html_frame_end();
    } else
    {
        echo html_frame_start("","98%","",0);
        echo "<table width='100%' border=0 cellpadding=3 cellspacing=1>\n\n";

        echo "<tr class=color4>\n";
        echo "    <td><font color=white>Application Name</font></td>\n";
        echo "    <td><font color=white>Description</font></td>\n";
        echo "    <td><font color=white>No. Versions</font></td>\n";
        echo "</tr>\n\n";

        $c = 0;
        while($oRow = mysql_fetch_object($hResult))
        {
            //skip if a NONAME
            if ($oRow->appName == "NONAME") { continue; }
		
            //set row color
            $bgcolor = ($c % 2) ? 'color0' : 'color1';
		
            //count versions
            $hResult2 = query_appdb("SELECT count(*) as versions FROM appVersion WHERE appId = $oRow->appId AND versionName != 'NONAME' and queued = 'false'");
            $y = mysql_fetch_object($hResult2);
		
            //display row
            echo "<tr class=$bgcolor>\n";
            echo "    <td>".html_ahref($oRow->appName,BASE."appview.php?appId=$oRow->appId")."</td>\n";
            echo "    <td>".trim_description($oRow->description)."</td>\n";
            echo "    <td>$y->versions &nbsp;</td>\n";
            echo "</tr>\n\n";
		
            $c++;    
        }

        echo "<tr><td colspan=3 class=color4><font color=white>$c match(es) found</font></td></tr>\n";
        echo "</table>\n\n";
        echo html_frame_end();
    }
}

/* pass in $isVersion of true if we are processing changes for an app version */
/* or false if processing changes for an application family */
function process_app_version_changes($isVersion)
{
    /* load up the version or application depending on which values are set */
    if($isVersion)
        $oVersion = new Version($_REQUEST['versionId']);
    else
        $oApp = new Application($_REQUEST['appId']);

    // commit changes of form to database
    if(($_REQUEST['submit'] == "Update Database") && $isVersion) /* is a version */
    {
        $oVersion->GetOutputEditorValues();
        $oVersion->update();
    } else if(($_REQUEST['submit'] == "Update Database") && !$isVersion) /* is an application */
    {
        $oApp->GetOutputEditorValues();
        $oApp->update();
    } else if($_REQUEST['submit'] == "Update URL")
    {
        $sWhatChanged = "";
        $bAppChanged = false;

        if (!empty($_REQUEST['url_desc']) && !empty($_REQUEST['url']) )
        {
            // process added URL
            if($_SESSION['current']->showDebuggingInfos()) { echo "<p align=center><b>{$_REQUEST['url']}:</b> {$_REQUEST['url_desc']} </p>"; }

            if($isVersion)
            {
                $aInsert = compile_insert_string( array('versionId' => $_REQUEST['versionId'],
                                             'type' => 'url',
                                             'description' => $_REQUEST['url_desc'],
                                             'url' => $_REQUEST['url']));
            } else
            {
                $aInsert = compile_insert_string( array( 'appId' => $_REQUEST['appId'],
                                             'type' => 'url',
                                             'description' => $_REQUEST['url_desc'],
                                             'url' => $_REQUEST['url']));
            
            }
            
            $sQuery = "INSERT INTO appData ({$aInsert['FIELDS']}) VALUES ({$aInsert['VALUES']})";
	    
            if($_SESSION['current']->showDebuggingInfos()) { echo "<p align=center><b>query:</b> $sQuery </p>"; }

            if (query_appdb($sQuery))
            {
                addmsg("The URL was successfully added into the database", "green");
                $sWhatChanged .= "  Added Url:     Description: ".stripslashes($_REQUEST['url_desc'])."\n";
                $sWhatChanged .= "                         Url: ".stripslashes($_REQUEST['url'])."\n";
                $bAppChanged = true;
            }
        }
        
        // Process changed URLs  
        for($i = 0; $i < $_REQUEST['rows']; $i++)
        {
            if($_SESSION['current']->showDebuggingInfos()) { echo "<p align=center><b>{$_REQUEST['adescription'][$i]}:</b> {$_REQUEST['aURL'][$i]}: {$_REQUEST['adelete'][$i]} : {$_REQUEST['aId'][$i]} : .{$_REQUEST['aOldDesc'][$i]}. : {$_REQUEST['aOldURL'][$i]}</p>"; }

            if ($_REQUEST['adelete'][$i] == "on")
            {
	            $hResult = query_appdb("DELETE FROM appData WHERE id = '{$_REQUEST['aId'][$i]}'");

                if($hResult)
                {
                    addmsg("<p><b>Successfully deleted URL ".$_REQUEST['aOldDesc'][$i]." (".$_REQUEST['aOldURL'][$i].")</b></p>\n",'green');
                    $sWhatChanged .= "Deleted Url:     Description: ".stripslashes($_REQUEST['aOldDesc'][$i])."\n";
                    $sWhatChanged .= "                         url: ".stripslashes($_REQUEST['aOldURL'][$i])."\n";
                    $bAppChanged = true;
                }


            }
            else if( $_REQUEST['aURL'][$i] != $_REQUEST['aOldURL'][$i] || $_REQUEST['adescription'][$i] != $_REQUEST['aOldDesc'][$i])
            {
                if(empty($_REQUEST['aURL'][$i]) || empty($_REQUEST['adescription'][$i]))
                    addmsg("The URL or description was blank. URL not changed in the database", "red");
                else
                {
                    $sUpdate = compile_update_string( array( 'description' => $_REQUEST['adescription'][$i],
                                                     'url' => $_REQUEST['aURL'][$i]));
                    if (query_appdb("UPDATE appData SET $sUpdate WHERE id = '{$_REQUEST['aId'][$i]}'"))
                    {
                         addmsg("<p><b>Successfully updated ".$_REQUEST['aOldDesc'][$i]." (".$_REQUEST['aOldURL'][$i].")</b></p>\n",'green');
                         $sWhatChanged .= "Changed Url: Old Description: ".stripslashes($_REQUEST['aOldDesc'][$i])."\n";
                         $sWhatChanged .= "                     Old Url: ".stripslashes($_REQUEST['aOldURL'][$i])."\n";
                         $sWhatChanged .= "             New Description: ".stripslashes($_REQUEST['adescription'][$i])."\n";
                         $sWhatChanged .= "                     New url: ".stripslashes($_REQUEST['aURL'][$i])."\n";
                         $bAppChanged = true;
                    }
                }
            }
        }
        if ($bAppChanged)
        {
            $sEmail = get_notify_email_address_list($_REQUEST['appId']);
	    $oApp = new Application($_REQUEST['appId']);
            if($sEmail)
            {
                if($isVersion)
                    $sSubject = "Links for ".$oApp->sName." ".$oVersion->sName." have been updated by ".$_SESSION['current']->sRealname;
                else
                    $sSubject = "Links for ".$oApp->sName." have been updated by ".$_SESSION['current']->sRealname;
                    
                $sMsg  = APPDB_ROOT."appview.php?appId=".$_REQUEST['appId']."\n";
                $sMsg .= "\n";
                $sMsg .= "The following changes have been made:";
                $sMsg .= "\n";
                $sMsg .= $sWhatChanged."\n";
                $sMsg .= "\n";

                mail_appdb($sEmail, $sSubject ,$sMsg);
            }
        }
    }
}

function perform_search_and_output_results($search_words)
{
    echo "<b>Searching for '".$search_words."'";

    echo "<center><b>Like matches</b></center>";
    $hResult = searchForApplication($search_words);
    outputSearchTableForhResult($search_words, $hResult);

    $minMatchingPercent = 60;
    echo "<center><b>Fuzzy matches - minimum ".$minMatchingPercent."% match</b></center>";
    $hResult = searchForApplicationFuzzy($search_words, $minMatchingPercent);
    outputSearchTableForhResult($search_words, $hResult);
}

function display_page_range($currentPage=1, $pageRange=1, $totalPages=1, $linkurl=NULL)
{
    if($linkurl==NULL)
    {
        $linkurl = $_SERVER['PHP_SELF']."?";
    }
    /* display the links to each of these pages */
    $currentPage = max(1,(min($currentPage,$totalPages)));
    $pageRange = min($pageRange,$totalPages);

    if($currentPage <= ceil($pageRange/2))
    {
        $startPage = 1;
        $endPage = $pageRange;
    } else
    {
        if($currentPage + ($pageRange/2) > $totalPages)
        {
            $startPage = $totalPages - $pageRange;
            $endPage = $totalPages;
        } else
        {
            $startPage = $currentPage - floor($pageRange/2);
            $endPage = $currentPage + floor($pageRange/2);
        }
    }
    $startPage = max(1,$startPage);

    if($currentPage != 1)
    {
        echo "<a href='".$linkurl."&page=1'>|&lt</a>&nbsp";
        $previousPage = $currentPage - 1;
        echo "<a href='".$linkurl."&page=$previousPage'>&lt</a>&nbsp";
    } else
    {
        echo "|&lt &lt ";
    }
    /* display the desired range */
    for($x = $startPage; $x <= $endPage; $x++)
    {
        if($x != $currentPage)
            echo "<a href='".$linkurl."&page=".$x."'>$x</a> ";
        else
            echo "$x ";
    }

    if($currentPage < $totalPages)
    {
        $nextPage = $currentPage + 1;
        echo "<a href='".$linkurl."&page=$nextPage'>&gt</a> ";
        echo "<a href='".$linkurl."&page=$totalPages'>&gt|</a> ";
    } else
        echo "&gt &gt|";
    
}

// Expand a path like /something/somedirectory/../ to /something
// from http://us2.php.net/realpath
function SimplifyPath($path) {
  $dirs = explode('/',$path);

  for($i=0; $i<count($dirs);$i++) {
   if($dirs[$i]=="." || $dirs[$i]=="") {
     array_splice($dirs,$i,1);
     $i--;
   }

   if($dirs[$i]=="..") {
     $cnt = count($dirs);
     $dirs=Simplify($dirs, $i);
     $i-= $cnt-count($dirs);
   }
  }
  return implode('/',$dirs);
}

function Simplify($dirs, $idx) {
  if($idx==0) return $dirs;

  if($dirs[$idx-1]=="..") Simplify($dirs, $idx-1);
  else  array_splice($dirs,$idx-1,2);

  return $dirs;
}
// End of snippet of code copied from php.net

// Use the directory of PHP_SELF and BASE and the relative path
// to get a simplified path to an appdb directory or file
// Used for the Xinha _editor_url because some plugins like SpellChecker
// won't work with relative paths like ../xinha
function GetSimplifiedPath($relative)
{
    return "/".SimplifyPath(dirname($_SERVER[PHP_SELF])."/".BASE.$relative);
}

function HtmlAreaLoaderScript($aTextareas)
{
    static $outputIndex = 0;

    echo '
  <script type="text/javascript">';
    // You must set _editor_url to the URL (including trailing slash) where
    // where xinha is installed, it's highly recommended to use an absolute URL
    //  eg: _editor_url = "/path/to/xinha/";
    // You may try a relative URL if you wish]
    //  eg: _editor_url = "../";
    // in this example we do a little regular expression to find the absolute path.
    // NOTE: we use GetSimplifiedPath() because we cannot use a relative path and have
    //   all of the plugins work correctly.  Specifically the SpellChecker plugin
    //   requires a absolute url path to the xinha directory
    echo '
    _editor_url  = "'.GetSimplifiedPath("xinha/").'", \'\';
    _editor_lang = "en";      // And the language we need to use in the editor.
  </script>';

    echo '
  <!-- Load up the actual editor core -->
  <script type="text/javascript" src="'.BASE.'xinha/htmlarea.js"></script>

  <script type="text/javascript">
    xinha_editors_'.$outputIndex.' = null;
    xinha_init_'.$outputIndex.'    = null;';

    /* only need to nll out the first set of config and plugins */
    /* as we will reuse these for additional htmlareas */
    if($outputIndex == 0)
    {
        echo '
    xinha_config_'.$outputIndex.'  = null;
    xinha_plugins_'.$outputIndex.' = null;';
    }

    echo '
    // This contains the names of textareas we will make into Xinha editors
    xinha_init_'.$outputIndex.' = xinha_init_'.$outputIndex.' ? xinha_init_'.$outputIndex.' : function()
    {';

      /** STEP 1 ***************************************************************
       * First, what are the plugins you will be using in the editors on this
       * page.  List all the plugins you will need, even if not all the editors
       * will use all the plugins.
       ************************************************************************/
    if($outputIndex == 0)
    {
      echo '
      xinha_plugins_'.$outputIndex.' = xinha_plugins_'.$outputIndex.' ? xinha_plugins_'.$outputIndex.' :
      [
       \'CharacterMap\',
       \'CharCounter\',
       \'ContextMenu\',
       \'FullScreen\',
       \'ListType\',
       \'SpellChecker\',
       \'Stylist\',
       \'SuperClean\',
       \'TableOperations\',
       \'DynamicCSS\',
       \'FindReplace\'
      ];

      // THIS BIT OF JAVASCRIPT LOADS THE PLUGINS, NO TOUCHING  :)
      if(!HTMLArea.loadPlugins(xinha_plugins_'.$outputIndex.', xinha_init_'.$outputIndex.')) return;';
    } else
    {
      echo '
      // THIS BIT OF JAVASCRIPT LOADS THE PLUGINS, NO TOUCHING  :)
      if(!HTMLArea.loadPlugins(xinha_plugins_0, xinha_init_'.$outputIndex.')) return;';   
    }

      /** STEP 2 ***************************************************************
       * Now, what are the names of the textareas you will be turning into
       * editors?
       ************************************************************************/

      // NOTE: we generate the editor names here so we can easily have any number of htmlarea editors
      //  and can reuse all of this code
      echo '
      xinha_editors_'.$outputIndex.' = xinha_editors_'.$outputIndex.' ? xinha_editors_'.$outputIndex.' :
      [';

      $firstEntry = true;
      foreach($aTextareas as $key=>$value)
      {
          if($firstEntry)
          {
              echo "'$value'";
              $firstEntry = false;
          } else
          {
              echo ", '$value'";
          }
      }

      echo '
      ];';

      /** STEP 3 ***************************************************************
       * We create a default configuration to be used by all the editors.
       * If you wish to configure some of the editors differently this will be
       * done in step 5.
       *
       * If you want to modify the default config you might do something like this.
       *
       *   xinha_config = new HTMLArea.Config();
       *   xinha_config.width  = \'640px\';
       *   xinha_config.height = \'420px\';
       *
       *************************************************************************/
      /* We only need the configuration output for the first htmlarea on a given page */
      if($outputIndex == 0)
      {
       echo '
       xinha_config_'.$outputIndex.' = new HTMLArea.Config();

       xinha_config_'.$outputIndex.'.toolbar = [
        ["popupeditor"],
        ["separator","fontsize","bold","italic","underline","strikethrough"],
        ["separator","forecolor","hilitecolor","textindicator"],
        ["separator","subscript","superscript"],
        ["linebreak","separator","justifyleft","justifycenter","justifyright","justifyfull"],
        ["separator","insertorderedlist","insertunorderedlist","outdent","indent"],
        ["separator","inserthorizontalrule","createlink","inserttable"],
        ["separator","undo","redo","selectall"], (HTMLArea.is_gecko ? [] : ["cut","copy","paste","overwrite","saveas"]),
        ["separator","killword","removeformat","toggleborders","lefttoright", "righttoleft","separator","htmlmode","about"]
        ];
    
       xinha_config_'.$outputIndex.'.pageStyle = "@import url('.BASE."application.css".');";
       ';
      }

      /** STEP 4 ***************************************************************
       * We first create editors for the textareas.
       *
       * You can do this in two ways, either
       *
       *   xinha_editors   = HTMLArea.makeEditors(xinha_editors, xinha_config, xinha_plugins);
       *
       * if you want all the editor objects to use the same set of plugins, OR;
       *
       *   xinha_editors = HTMLArea.makeEditors(xinha_editors, xinha_config);
       *   xinha_editors['myTextArea'].registerPlugins(['Stylist','FullScreen']);
       *   xinha_editors['anotherOne'].registerPlugins(['CSS','SuperClean']);
       *
       * if you want to use a different set of plugins for one or more of the
       * editors.
       ************************************************************************/

       echo '
       xinha_editors_'.$outputIndex.'   = HTMLArea.makeEditors(xinha_editors_'.$outputIndex.',
          xinha_config_0, xinha_plugins_0);';

      /** STEP 5 ***************************************************************
       * If you want to change the configuration variables of any of the
       * editors,  this is the place to do that, for example you might want to
       * change the width and height of one of the editors, like this...
       *
       *   xinha_editors.myTextArea.config.width  = '640px';
       *   xinha_editors.myTextArea.config.height = '480px';
       *
       ************************************************************************/


      /** STEP 6 ***************************************************************
       * Finally we "start" the editors, this turns the textareas into
       * Xinha editors.
       ************************************************************************/
       echo '
      HTMLArea.startEditors(xinha_editors_'.$outputIndex.');
    }';

    if($outputIndex != 0)
    {
      echo '
      var old_on_load_'.$outputIndex.' = window.onload;
      window.onload = function() {
      if (typeof old_on_load_'.$outputIndex.' == "function") old_on_load_'.$outputIndex.'();
        xinha_init_'.$outputIndex.'();
      }';
    } else
    {
        echo '
    window.onload = xinha_init_'.$outputIndex.';';
    }

    echo '    
    </SCRIPT>
      ';

    $outputIndex++; /* increment the output index */
}

?>
