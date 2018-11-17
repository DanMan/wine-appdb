<?php
/**
 * Search engine.
 *
 * Mandatory parameters:
 *  - sSearchQuery, user search query
 * 
 * TODO:
 *  - prefix perform_search_and_output_results with a module prefix
 */

// application environment
require("path.php");
require(BASE."include/incl.php");

apidb_header("Search Results");
perform_search_and_output_results($aClean['sSearchQuery']);
apidb_footer();

