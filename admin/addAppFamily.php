<?php

include("path.php");
include(BASE."include/"."incl.php");
include(BASE."include/"."tableve.php");

//check for admin privs
if(!havepriv("admin"))
{
    errorpage("Insufficient Privileges!");
    exit;
}

apidb_header("Add Application Family");

$t = new TableVE("create");

if($_POST)
{
    $t->update($_POST);
}
else
{
    $table = "appFamily";
    $query = "INSERT INTO $table VALUES(0, 'NONAME', 0, null, null, null, $catId)";

    query_appdb("DELETE FROM $table WHERE appName = 'NONAME'");

    if(debugging()) { echo "<p align=center><b>query:</b> $query </p>"; }

    $t->create($query, $table, "appId");
}

apidb_footer();

?>
