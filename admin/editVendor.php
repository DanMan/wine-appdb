<?php
require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/vendor.php");

if(!$_SESSION['current']->hasPriv("admin"))
    util_show_error_page_and_exit();

$oVendor = new Vendor($aClean['iVendorId']);
if($aClean['sSubmit'])
{
    $oVendor->update($aClean['sName'],$aClean['sWebpage']);
    util_redirect_and_exit(apidb_fullurl("vendorview.php"));
}
else
{
    if($oVendor->iVendorId)
        apidb_header("Edit Vendor");
    else
        apidb_header("Add Vendor");

    // Show the form
    echo '<form name="sQform" action="'.$_SERVER['PHP_SELF'].'" method="post" enctype="multipart/form-data">',"\n";

    $oVendor->outputEditor();

    echo '<tr valign=top><td class=color3 align=center colspan=2>',"\n";
    echo '<input name="sSubmit" type="submit" value="Submit" class="button" >&nbsp',"\n";
    echo '</td></tr>',"\n";

    echo "</form>";
    echo html_frame_end("&nbsp;");
    apidb_footer();

}
?>
