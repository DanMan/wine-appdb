<?php
/**
 * User's role and preferences editor.
 *
 * Optional parameters:
 *  - iUserId, user identifier (when an administrator edits another user)
 *  - iLimit
 *  - sOrderBy
 *  - sUserPassword, new password
 *  - sUserPassword2, new password confirmation
 *  - sUserEmail, e-mail address
 *  - sUserRealname, user's real name
 *  - sWineRelease, user's Wine release
 *  - bIsAdmin, true if user is an administrator
 * 
 * TODO:
 *  - document iLimit and sOrderBy
 *  - replace sOrderBy with iOrderBy and use constants for each accepted value
 *  - add a field to prefs_list to flag the user level for the pref
 */

// application environment
require("path.php");
require(BASE."include/incl.php");

if(!$_SESSION['current']->isLoggedIn())
    util_show_error_page_and_exit("You must be logged in to edit preferences");

// we come from the administration to edit an user
if($_SESSION['current']->hasPriv("admin") &&
    isset($aClean['iUserId']) &&
    isset($aClean['iLimit']) &&
    isset($aClean['sOrderBy']) &&
    in_array($aClean['sOrderBy'],array("email","realname","created"))
)
{
    $oUser = new User($aClean['iUserId']);
} else
{
    $oUser = &$_SESSION['current'];
}

if(isset($aClean['sSubmit']) && $aClean['sSubmit'] == "Update")
{
    while(list($sKey, $sValue) = each($aClean))
    {
        /* if a parameter lacks 'pref_' at its head it isn't a */
        /* preference so skip over processing it */
        if(!ereg("^pref_(.+)$", $sKey, $arr))
            continue;
        $oUser->setPref($arr[1], $sValue);
    }
    
    /* make sure the user enters the same password twice */
    if ($aClean['sUserPassword'] == $aClean['sUserPassword2'])
    {
        $str_passwd = $aClean['sUserPassword'];
    }
    else if ($aClean['sUserPassword'])
    {
        addmsg("The Passwords you entered did not match.", "red");
    }

    /* update user data fields */
    $oUser->sEmail = $aClean['sUserEmail'];
    $oUser->sRealname = $aClean['sUserRealname'];
    $oUser->sWineRelease = $aClean['sWineRelease'];

    /* if the password was empty in both cases then skip updating the users password */
    if(!empty($str_passwd))
    {
        if(!$oUser->update_password($str_passwd))
            addmsg("Failed to update password", "red");
    }

    if ($oUser->update() == SUCCESS)
    {
        addmsg("Preferences Updated", "green");
        // we were managing an user, let's go back to the admin after
        // updating tha admin status
        if($oUser->iUserId == $aClean['iUserId'] &&
                $_SESSION['current']->hasPriv("admin"))
        {
            if($aClean['bIsAdmin'] == "true") 
                $oUser->addPriv("admin");
            else 
                $oUser->delPriv("admin");
            util_redirect_and_exit(BASE."admin/adminUsers.php?iUserId=".$oUser->iUserId.
                    "&amp;sSearch=".$aClean['sSearch']."&amp;iLimit=".$aClean['iLimit'].
                    "&amp;sOrderBy=".$aClean['sOrderBy']."&amp;sSubmit=true");
        }
    }
    else
    {
        addmsg("There was a problem updating your user info", "red");
    }
}

apidb_header('Preferences');

echo "<h1 class=\"whq-app-title\">Preferences for {$oUser->sRealname}</h1>\n";

echo "<form method=\"post\" action=\"preferences.php\">\n";

// build a table
$oTable = new Table();
$oTable->SetWidth("100%");
$oTable->SetClass("whq-table");

// user account form
$aTableRows = array();
$oTableRow = new TableRow();
$oTableRow->AddTextCell("&nbsp; Email Address");
$oTableRow->AddTextCell('<input type="text" name="sUserEmail" value="'.$oUser->sEmail.'" class="form-control">');
$aTableRows[] = $oTableRow;
$oTableRow = new TableRow();
$oTableRow->AddTextCell("&nbsp; Password");
$oTableRow->AddTextCell('<input type="password" name="sUserPassword" class="form-control">');
$aTableRows[] = $oTableRow;
$oTableRow = new TableRow();
$oTableRow->AddTextCell("&nbsp; Password (again)");
$oTableRow->AddTextCell('<input type="password" name="sUserPassword2" class="form-control">');
$aTableRows[] = $oTableRow;
$oTableRow = new TableRow();
$oTableRow->AddTextCell("&nbsp; Real Name");
$oTableRow->AddTextCell('<input type="text" name="sUserRealname" value="'.$oUser->sRealname.'" class="form-control">');
$aTableRows[] = $oTableRow;
foreach($aTableRows as $oTableRow)
{
    $oTable->AddRow($oTableRow);
}
$aTableRows = array();

// Edit admin privilege
$sAdminChecked = "";
if($oUser->hasPriv("admin"))
    $sAdminChecked = 'checked="true"';
if($_SESSION['current']->hasPriv("admin"))
{
    $oTableRow = new TableRow();
    $oTableRow->AddTextCell("&nbsp; Administrator");
    $oTableRow->AddTextCell("<input type=\"checkbox\"".
                          " name=\"bIsAdmin\" value=\"true\" ".
                          "$sAdminChecked>");

    $aTableRows[] = $oTableRow;
}

// wine version
$oTableRow = new TableRow();
$oTableRow->AddTextCell("&nbsp; Wine version");
$sBugzillaVersionList = make_bugzilla_version_list("sWineRelease",
                                                   $oUser->sWineRelease);
if (!empty($sBugzillaVersionList))
{
    $oTableRow->AddCell(new TableCell($sBugzillaVersionList));
    $aTableRows[] = $oTableRow;
}
foreach($aTableRows as $oTableRow)
{
     $oTable->AddRow($oTableRow);
}

// user preferences - only editable by user, not admin
if( !isset($aClean['iUserId']) || $oUser->iUserId != $aClean['iUserId'])
{
    $aTableRows = array();
    $hResult = query_parameters("SELECT * FROM prefs_list ORDER BY id");
    while($hResult && $r = query_fetch_object($hResult))
    {
            // skip admin options
            if(!$_SESSION['current']->hasPriv("admin"))
            {
                if($r->name == "debug")
                    continue;
            }

            /* Check if the permission only applies to a specific group */
            if($r->show_for_group && !$_SESSION['current']->hasPriv($r->show_for_group))
                continue;

            $input = html_select("pref_$r->name", explode('|', $r->value_list), 
                                 $oUser->getpref($r->name, $r->def_value));

            $oTableRow = new TableRow();
            $oTableCell = new TableCell("&nbsp; $r->description");
            $oTableRow->AddCell($oTableCell);
            $oTableCell = new TableCell($input);
            $oTableRow->AddCell($oTableCell);

            $aTableRows[] = $oTableRow;
    }

    foreach($aTableRows as $oTableRow)
    {
       $oTable->AddRow($oTableRow);
    }
}
echo $oTable->GetString();

echo "<p><button type=\"submit\" name='sSubmit' value=\"Update\" class=\"btn btn-default\"><i class=\"fa fa-save\"></i> Save Preferences</button></p>\n";

// if we manage another user we give the parameters to go back to the admin
if( isset($aClean['iUserId']) && $oUser->iUserId == $aClean['iUserId'])
{
    echo "<input type=\"hidden\" name=\"iLimit\" value=\"".$aClean['iLimit']."\">\n";
    echo "<input type=\"hidden\" name=\"sOrderBy\" value=\"".$aClean['sOrderBy']."\">\n";
    echo "<input type=\"hidden\" name=\"sSearch\" value=\"".$aClean['sSearch']."\">\n";
    echo "<input type=\"hidden\" name=\"iUserId\" value=\"".$aClean['iUserId']."\">\n";
}

echo "</form>\n";

apidb_footer();

