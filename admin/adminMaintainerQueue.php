<?php
/********************************************************/
/* code to View and approve new application maintainers */
/********************************************************/

include("path.php");
require(BASE."include/incl.php");
require(BASE."include/tableve.php");
require(BASE."include/category.php");
require(BASE."include/maintainer.php");
require(BASE."include/application.php");
require(BASE."include/mail.php");

$aClean = array(); //array of filtered user input

$aClean['sub'] = makeSafe( $_REQUEST['sub'] );
$aClean['queueId'] = makeSafe( $_REQUEST['queueId'] );
$aClean['add'] = makeSafe( $_REQUEST['add'] );
$aClean['reject'] = makeSafe( $_REQUEST['reject'] );
$aClean['replyText'] = makeSafe( $_REQUEST['replyText'] );

if(!$_SESSION['current']->hasPriv("admin"))
{
    errorpage("Insufficient privileges.");
    exit;
}

if ($aClean['sub'])
{
    if ($aClean['queueId'])
    {
        //get data
        $sQuery = "SELECT queueId, appId, versionId,".
                     "userId, maintainReason, superMaintainer,".
                     "UNIX_TIMESTAMP(submitTime) as submitTime ".
                     "FROM appMaintainerQueue WHERE queueId = ".$aClean['queueId'].";";
        $hResult = query_appdb($sQuery);
        $oRow = mysql_fetch_object($hResult);
        $oUser = new User($oRow->userId);
        mysql_free_result($hResult);
    }
    else
    {
        //error no Id!
        errorpage("<p><b>QueueId Not Found!</b></p>");
    }

    //process according to which request was submitted and optionally the sub flag
    if (!$aClean['add'] && !$aClean['reject'] && $aClean['queueId'])
    {
        apidb_header("Admin Maintainer Queue");
        echo '<form name="qform" action="adminMaintainerQueue.php" method="post" enctype="multipart/form-data">',"\n";

        $x = new TableVE("view");

        //help
        echo "<div align=center><table width='90%' border=0 cellpadding=3 cellspacing=0><tr><td>\n\n";
        echo "Please enter an accurate and personalized reply anytime a maintainer request is rejected.\n";
        echo "Its not polite to reject someones attempt at trying to help out without explaining why.\n";
        echo "</td></tr></table></div>\n\n";    

        //view application details
        echo html_frame_start("New Maintainer Form",600,"",0);
        echo "<table width='100%' border=0 cellpadding=2 cellspacing=0>\n";

        // Show the other maintainers of this application, if there are any
        echo '<tr valign=top><td class=color0><b>Other maintainers of this app:</b></td>',"\n";

        $foundMaintainers = false;

        $firstDisplay = true; /* if false we need to fix up table rows appropriately */

        $other_users = getMaintainersUserIdsFromAppIdVersionId($oRow->versionId);
        if($other_users)
        {
            $foundMaintainers = true;
            while(list($index, $userIdValue) = each($other_users))
            {
                $oUser = new User($userIdValue);
                if($firstDisplay)
                {
                    echo "<td>".$oUser->sRealname."</td></tr>\n";
                    $firstDisplay = false;
                } else
                {
                    echo "<tr><td class=\"color0\"></td><td>".$oUser->sRealname."</td></tr>\n";
                }
            }
        }

        $other_users = getSuperMaintainersUserIdsFromAppId($oRow->appId);
        if($other_users)
        {
            $foundMaintainers = true;
            while(list($index, $userIdValue) = each($other_users))
            {
                $oUser = new User($userIdValue);
                if($firstDisplay)
                {
                    echo "<td>".$oUser->sRealname."*</td></tr>\n";
                    $firstDisplay = false;
                } else
                {
                    echo "<tr><td class=\"color0\"></td><td>".$oUser->sRealname."*</td></tr>\n";
                }
            }
        }

        if(!$foundMaintainers)
        {
            echo "<td>No other maintainers</td></tr>\n";
        }

        // Show which other apps the user maintains
        echo '<tr valign="top"><td class="color0"><b>This user also maintains these apps:</b></td>',"\n";

        $firstDisplay = true;
        $other_apps = getAppsFromUserId($oRow->userId);
        if($other_apps)
        {
            while(list($index, list($appIdOther, $versionIdOther, $superMaintainerOther)) = each($other_apps))
            {
                $oApp = new Application($appIdOther);
                $oVersion = new Version($versionIdOther);
                if($firstDisplay)
                {
                    $firstDisplay = false;
                    if($superMaintainerOther)
                        echo "<td>".$oApp->sName."*</td></tr>\n";
                    else
                        echo "<td>".$oApp->sName." ".$oVersion->sName."</td></tr>\n";
                } else
                {
                    if($superMaintainerOther)
                        echo "<td class=color0></td><td>".$oApp->sName."*</td></tr>\n";
                    else
                        echo "<td class=color0></td><td>".$oApp->sName." ".$oVersion->sName."</td></tr>\n";
                }
            }
        } else
        {
            echo "<td>User maintains no other applications</td></tr>\n";
        }

        $oApp = new Application($oRow->appId);
        $oVersion = new Version($oRow->versionId);

        //app name
        echo '<tr valign=top><td class=color0><b>App Name</b></td>',"\n";
        echo "<td>".$oApp->sName."</td></tr>\n";

        //version
        echo '<tr valign=top><td class=color0><b>App Version</b></td>',"\n";
        echo "<td>".$oVersion->sName."</td></tr>\n";
         
        //maintainReason
        echo '<tr valign=top><td class=color0><b>Maintainer request reason</b></td>',"\n";
        echo '<td><textarea name="maintainReason" rows=10 cols=35>'.$oRow->maintainReason.'</textarea></td></tr>',"\n";

        //email response
        echo '<tr valign=top><td class=color0><b>Email reply</b></td>',"\n";
        echo "<td><textarea name='replyText' rows=10 cols=35>Enter a personalized reason for acceptance or rejection of the users maintainer request here</textarea></td></tr>\n";

        /* Add button */
        echo '<tr valign=top><td class=color3 align=center colspan=2>' ,"\n";
        echo '<input type=submit name=add value=" Add maintainer to this application " class=button /> </td></tr>',"\n";

        /* Reject button */
        echo '<tr valign=top><td class=color3 align=center colspan=2>' ,"\n";
        echo '<input type=submit name=reject value=" Reject this request " class=button /></td></tr>',"\n";

        echo '</table>',"\n";
        echo '<input type=hidden name="sub" value="inside_form" />',"\n"; 
        echo '<input type=hidden name="queueId" value="'.$aClean['queueId'].'" />',"\n";  

        echo html_frame_end("&nbsp;");
        echo html_back_link(1,'adminMaintainerQueue.php');
        echo "</form>";
        apidb_footer();
        exit;

    }
    else if ($aClean['add'] && $aClean['queueId'])
    {
        /* create a new user object for the maintainer */
        $maintainerUser = new User($oRow->userId);

        /* add the user as a maintainer and return the statusMessage */
        $statusMessage = $maintainerUser->addAsMaintainer($oRow->appId, $oRow->versionId,
                                                          $oRow->superMaintainer,
                                                          $aClean['queueId']);
        //done
        addmsg("<p><b>$statusMessage</b></p>", 'green');
    }
    else if (($aClean['reject'] || ($aClean['sub'] == 'reject')) && $aClean['queueId'])
    {
       $sEmail = $oUser->sEmail;
       if ($sEmail)
       {
           $oApp = new Application($oRow->appId);
           $oVersion = new Version($oRow->versionId);
           $sSubject =  "Application Maintainer Request Report";
           $sMsg  = "Your application to be the maintainer of ".$oApp->sName." ".$oVersion->sName." was rejected. ";
           $sMsg .= $aClean['replyText'];
           $sMsg .= "";
           $sMsg .= "-The AppDB admins\n";
                
           mail_appdb($sEmail, $sSubject ,$sMsg);
       }

       //delete main item
       $sQuery = "DELETE from appMaintainerQueue where queueId = ".$aClean['queueId'].";";
       $hResult = query_appdb($sQuery,"unable to delete selected maintainer application");
       echo html_frame_start("Delete maintainer application",400,"",0);
       if($hResult)
       {
           //success
           echo "<p>Maintainer application was successfully deleted from the Queue.</p>\n";
       }
       echo html_frame_end("&nbsp;");
       echo html_back_link(1,'adminMaintainerQueue.php');
    }
    else
    {
        //error no sub!
        addmsg('<p><b>Internal Routine Not Found!</b></p>', 'red');        
    }
}
/* display the list of all outstanding maintainer requests */
{
    apidb_header("Admin Maintainer Queue");
    echo '<form name="qform" action="adminMaintainerQueue.php" method="post" enctype="multipart/form-data">',"\n";

    //get available maintainers
    $sQuery = "SELECT queueId, appId, versionId,".
                     "userId, maintainReason,".
                     "superMaintainer,".
                     "submitTime as submitTime ".
                     "from appMaintainerQueue;";
    $hResult = query_appdb($sQuery);

    if(!$hResult || !mysql_num_rows($hResult))
    {
         //no apps in queue
        echo html_frame_start("","90%");
        echo '<p><b>The Maintainer Queue is empty.</b></p>',"\n";
        echo '<p>There is nothing for you to do. Check back later.</p>',"\n";        
        echo html_frame_end("&nbsp;");         
    }
    else
    {
        //help
        echo "<div align=center><table width='90%' border=0 cellpadding=3 cellspacing=0><tr><td>\n\n";
        echo "<p>This is a list of users that are asking to become application maintainers.\n";
        echo "Please read carefully the reasons they give for wanting to be an application maintainer.\n";
        echo "</td></tr></table></div>\n\n";
    
        //show applist
        echo html_frame_start("","90%","",0);
        echo "<table width='100%' border=0 cellpadding=3 cellspacing=0>\n\n";
        
        echo "<tr class=color4>\n";
        echo "    <td>Submission Date</td>\n";
        echo "    <td>Application Name</td>\n";
        echo "    <td>Version</td>\n";
        echo "    <td>Super maintainer?</td>\n";
        echo "    <td>Submitter</td>\n";
        echo "    <td>Action</td>\n";
        echo "</tr>\n\n";
        
        $c = 1;
        while($oRow = mysql_fetch_object($hResult))
        {
            $oUser = new User($oRow->userId);
            $oApp = new Application($oRow->appId);
            $oVersion = new Version($oRow->versionId);
            if ($c % 2 == 1) { $bgcolor = 'color0'; } else { $bgcolor = 'color1'; }
            echo "<tr class=$bgcolor>\n";
            echo "    <td>".print_date(mysqldatetime_to_unixtimestamp($oRow->submitTime))." &nbsp;</td>\n";
            echo "    <td>".$oApp->sName."</td>\n";

            if($oRow->superMaintainer)
            {
                echo "<td>N/A</td>\n";
                echo "<td>Yes</td>\n";
            } else
            {
  	        echo "<td>".$oVersion->sName." &nbsp;</td>\n";
                echo "<td>No</td>\n";
            }

            echo "    <td><a href=\"mailto:".$oUser->sEmail."\">".$oUser->sRealname."</a></td>\n";
            echo "    <td>[<a href=\"adminMaintainerQueue.php?sub=view&queueId=$oRow->queueId\">answer</a>]</td>\n";
            echo "</tr>\n\n";
            $c++;
        }
        echo "</table>\n\n";
        echo html_frame_end("&nbsp;");
        echo "</form>";
        apidb_footer();

    }
        
}
?>
