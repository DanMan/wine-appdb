<?php
/********************************/
/* code to submit a new comment */
/********************************/
    
/*
 * application environment
 */
include("path.php");
require(BASE."include/incl.php");
require(BASE."include/application.php");
require(BASE."include/mail.php");

// you must be logged in to submit comments
if(!$_SESSION['current']->isLoggedIn())
{
  apidb_header("Please login");
  echo "To submit a comment for an application you must be logged in. Please <a href=\"account.php?cmd=login\">login now</a> or create a <a href=\"account.php?cmd=new\">new account</a>.","\n";
  exit;
}

if(!is_numeric($_REQUEST['appId']))
{
  errorpage('Internal Database Access Error');
  exit;
}

if(!is_numeric($_REQUEST['versionId']))
{
  $_REQUEST['versionId'] = 0;
}

if(!is_numeric($_REQUEST['thread']))
{
  $_REQUEST['thread'] = 0;
}

############################
# ADDS COMMENT TO DATABASE #
############################
if(isset($_REQUEST['body']))
{
    $hostname = get_remote();
    
    // get current userid
    $userId = $_SESSION['current']->userid;

    $aInsert = compile_insert_string(array( 'parentId' => $_REQUEST['thread'],
                                            'appId' => $_REQUEST['appId'],
                                            'versionId' => $_REQUEST['versionId'],
                                            'userId' => $userId,
                                            'hostname' => $hostname,
                                            'subject' => $_REQUEST['subject'],
                                            'body' => $_REQUEST['body']));
                                            
    $result = query_appdb("INSERT INTO appComments (`time`, {$aInsert['FIELDS']}) VALUES (NOW(), {$aInsert['VALUES']})");
    
    if ($result)
    {
        if (is_numeric($_REQUEST['originator']))
        {
            $oOriginator = new User($_REQUEST['originator']);
            if ($oOriginator->getPref("send_email"))
            {
                $sEmail = $oOriginator->sEmail;
                $sFullAppName = "Application: ".lookupAppName($_REQUEST['appId'])." Version: ".lookupVersionName($_REQUEST['appId'], $_REQUEST['versionId']);
                $sMsg  = APPDB_ROOT."appview.php?appId=".$_REQUEST['appId']."&versionId=".$_REQUEST['versionId'].".\n";
                $sMsg .= "\r\n";
                $sMsg .= $_SESSION['current']->realname." added comment to ".$sFullAppName."\r\n";
                $sMsg .= "\r\n";
                $sMsg .= "Subject: ".$subject."\r\n";
                $sMsg .= "\r\n";
                $sMsg .= $_REQUEST['body']."\r\n";

                mail_appdb($sEmail, $sFullAppName ,$sMsg);

                addmsg("Comment message sent to original poster", "green");                   
            }
        }
        $sEmail = get_notify_email_address_list($_REQUEST['appId'], $_REQUEST['versionId']);
        if($sEmail)
        {
            $sFullAppName = "Application: ".lookupAppName($_REQUEST['appId'])." Version: ".lookupVersionName($_REQUEST['appId'], $_REQUEST['versionId']);
            $sMsg  = APPDB_ROOT."appview.php?appId=".$_REQUEST['appId']."&versionId=".$_REQUEST['versionId'].".\r\n";
            $sMsg .= "\r\n";
            $sMsg .= $_SESSION['current']->realname." added comment to ".$fullAppName."\r\n";
            $sMsg .= "\r\n";
            $sMsg .= "Subject: ".$subject."\r\n";
            $sMsg .= "\r\n";
            $mssMsg .= $_REQUEST['body']."\r\n";

            mail_appdb($sEmail, $sFullAppName ,$sMsg);
        } 
        addmsg("New Comment Posted", "green");
    }
    redirect(apidb_fullurl("appview.php?appId=".$_REQUEST['appId']."&versionId=".$_REQUEST['versionId']));
}

################################
# USER WANTS TO SUBMIT COMMENT #
################################
else
{
  apidb_header("Add Comment");

  $mesTitle = "<b>Post New Comment</b>";

  if($_REQUEST['thread'] > 0)
  {
    $result = query_appdb("SELECT * FROM appComments WHERE commentId = ".$_REQUEST['thread']);
    $ob = mysql_fetch_object($result);
    if($ob)
    {
      $mesTitle = "<b>Replying To ...</b> $ob->subject\n";
      $originator = $ob->userId;
      echo html_frame_start($ob->subject,500);
      echo htmlify_urls($ob->body), "<br /><br />\n";
      echo html_frame_end();
    }
  }

  echo "<form method=\"POST\" action=\"addcomment.php\">\n";

  echo html_frame_start($mesTitle,500,"",0);
    
  echo '<table width="100%" border=0 cellpadding=0 cellspacing=1>',"\n";
  echo "<tr class=\"color0\"><td align=right><b>From:</b>&nbsp;</td>\n";
  echo "	<td>&nbsp;".$_SESSION['current']->realname."</td></tr>\n";
  echo "<tr class=\"color0\"><td align=right><b>Subject:</b>&nbsp;</td>\n";
  echo "	<td>&nbsp;<input type=\"text\" size=\"35\" name=\"subject\" value=\"".$_REQUEST['subject']."\" /> </td></tr>\n";
  echo "<tr class=\"color1\"><td colspan=2><textarea name=\"body\" cols=\"70\" rows=\"15\" wrap=\"virtual\">".$_REQUEST['body']."</textarea></td></tr>\n";
  echo "<tr class=\"color1\"><td colspan=2 align=center>\n";
  echo "  <input type=\"SUBMIT\" value=\"Post Comment\" class=\"button\" />\n";
  echo "  <input type=\"RESET\" value=\"Reset\" class=\"button\" />\n";
  echo "</td></tr>\n";
  echo "</table>\n";

  echo html_frame_end();

  echo "<input type=\"HIDDEN\" name=\"thread\" value=\"".$_REQUEST['thread']."\" />\n";
  echo "<input type=\"HIDDEN\" name=\"appId\" value=\"".$_REQUEST['appId']."\" />\n";
  echo "<input type=\"HIDDEN\" name=\"versionId\" value=\"".$_REQUEST['versionId']."\" />\n";
  if (isset($_REQUEST['thread']))
  {
    echo "<input type=\"HIDDEN\" name=\"originator\" value=\"$originator\" />\n";
  }
  echo "</form>";
}
?>

<p>&nbsp;</p>

<?
apidb_footer();
?>
