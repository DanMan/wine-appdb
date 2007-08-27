<?php
/*****************/
/* sidebar_admin */
/*****************/
require_once(BASE."include/testData.php");
require_once(BASE."include/distribution.php");

function global_admin_menu() {

    $g = new htmlmenu("Global Admin");

    $g->add("App Queue (".application::objectGetEntriesCount(true, false).")",
            BASE."objectManager.php?sClass=application_queue&bIsQueue=true&sTitle=".
            "Application%20Queue");
    $g->add("Version Queue (".version::objectGetEntriesCount(true, false).")",
            BASE."objectManager.php?sClass=version_queue&bIsQueue=true&sTitle=".
            "Version%20Queue");
    $g->add("Screenshot Queue (".appData::objectGetEntriesCount("true",
            false, "screenshot").")",
            BASE."objectManager.php?sClass=screenshot&bIsQueue=true&sTitle=".
            "Screenshot%20Queue");
    $g->add("Maintainer Queue (".Maintainer::objectGetEntriesCount(true, false).")",
            BASE."objectManager.php?sClass=maintainer&bIsQueue=true&sTitle=".
            "Maintainer%20Queue");
    $g->add("Test Results Queue (".testData::objectGetEntriesCount(true, false).")",
            BASE."objectManager.php?sClass=testData_queue&bIsQueue=true&sTitle=".
            "Test%20Results%20Queue");
    $g->add("Bug Link Queue (".bug::objectGetEntriesCount(true, false).")",
            BASE."objectManager.php?sClass=bug&bIsQueue=true&sTitle=".
            "Bug%20Link%20Queue");

    $g->addmisc("&nbsp;");

    $g->add("Maintainer Entries (".Maintainer::getMaintainerCount().")",
            BASE."admin/adminMaintainers.php");
    $g->add("Bug Links (".bug::objectGetEntriesCount(false, false).")",
            BASE."objectManager.php?sClass=bug&bIsQueue=false&sTitle=".
            "Bug%20Links");
    $g->add("Test Results (".testData::objectGetEntriesCount(false, false).")",
            BASE."objectManager.php?sClass=testData&bIsQueue=false&sTitle=".
            "View%20Test%20Results");
    $g->add("Users Management", BASE."admin/adminUsers.php");
    $g->add("Comments Management", BASE."admin/adminCommentView.php");
    $g->add("Screenshots Management", BASE."admin/adminScreenshots.php");

    $g->addmisc("&nbsp;");

    $g->add("Rejected Applications (".application::objectGetEntriesCount(true,
            true).")",
            BASE."objectManager.php?sClass=application_queue&bIsQueue=true&bIsRejected=".
            "true&sTitle=Rejected%20Applications");
    $g->add("Rejected Versions (".version::objectGetEntriesCount(true, true).")",
            BASE."objectManager.php?sClass=version_queue&bIsQueue=true&".
            "bIsRejected=true&sTitle=Rejected%20Versions");
    $g->add("Rejected Test Results (".testData::objectGetEntriesCount(true,
            true).")",
            BASE."objectManager.php?sClass=testData_queue&bIsQueue=true".
            "&bIsRejected=true&sTitle=Rejected%20Test%20Results");

    $g->addmisc("&nbsp;");

    $g->add("Add Category", BASE."admin/addCategory.php");
    $g->add("Add Vendor", BASE."objectManager.php?sClass=vendor&bQueue=".
    "false&sAction=add&sTitle=Add%20Vendor");

    $g->done();
}

?>
