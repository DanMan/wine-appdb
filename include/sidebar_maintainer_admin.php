<?php
/*****************/
/* sidebar_admin */
/*****************/

function global_maintainer_admin_menu() {

    $g = new htmlmenu("Maintainer Admin");

    $g->add("View Version Queue (".version::objectGetEntriesCount(true, false).")",
            BASE."objectManager.php?sClass=version&bIsQueue=true&sTitle=".
            "Version%20Queue");
    $g->add("View Screenshot Queue (".appData::objectGetEntriesCount("true",
            false, "screenshot").")",
            BASE."objectManager.php?sClass=screenshot&bIsQueue=true&sTitle=".
            "Screenshot%20Queue");
    $g->add("View Test Results Queue (".testData::objectGetEntriesCount(true, false).")",
            BASE."objectManager.php?sClass=testData&bIsQueue=true&sTitle=".
            "Test%20Results%20Queue");
    $g->done();
}

?>
