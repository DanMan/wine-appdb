<?php

require_once("path.php");
require_once(BASE."/include/incl.php");

/**
 *  Page containing a form for sending e-mail
 *
 */

$oUser = new User($_SESSION['current']->iUserId);

/* Restrict error to logged-in users */
if(!$oUser->isLoggedIn())
{
    login_form();
    exit;
}


$oRecipient = new User($aClean['iRecipientId']);

if(!User::exists($oRecipient->sEmail))
    util_show_error_page_and_exit("User not found");

/* Check for errors */
if((!$aClean['sMessage'] || !$aClean['sSubject']) && $aClean['sSubmit'])
{
    $error = "<font color=\"red\">Please enter both a subject and a ".
             "message.</font>";
    $aClean['sSubmit'] = "";
}

/* Display the feedback form if nothing else is specified */
if(!$aClean['sSubmit'])
{
    apidb_header("E-mail $oRecipient->sRealname");
    echo html_frame_start("Send us your suggestions",400,"",0);

    echo $error;
    echo "<form action=\"".$_SERVER['PHP_SELF']."\" method=\"post\">";

    /* User manager */
    if($_SESSION['current']->hasPriv("admin"))
    {
        echo "<p><a href=\"".BASE."preferences.php?iUserId=".
                $oRecipient->iUserId."&sSearch=Administrator&iLimit".
                "=100&sOrderBy=email\">User manager</a></p>";
    }

    echo "<p>E-mail $oRecipient->sRealname.</p>";
    
    $oTable = new Table();
    $oTable->SetWidth("100%");
    $oTable->SetBorder(0);
    $oTable->SetCellPadding(2);
    $oTable->SetCellSpacing(2);
    
    $oTableRow = new TableRow();
    $oTableRow->SetClass("color4");
    $oTableRow->AddTextCell("Subject");
    $oTableCell = new TableCell("<input type=\"text\" name=\"sSubject\" size=\"71\"".
                                " value=\"".$aClean['sSubject']."\" />");
    $oTableRow->AddCell($oTableCell);
    $oTable->AddRow($oTableRow);

    $oTableRow = new TableRow();
    $oTableRow->SetClass("color4");
    $oTableCell = new TableCell("Message");
    $oTableCell->SetValign("top");
    $oTableRow->AddCell($oTableCell);
    $oTableCell = new TableCell("<textarea name=\"sMessage\" rows=\"15\" cols=\"60\">"
                                .$aClean['sMessage']."</textarea>");
    $oTableRow->AddCell($oTableCell);
    $oTable->AddRow($oTableRow);

    $oTableRow = new TableRow();
    $oTableRow->AddTextCell("");
    $oTableRow->AddTextCell("<input type=\"submit\" value=\"Submit\" name=\"sSubmit\" />");
    $oTable->AddRow($oTableRow);

    // output the table
    echo $oTable->GetString();

    echo "<input type=\"hidden\" name=\"iRecipientId\" ".
    "value=\"$oRecipient->iUserId\" />";

    echo "</form>\n";

    echo html_frame_end("&nbsp;");

} else if ($aClean['sSubject'] && $aClean['sMessage'])
{
    $sSubjectRe = $aClean['sSubject'];
    if(substr($sSubjectRe, 0, 4) != "Re: ")
        $sSubjectRe = "Re: $sSubjectRe";

    $sSubjectRe = urlencode($sSubjectRe);

    $sMsg = "The following message was sent to you from $oUser->sRealname ";
    $sMsg .= "through the Wine AppDB contact form.\nTo Reply, visit ";
    $sMsg .= APPDB_ROOT."contact.php?iRecipientId=$oUser->iUserId&sSubject=";
    $sMsg .= $sSubjectRe."\n\n";
    $sMsg .= $aClean['sMessage'];

    mail_appdb($oRecipient->sEmail, $aClean['sSubject'], $sMsg);

    util_redirect_and_exit(BASE."index.php");
}

?> 
