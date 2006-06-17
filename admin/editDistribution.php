<?php
include("path.php");
require(BASE."include/incl.php");
require(BASE."include/distributions.php");

$aClean = array(); //array of filtered user input

$aClean['iDistributionId'] = makeSafe($_REQUEST['iDistributionId']);
$aClean['submit'] = makeSafe($_REQUEST['submit']);

if(!$_SESSION['current']->hasPriv("admin"))
{
    errorpage("Insufficient privileges.");
    exit;
}


$oDistribution = new distribution($aClean['iDistributionId']);
if($aClean['Submit'])
{
    $oDistribution->GetOutputEditorValues();

    if($oDistribution->iDistributionId)
        $oDistribution->update();
    else
    {
       $oDistribution->create();
    } 
  
    redirect(apidb_fullurl("distributionView.php"));
    exit;
}
else
{
    if ($oDistribution->iDistributionId)
        apidb_header("Edit Distribution");
    else
        apidb_header("Add Distribution");

    echo '<form name="qform" action="'.$_SERVER['PHP_SELF'].'" method="post" enctype="multipart/form-data">',"\n";

    $oDistribution->OutputEditor();

    echo '<tr valign=top><td class=color3 align=center colspan=2>',"\n";
    echo '<input name="Submit" type="submit" value="Submit" class="button" >&nbsp',"\n";
    echo '</td></tr>',"\n";

    echo "</form>";
    echo html_frame_end("&nbsp;");
    apidb_footer();
}
?>
