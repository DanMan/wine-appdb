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

    $g = new htmlmenu(APPDB_OWNER." Menu");
    $g->add("AppDB", BASE);
    $g->add(APPDB_OWNER, APPDB_OWNER_URL);
    $g->add("Bugzilla", BUGZILLA_ROOT);
    $g->add("Wine Wiki", "http://wiki.winehq.org");
    $g->done();

    $g = new htmlmenu("AppDB");
    $g->add("Screenshots", BASE."objectManager.php?sClass=screenshot&sTitle=View+Screenshots");
    $g->add("Browse Apps", BASE."appbrowse.php");
    $g->add("Browse Newest Apps", BASE."objectManager.php?sClass=application&".
            'sTitle=Newest%20apps&sOrderBy=appId&bAscending=false');
    $g->add("Downloadable Apps", BASE."browse_downloadable.php");
    $g->add("Browse Apps by Rating", BASE."browse_by_rating.php");
    $g->add("Top 25", BASE."votestats.php");
    $g->add("Submit Application", BASE."objectManager.php?sClass=application_queue&".
            "sTitle=Submit+Application&sAction=add");
    $g->add("Help &amp; Documentation", BASE."help/");
    $g->add("Statistics", BASE."appdbStats.php");
    $g->add("Distributions (".distribution::objectGetEntriesCount(false, false).")", BASE.'objectManager.php?sClass=distribution&sTitle=View%20Distributions');
    $g->add("Vendors (".vendor::objectGetEntriesCount(false, false).")", BASE.'objectManager.php?sClass=vendor&sTitle=View%20Vendors');
    $g->add("Email your suggestions for improving the AppDB", "mailto:appdb@winehq.org");
    $g->done();

    $g = new htmlmenu("Search");
    $g->addmisc(app_search_box(!empty($aClean['sSearchQuery']) ? $aClean['sSearchQuery'] : ''));
    $g->done();

}


function app_search_box($q = '')
{
    // google custom search dialog
    // used in place of appdb specific search engine code
    // Chris Morgan <cmorgan@alum.wpi.edu> maintains
    // the search engine settings
    $shSearchStr = '
<!-- Google CSE Search Box Begins -->
  <script type="text/javascript">
  document.write(\'<form id=\"searchbox_013271970634691685804:bc-56dvxydi\" action=\"http://appdb.winehq.org/search_results.php\">\')
    document.write(\'<input type="hidden" name="cx" value="013271970634691685804:bc-56dvxydi" />\')
    document.write(\'<input type=\"hidden\" name=\"cof" value=\"FORID:11\" />\')
    document.write(\'<input name=\"q\" type=\"text\" size=\"20\" />\')
    document.write(\'<input type=\"submit\" name=\"sa\" value=\"Search\" />\')
  document.write(\'</form>\')
  </script>
  <script type="text/javascript" src="http://www.google.com/coop/cse/brand?form=searchbox_013271970634691685804%3Abc-56dvxydi"></script>
<!-- Google CSE Search Box Ends -->
';
    // Search dialog using our own search engine, displayed when
    // JavaScript is unavailable
    $shSearchStr .= '
  <noscript>
    <form method="post" action="search.php">
    <input type="text" size="20" name="sSearchQuery" />
    <input type="submit" value="Search" />
    </form>
  </noscript>
';

   return $shSearchStr;
}

?>
