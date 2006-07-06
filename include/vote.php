<?php
require_once(BASE."include/util.php");
/* max votes per user */
define('MAX_VOTES',3);


/**
 * count the number of votes for appId by userId
 */
function vote_count($appId, $userId = null)
{

    if(!$userId)
    {
        if($_SESSION['current']->isLoggedIn())
            $userId = $_SESSION['current']->iUserId;
        else
            return 0;
}
    $hResult = query_parameters("SELECT * FROM appVotes WHERE appId = '?' AND userId = '?'",
                            $appId, $userId);
    return mysql_num_rows($hResult);
}


/**
 * total votes by userId
 */
function vote_count_user_total($userId = null)
{
    if(!$userId)
    {
        if($_SESSION['current']->isLoggedIn())
            $userId = $_SESSION['current']->iUserId;
        else
            return 0;
    }
    $hResult = query_parameters("SELECT * FROM appVotes WHERE userId = '?'", $userId);
    return mysql_num_rows($hResult);
}


/*
 * total votes for appId
 */
function vote_count_app_total($appId)
{
    $hResult = query_parameters("SELECT * FROM appVotes WHERE appId = '?'", $appId);
    return mysql_num_rows($hResult);
}


/**
 * add a vote for appId
 */
function vote_add($appId, $slot, $userId = null)
{
    if(!$userId)
    {
        if($_SESSION['current']->isLoggedIn())
            $userId = $_SESSION['current']->iUserId;
        else
            return;
    }

    if($slot > MAX_VOTES)
        return;
    
    vote_remove($slot, $userId);

    query_parameters("INSERT INTO appVotes (id, time, appId, userId, slot)
                      VALUES (?, ?, '?', '?', '?')", "null", "null", $appId, $userId, $slot);
}


/**
 * remove vote for a slot
 */
function vote_remove($slot, $userId = null)
{
    
    if(!$userId)
    {
        if($_SESSION['current']->isLoggedIn())
            $userId = $_SESSION['current']->iUserId;
        else
            return;
    }

    $sQuery = "DELETE FROM appVotes WHERE userId = '?' AND slot = '?'";
    query_parameters($sQuery, $userId, $slot);
}


function vote_get_user_votes($userId = null)
{
    if(!$userId)
    {
        if($_SESSION['current']->isLoggedIn())
            $userId = $_SESSION['current']->iUserId;
        if(!$userId)
            return array();
    }
    $hResult = query_parameters("SELECT * FROM appVotes WHERE userId = '?'", $userId);
    if(!$hResult)
        return array();

    $obs = array();
    while($oRow = mysql_fetch_object($hResult))
        $obs[$oRow->slot] = $oRow;
    return $obs;
}


function vote_menu()
{

    $aClean = array(); //array of filtered user input
    $aClean['iAppId'] = makeSafe($_REQUEST['iAppId']);

    $m = new htmlmenu("Votes","updatevote.php");
    
    $votes = vote_get_user_votes();

    for($i = 1;$i <= MAX_VOTES; $i++)
    {
        if(isset($votes[$i]))
        {
            $sAppName = Application::lookup_name($votes[$i]->appId);
            $str = "<a href='appview.php?iAppId=".$votes[$i]->appId."'> $sAppName</a>";
            $m->add("<input type=radio name=slot value='$i'> ".$str);
        }
        else
            $m->add("<input type=radio name=iSlot value='$i'> No App Selected");
    }
    
    $m->addmisc("&nbsp;");

    $m->add("<input type=submit name=sClear value=' Clear Vote   ' class=votebutton>");
    $m->add("<input type=submit name=sVote value='Vote for App' class=votebutton>");
    
    $m->addmisc("<input type=hidden name=iAppId value={$aClean['iAppId']}>");
    
    $m->add("View Results", BASE."votestats.php");
    $m->add("Voting Help", BASE."help/?sTopic=voting");
    
    $m->done(1);    
}


function vote_update($vars)
{
    if(!$_SESSION['current']->isLoggedIn())
    {
        util_show_error_page("You must be logged in to vote");
        return;
    }

    if( !is_numeric($vars['iAppId']) OR !is_numeric($vars['iSlot']))
    {
        if(is_numeric($vars['iAppId']))
           redirect(apidb_fullurl("appview.php?iAppId=".$vars["iAppId"]));
        else
            redirect(apidb_fullurl("index.php"));

        return;
    }
    
    if($vars["sVote"])
    {
        addmsg("Registered vote for App #".$vars["iAppId"], "green");
        vote_add($vars["iAppId"], $vars["slot"]);
    } else if($vars["sClear"])
    {
        /* see if we have a vote in this slot, if we don't there is */
        /* little reason to remove it or even mention that we did anything */
        if(is_vote_in_slot($vars["slot"]))
        {
            vote_remove($vars["slot"]);
            addmsg("Removed vote for App #".$vars["iAppId"], "green");
        }
    }

    redirect(apidb_fullurl("appview.php?iAppId=".$vars["iAppId"]));
}

// tell us if there is a vote in a given slot so we don't
// display incorrect information to the user or go
// through the trouble of trying to remove a vote that doesn't exist
function is_vote_in_slot($slot, $userId = null)
{
    if(!$userId)
    {
        if($_SESSION['current']->isLoggedIn())
            $userId = $_SESSION['current']->iUserId;
        else
            return;
    }

    $sQuery = "SELECT COUNT(*) as count from appVotes WHERE userId = '?' AND slot = '?'";
    if($hResult = query_parameters($sQuery, $userId, $slot))
    {
        $oRow = mysql_fetch_object($hResult);        
        if($oRow->count != 0)
            return true;
        else
            return false;
    }
    
    return false;
}

?>
