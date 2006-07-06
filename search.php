<?php
/*****************/
/* search engine */
/*****************/

/*
 * application environment
 */ 
include("path.php");
require(BASE."include/incl.php");
require(BASE."include/application.php");

$aClean = array(); //array of filtered user input

$aClean['sSearchQuery'] = makeSafe($_REQUEST['sSearchQuery']);

apidb_header("Search Results");
perform_search_and_output_results($aClean['sSearchQuery']);
apidb_footer();
?>
