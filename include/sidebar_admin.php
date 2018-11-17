<?php
/*****************/
/* sidebar_admin */
/*****************/
require_once(BASE."include/testData.php");
require_once(BASE."include/distribution.php");

function global_admin_menu() {

    $g = new htmlmenu("Global Admin");

    $g->add("App Queue (".application::objectGetEntriesCount('queued').")",
            BASE.'objectManager.php?sClass=application_queue&amp;sState=queued&amp;sTitle='.
            'Application%20Queue');
    $g->add("Version Queue (".version::objectGetEntriesCount('queued').")",
            BASE.'objectManager.php?sClass=version_queue&amp;sState=queued&amp;sTitle='.
            'Version%20Queue');
    $g->add("Screenshot Queue (".appData::objectGetEntriesCount('queued', "screenshot").")",
            BASE.'objectManager.php?sClass=screenshot&amp;sState=queued&amp;sTitle='.
            'Screenshot%20Queue');
    $g->add("Maintainer Queue (".Maintainer::objectGetEntriesCount('queued').")",
            BASE.'objectManager.php?sClass=maintainer&amp;sState=queued&amp;sTitle='.
            'Maintainer%20Queue');
    $g->add("Test Results Queue (".testData::objectGetEntriesCount('queued').")",
            BASE.'objectManager.php?sClass=testData_queue&amp;sState=queued&amp;sTitle='.
            'Test%20Results%20Queue');
    $g->add("Bug Link Queue (".bug::objectGetEntriesCount('queued').")",
            BASE.'objectManager.php?sClass=bug&amp;sState=queued&amp;sTitle='.
            'Bug%20Link%20Queue');

    $g->addmisc("&nbsp;");

    $g->add("Maintainer Entries (".Maintainer::objectGetEntriesCount('accepted').")",
            BASE."objectManager.php?sClass=maintainerView&sTitle=View+Maintainers");
    $g->add("Bug Links (".bug::objectGetEntriesCount('accepted').")",
            BASE."objectManager.php?sClass=bug&amp;sTitle=".
            "Bug%20Links");
    $g->add("Test Results (".testData::objectGetEntriesCount('accepted').")",
            BASE."objectManager.php?sClass=testData&amp;sTitle=".
            "View%20Test%20Results");
    $g->add("Users Management", BASE."admin/adminUsers.php");
    $g->add('Comments Management', BASE.'objectManager.php?sClass=comment');
    $g->add("Screenshots Management", BASE."admin/adminScreenshots.php");

    $g->addmisc("&nbsp;");

    $g->add("Rejected Applications (".application::objectGetEntriesCount('rejected').")",
            BASE.'objectManager.php?sClass=application_queue&amp;sState=rejected&amp;'.
            'sTitle=Rejected%20Applications');
    $g->add("Rejected Versions (".version::objectGetEntriesCount('rejected').")",
            BASE.'objectManager.php?sClass=version_queue&amp;sState=rejected&amp;'.
            'sTitle=Rejected%20Versions');
    $g->add("Rejected Test Results (".testData::objectGetEntriesCount('rejected').")",
            BASE.'objectManager.php?sClass=testData_queue&amp;sState=rejected&amp;'.
            'sTitle=Rejected%20Test%20Results');

    $g->addmisc("&nbsp;");

    $g->add('Manage Common Replies', BASE.'objectManager.php?sClass=CommonReply');
    $g->add("Add Category", BASE."objectManager.php?sClass=category&amp;sAction=add&amp;sTitle=Add+Category");
    $g->add("Add Vendor", BASE."objectManager.php?sClass=vendor&amp;bQueue=".
    "false&amp;sAction=add&amp;sTitle=Add%20Vendor");
    $g->add('Control Center', BASE.'admin.php');

    $g->done();
}


