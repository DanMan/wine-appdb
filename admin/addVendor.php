<?php

include("path.php");
include(BASE."include/"."incl.php");
include(BASE."include/"."tableve.php");

if(!$_SESSION['current']->hasPriv("admin"))
{
    errorpage();
    exit;
}
else
{
    global $admin_mode;
    $admin_mode = 1;
}

apidb_header("Add Vendor");

$t = new TableVE("create");

if($_POST)
{
    $t->update($_POST);
}
else
{
    $table = "vendor";
    $query = "INSERT INTO $table VALUES(0, 'NONAME', null)";

    query_appdb("DELETE FROM $table WHERE vendorName = 'NONAME'");

    if(debugging())
	echo "$query <br /><br />\n";

    $t->create($query, $table, "vendorId");
}

apidb_footer();

?>
