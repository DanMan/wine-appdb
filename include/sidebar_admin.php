<?php
/*****************/
/* sidebar_admin */
/*****************/

function global_admin_menu() {

    global $apidb_root;

    $g = new htmlmenu("Global Admin");
    
    $g->add("Add Category", $apidb_root."admin/addCategory.php");
    $g->add("Add Application", $apidb_root."admin/addAppFamily.php?catId=0");
    $g->add("Add Vendor", $apidb_root."admin/addVendor.php");
    
    $g->addmisc("&nbsp;");
    $g->add("List Users", $apidb_root."admin/");
    $g->add("View App Queue (".getQueuedAppCount().")", $apidb_root."admin/adminAppQueue.php");
    $g->add("View App Data Queue (".getQueuedAppDataCount().")", $apidb_root."admin/adminAppDataQueue.php");
    $g->add("View Maintainer Queue (".getQueuedMaintainerCount().")", $apidb_root."admin/adminMaintainerQueue.php");
    $g->add("View Maintainers (".getMaintainerCount().")", $apidb_root."admin/adminMaintainers.php");

    $g->addmisc("&nbsp;");
    $g->add("Comment manager", $apidb_root."admin/adminCommentView.php");
    $g->done();

}

?>
