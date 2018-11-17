<?php

require_once("path.php");
require_once(BASE."/include/incl.php");

/**
 *  Page containing a form for sending e-mail
 *
 */

$oUser = new User($_SESSION['current']->iUserId);
$shError = '';

/* Restrict error to logged-in users */
if(!$oUser->isLoggedIn())
{
    login_form();
    exit;
}

$oRecipient = null;
$sRecipientText = '';
$iRecipientId = null;
$sRecipientGroup = getInput('sRecipientGroup', $aClean);
$sRecipients = '';

if($sRecipientGroup)
{
    if(!$oUser->hasPriv('admin'))
        util_show_error_page_and_exit("Only admins can do this");

    switch($sRecipientGroup)
    {
        case 'maintainers':
            $sRecipientText = 'all maintainers';
            $sRecipients = maintainer::getSubmitterEmails();
            if($sRecipients === FALSE)
            util_show_error_page_and_exit("Failed to get list of maintainers");
            break;
            
        case 'admins';
            $sRecipientText = 'all AppDB admins';
            $sRecipients = user::getAdminEmails();
            if($sRecipients === FALSE)
            util_show_error_page_and_exit("Failed to get list of admins");
            break;

        default:
            util_show_error_page_and_exit("Invalid recipient group");
    }
} else
{
    $oRecipient = new User($aClean['iRecipientId']);
    $iRecipientId = $oRecipient->iUserId;
    $sRecipients = $oRecipient->sEmail;

    if(!User::exists($oRecipient->sEmail))
        util_show_error_page_and_exit("User not found");

    $sRecipientText = $oRecipient->sRealname;
}

/* Check for errors */
if((!$aClean['sMessage'] || !$aClean['sSubject']) && $aClean['sSubmit'])
{
    $shError = "<font color=\"red\">Please enter both a subject and a ".
             "message.</font>";
    $aClean['sSubmit'] = "";
}

/* Display the feedback form if nothing else is specified */
if(!$aClean['sSubmit'])
{
    apidb_header("E-mail $sRecipientText");
    echo '&nbsp';
    echo html_frame_start("Composer",400,"",0);

    echo $shError;
    echo "<form action=\"".$_SERVER['PHP_SELF']."\" method=\"post\">";

    /* User manager */
    if($_SESSION['current']->hasPriv("admin") && $oRecipient)
    {
        echo "<p><a href=\"".BASE."preferences.php?iUserId=".
                $oRecipient->iUserId."&amp;sSearch=Administrator&amp;iLimit".
                "=100&amp;sOrderBy=email\">User manager</a></p>";
    }

    if($oRecipient)
    {
        echo "<p><a href=\"".BASE."objectManager.php?sClass=maintainerView&iId=".
             "{$oRecipient->iUserId}&sTitle=Maintained+Apps\">Maintained apps</a>";
    }

    echo "<p>E-mail $sRecipientText.</p>";

    $oTable = new Table();
    $oTable->SetWidth("100%");
    $oTable->SetBorder(0);
    $oTable->SetCellPadding(2);
    $oTable->SetCellSpacing(2);

    $oTableRow = new TableRow();
    $oTableRow->SetClass("color4");
    $oTableRow->AddTextCell("Subject");
    $oTableCell = new TableCell("<input type=\"text\" name=\"sSubject\" size=\"71\"".
                                " value=\"".getInput('sSubject', $aClean)."\">");
    $oTableRow->AddCell($oTableCell);
    $oTable->AddRow($oTableRow);

    $oTableRow = new TableRow();
    $oTableRow->SetClass("color4");
    $oTableCell = new TableCell("Message");
    $oTableCell->SetValign("top");
    $oTableRow->AddCell($oTableCell);
    $oTableCell = new TableCell("<textarea name=\"sMessage\" rows=\"15\" cols=\"60\">"
                                .getInput('sMessage', $aClean)."</textarea>");
    $oTableRow->AddCell($oTableCell);
    $oTable->AddRow($oTableRow);

    $oTableRow = new TableRow();
    $oTableRow->AddTextCell("");
    $oTableRow->AddTextCell("<input type=\"submit\" value=\"Submit\" name=\"sSubmit\">");
    $oTable->AddRow($oTableRow);

    // output the table
    echo $oTable->GetString();

    echo "<input type=\"hidden\" name=\"iRecipientId\" ".
    "value=\"$iRecipientId\">";

    echo "<input type=\"hidden\" name=\"sRecipientGroup\" ".
    "value=\"$sRecipientGroup\">";

    echo "</form>\n";

    echo html_frame_end("&nbsp;");

} else if ($aClean['sSubject'] && $aClean['sMessage'])
{
    if($oRecipient)
    {
        $sSubjectRe = $aClean['sSubject'];
        if(substr($sSubjectRe, 0, 4) != "Re: ")
            $sSubjectRe = "Re: $sSubjectRe";

        $sSubjectRe = urlencode($sSubjectRe);

        $sMsg = "The following message was sent to you from $oUser->sRealname ";
        $sMsg .= "through the Wine AppDB contact form.\nTo Reply, visit ";
        $sMsg .= APPDB_ROOT."contact.php?iRecipientId=$oUser->iUserId&amp;sSubject=";
        $sMsg .= $sSubjectRe."\n\n";
        $sMsg .= $aClean['sMessage'];
    } else
    {
        $sMsg = "The following message was sent to you by the AppDB admins:\n\n";
        $sMsg .= $aClean['sMessage'];
    }

    mail_appdb($sRecipients, '[PM] '.$aClean['sSubject'], $sMsg);

    util_redirect_and_exit(BASE."index.php");
}


