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

$iId = getInput('iCatId', $aClean);

util_redirect_and_exit(BASE."objectManager.php?sClass=category&iId=$iId&sAction=view&sTitle=Browse+Applications");


