<?php
/**
 * Application browser.
 *
 * Optional parameters:
 *  - iCatId, shows applications that belong to the category identified by iCatId
 */

// application environment
require("path.php");
require(BASE."include/"."incl.php");
require_once(BASE."include/"."appdb.php");
require_once(BASE."include/"."category.php");

//output header
apidb_header("Search results");

echo "<div class='default_container'>\n";

echo '
<!-- Google Search Result Snippet Begins -->
  <div id="results_013271970634691685804:bc-56dvxydi"></div>
  <script type="text/javascript">
    var googleSearchIframeName = "results_013271970634691685804:bc-56dvxydi";
    var googleSearchFormName = "searchbox_013271970634691685804:bc-56dvxydi";
    var googleSearchFrameWidth = 600;
    var googleSearchFrameborder = 0;
    var googleSearchDomain = "www.google.com";
    var googleSearchPath = "/cse";
  </script>
  <script type="text/javascript" src="http://www.google.com/afsonline/show_afs_search.js"></script>
<!-- Google Search Result Snippet Ends -->
';

echo "</div>\n";

apidb_footer();


