<?php
/************************/
/* Add Application Note */
/************************/

include("path.php");
require(BASE."include/incl.php");
require(BASE."include/application.php");
require(BASE."include/mail.php");

//check for admin privs
if(!$_SESSION['current']->isLoggedIn() || (!$_SESSION['current']->hasPriv("admin") && !$_SESSION['current']->isMaintainer($_REQUEST['appId'],$_REQUEST['versionId'])) )
{
    errorpage("Insufficient Privileges!");
    exit;
}

//set link for version
if(is_numeric($_REQUEST['versionId']) and !empty($_REQUEST['versionId']))
{
    $versionLink = "&versionId={$_REQUEST['versionId']}";
}
else 
    exit;

if(!is_numeric($_REQUEST['appId']))
{
    errorpage('Wrong ID');
    exit;
}  

if($_REQUEST['sub'] == "Submit")
{

    $aInsert = compile_insert_string(array( 'noteTitle' =>$_REQUEST['noteTitle'],
                                            'NoteDesc' => $_REQUEST['noteDesc'],
                                            'appId' => $_REQUEST['appId'],
                                            'versionId' => $_REQUEST['versionId'] ));

    if (query_appdb("INSERT INTO `appNotes` ({$aInsert['FIELDS']}) VALUES ({$aInsert['VALUES']})"))
    {
        // successful
        $sEmail = get_notify_email_address_list($_REQUEST['appId'], $_REQUEST['versionId']);
        if($sEmail)
        {
            $sFullAppName  = "Application: ".lookupAppName($_REQUEST['appId']);
            $sFullAppName .= " Version: ".lookupVersionName($_REQUEST['appId'], $_REQUEST['versionId']);
            $sMsg  = APPDB_ROOT."appview.php?appId=".$_REQUEST['appId']."&versionId=".$_REQUEST['versionId']."\r\n";
            $sMsg .= "\r\n";
            $sMsg .= $_SESSION['current']->sRealname." added note to ".$sFullAppName."\r\n";
            $sMsg .= "\r\n";
            $sMsg .= "title: ".$_REQUEST['noteTitle']."\r\n";
            $sMsg .= "\r\n";
            $sMsg .= $_REQUEST['noteDesc']."\r\n";

            mail_appdb($sEmail, $sFullAppName ,$sMsg);
        }
        $statusMessage = "<p>Note added into the database</p>\n";
        addmsg($statusMessage,Green);
    }
    redirect(apidb_fullurl("appview.php?appId=".$_REQUEST['appId'].$versionLink));
    exit;
}
else if($_REQUEST['sub'] == 'Preview' OR empty($_REQUEST['submit']))
{
    apidb_header("Add Application Note");

    echo "<form method=post action='addAppNote.php'>\n";
    echo html_frame_start("Add Application Note {$_REQUEST['appId']}", "90%","",0);
    echo html_table_begin("width='100%' border=0 align=left cellpadding=6 cellspacing=0 class='box-body'");

    echo "<input type=hidden name='appId' value='{$_REQUEST['appId']}'>";
    echo "<input type=hidden name='versionId' value='{$_REQUEST['versionId']}'>";
    echo '<tr><td colspan=2 class=color4>';
    echo '<center><b>You can use html to make your Warning, Howto or Note look better.</b></center>';
    echo '</td></tr>',"\n";

    echo add_br($_REQUEST['noteDesc']);

    if ($_REQUEST['noteTitle'] == "HOWTO" || $_REQUEST['noteTitle'] == "WARNING")
    {
        echo "<input type=hidden name='noteTitle' value='{$_REQUEST['noteTitle']}'>";
        echo "<tr><td class=color1>Type</td><td class=color0>{$_REQUEST['noteTitle']}</td></tr>\n";
    }
    else
    {
        echo "<tr><td class=color1>Title</td><td class=color0><input size='80%' type='text' name='noteTitle' type='text' value='{$_REQUEST['noteTitle']}'></td></tr>\n";
    }
    echo '<tr><td class=color4>Description</td><td class=color0>', "\n";
    echo '<textarea cols=50 rows=10 name="noteDesc">'.stripslashes($_REQUEST['noteDesc']).'</textarea></td></tr>',"\n";

    echo '<tr><td colspan=2 align=center class=color3>',"\n";
    echo '<input type="submit" name=sub value="Preview">&nbsp',"\n";
    echo '<input type="submit" name=sub value="Submit"></td></tr>',"\n";
    echo html_table_end();
    echo html_frame_end();
    
    echo html_back_link(1,BASE."appview.php?appId={$_REQUEST['appId']}$versionLink");
    apidb_footer();
}

?>
