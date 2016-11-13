<?php
/***********/
/* SideBar */
/***********/
require_once(BASE."include/distribution.php");
require_once(BASE."include/vendor.php");
require_once(BASE."include/util.php");
  
function global_sidebar_menu()
{
    global $aClean;

    $g = new htmlmenu("AppDB");
    $g->add('Home', BASE.'index.php');
    $g->add("Screenshots", BASE."objectManager.php?sClass=screenshot&amp;sTitle=View+Screenshots");
    $g->add("Browse Apps", BASE."objectManager.php?sClass=application&amp;".
            'sTitle=Browse%20Applications&amp;sOrderBy=appName&amp;bAscending=true');
    $g->add('Browse by Developer', BASE.'objectManager.php?sClass=vendor&amp;sTitle=Browse%20by%20Developer');
    $g->add("Top 25", BASE."votestats.php");
    $g->add("Submit App", BASE."objectManager.php?sClass=application_queue&amp;".
            "sTitle=Submit+Application&amp;sAction=add");
    $g->add("Help", "https://wiki.winehq.org/Category:AppDB");
    $g->add("Statistics", BASE."appdbStats.php");
    $g->add('Distributions ('.distribution::objectGetEntriesCount('accepted').')', BASE.'objectManager.php?sClass=distribution&amp;sTitle=View%20Distributions');
    $g->add("Email Us", "mailto:appdb@winehq.org");
    $g->done();

}

?>
