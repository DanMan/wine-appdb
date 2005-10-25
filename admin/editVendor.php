<?php
include("path.php");
require_once(BASE."include/incl.php");
require_once(BASE."include/vendor.php");

if(!$_SESSION['current']->hasPriv("admin"))
{
    errorpage();
    exit;
}

$oVendor = new Vendor($_REQUEST['iVendorId']);
if($_REQUEST['Submit'])
{
    $oVendor->update($_REQUEST['sName'],$_REQUEST['sWebpage']);
    redirect(apidb_fullurl("vendorview.php"));
}
else
{
    if($oVendor->iVendorId)
        apidb_header("Edit Vendor");
    else
        apidb_header("Add Vendor");

    // Show the form
    echo '<form name="qform" action="'.$_SERVER['PHP_SELF'].'" method="post" enctype="multipart/form-data">',"\n";

    $oVendor->OutputEditor();

    echo '<tr valign=top><td class=color3 align=center colspan=2>',"\n";
    echo '<input name="Submit" type="submit" value="Submit" class="button" >&nbsp',"\n";
    echo '</td></tr>',"\n";

    echo "</form>";
    echo html_frame_end("&nbsp;");
    apidb_footer();

}
?>
