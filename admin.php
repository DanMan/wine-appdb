<?php

require_once('path.php');
require_once('include/incl.php');
require_once('include/maintainer.php');

if(!$_SESSION['current']->hasPriv('admin'))
    util_show_error_page_and_exit("Only admins are allowed in here");

apidb_header('AppDB Control Center');

function updateAppMaintainerStates()
{
    $hResult = application::objectGetEntries('accepted');

    $i = 0;
    while($oRow = query_fetch_object($hResult))
    {
        $oApp = new application(null, $oRow);
        $oApp->updateMaintainerState();
        $i++;
    }

    echo "Updated $i entries";
}

function updateVersionMaintainerStates()
{
    $hResult = version::objectGetEntries('accepted');

    $i = 0;
    while($oRow = query_fetch_object($hResult))
    {
        $oVersion = new version(null, $oRow);
        $oVersion->updateMaintainerState();
        $i++;
    }

    echo "Updated $i entries";
}

function deleteInactiveMaintainers()
{
    $hResult = maintainer::getInactiveMaintainers(24);
    $i = 0;
    while($oRow = query_fetch_object($hResult))
    {
        $oMaintainer = new maintainer(null, $oRow);
        $oMaintainer->delete();
        $oUser = new User($oRow->userid);
        $sEmail = $oUser->sEmail;
        $sSubject  = "Maintainer status removed";
        $sMsg  = "Your maintainer status has been removed because you have not logged into the AppDB in over 24 months.";
        mail_appdb($sEmail, $sSubject, $sMsg);
        $i++;
    }     
        echo "Removed $i inactive maintainers.";
}

function fixNoteLinks()
{
    // Notes shown for app and all versions
    $hResult = query_parameters("SELECT * FROM appNotes WHERE versionId = '?'", APPNOTE_SHOW_FOR_ALL);

    echo 'The following notes are set to show for app and all versions:<br />';
    $iCount = 0;
    $iSkipped = 0;
    while(($oRow = query_fetch_object($hResult)))
    {
        $oNote = new note(null, $oRow);
        $iNoteId = $oNote->objectGetId();
        $oApp = new Application($oNote->iAppId);
        echo 'ID: '.$oNote->objectGetId().'<br />';
        echo 'App: '.$oApp->objectMakeLink().'<br />';
        
        $aVersions = $oApp->GetVersions(true);
        foreach($aVersions as $oVersion)
        {
            $iVersionId = $oVersion->objectGetId();

            $hResultTag = query_parameters("SELECT COUNT(*) as count FROM tags_NoteVersion_assignments WHERE tagId = '?' AND taggedId = '?'", $iVersionId, $oRow->noteId);

            $oRowTag = query_fetch_object($hResultTag);
        
            if(!$oRowTag->count)
            {
                query_parameters("INSERT INTO tags_NoteVersion_assignments (tagId,taggedId) VALUES('$iVersionId','$iNoteId')");
                $iCount++;
            } else
            {
                $iSkipped++;
            }
        }

        echo '<br />';
    }
    
    echo "<br /><br />Created $iCount tags ($iSkipped already existed)<br />";

    // Notes shown for all versions
    $hResult = query_parameters("SELECT * FROM appNotes WHERE versionId = '?'", APPNOTE_SHOW_FOR_VERSIONS);

    echo '<br /><br />The following notes are set to show for all versions:<br />';
    $iCount = 0;
    $iSkipped = 0;
    while(($oRow = query_fetch_object($hResult)))
    {
        $oNote = new note(null, $oRow);
        $iNoteId = $oNote->objectGetId();
        $oApp = new Application($oNote->iAppId);
        echo 'ID: '.$oNote->objectGetId().'<br />';
        echo 'App: '.$oApp->objectMakeLink().'<br />';

        $aVersions = $oApp->GetVersions(true);
        foreach($aVersions as $oVersion)
        {
            $iVersionId = $oVersion->objectGetId();

            $hResultTag = query_parameters("SELECT COUNT(*) as count FROM tags_NoteVersion_assignments WHERE tagId = '?' AND taggedId = '?'", $iVersionId, $oRow->noteId);

            $oRowTag = query_fetch_object($hResultTag);
        
            if(!$oRowTag->count)
            {
                query_parameters("INSERT INTO tags_NoteVersion_assignments (tagId,taggedId) VALUES('?','?')", $iVersionId, $iNoteId);
                $iCount++;
            } else
            {
                $iSkipped++;
            }
        }

        echo '<br />';
    }

    echo "<br /><br />Created $iCount tags ($iSkipped already existed)<br />";

    // Notes shown for specific versions
    $hResult = query_parameters("SELECT * FROM appNotes WHERE versionId = '?'", APPNOTE_SHOW_FOR_SPECIFIC_VERSIONS);

    echo '<br /><br />The following notes are set to show for specific versions:<br />';
    $iCount = 0;
    $iSkipped =0;
    while(($oRow = query_fetch_object($hResult)))
    {
        $oNote = new note(null, $oRow);
        $iNoteId = $oNote->objectGetId();
        $oApp = new Application($oNote->iAppId);
        echo 'ID: '.$oNote->objectGetId().'<br />';
        echo 'App: '.$oApp->objectMakeLink().'<br />';
        echo '<br />';
        
        $hResult2 = query_parameters("SELECT DISTINCT(versionId) FROM appNotes WHERE linkedWith = '?'", $oNote->objectGetId());
        
        while(($oRow2 = query_fetch_object($hResult2)))
        {
            $iVersionId = $oRow2->versionId;
            
            $hResultTag = query_parameters("SELECT COUNT(*) as count FROM tags_NoteVersion_assignments WHERE tagId = '?' AND taggedId = '?'", $iVersionId, $oRow->noteId);

            $oRowTag = query_fetch_object($hResultTag);
        
            if(!$oRowTag->count)
            {
                query_parameters("INSERT INTO tags_NoteVersion_assignments (tagId,taggedId) VALUES('?','?')", $iVersionId, $iNoteId);
                $iCount++;
            } else
            {
                $iSkipped++;
            }
        }
    }
    
    echo "<br /><br />Created $iCount tags ($iSkipped already existed)<br />";

    // Notes shown for app
    $hResult = query_parameters("SELECT * FROM appNotes WHERE versionId = '?'", APPNOTE_SHOW_FOR_APP);

    echo '<br /><br />The following notes are set to show on app page:<br />';
    $iCount = 0;
    while(($oRow = query_fetch_object($hResult)))
    {
        $oNote = new note(null, $oRow);
        $oApp = new Application($oNote->iAppId);
        echo 'ID: '.$oNote->objectGetId().'<br />';
        echo 'App: '.$oApp->objectMakeLink().'<br />';
        echo '<br />';
        $iCount++;
    }
    
    echo "<br />$iCount in total<br />";

    // Create links for ordinary notes
    echo "<br /><br />Creating tags for ordinary notes<br />";
    $hResult = query_parameters("SELECT * FROM appNotes WHERE versionId > '0' AND linkedWith = '0'");
    
    $iTagsCreated = 0;
    $iSkipped = 0;
    while(($oRow = query_fetch_object($hResult)))
    {
        $hResultTag = query_parameters("SELECT COUNT(*) as count FROM tags_NoteVersion_assignments WHERE tagId = '?' AND taggedId = '?'", $oRow->versionId, $oRow->noteId);

        $oRowTag = query_fetch_object($hResultTag);
        
        if(!$oRowTag->count)
        {
            query_parameters("INSERT INTO tags_NoteVersion_assignments (tagId,taggedId) VALUES('?','?')", $oRow->versionId, $oRow->noteId);
            $iTagsCreated++;
        } else
        {
            $iSkipped++;
        }
    }
    
    echo "Created $iTagsCreated note tags ($iSkipped already existed)<br />"; 
    
    
    echo "<br />Deleting note links<br />";
    $hResult = query_parameters("DELETE FROM appNotes WHERE linkedWith != '0'");
    echo "Deleted ".query_affected_rows()." links<br />";
    
}

function fixInvalidTestSubmitTime()
{
    $sQuery = "UPDATE testResults SET submitTime = testedDate WHERE submitTime = '0000-00-00 00:00:00'";
    $hResult = query_parameters($sQuery);
    
    echo "Updated ".query_affected_rows()." test reports with invalid submit times.<br>";
}

function deleteOrphanComments()
{
    $sQuery = "DELETE FROM appComments WHERE NOT EXISTS( SELECT appVersion.versionId FROM appVersion"; 
    $sQuery.= " WHERE appVersion.versionId = appComments.versionId )";
    $hResult = query_parameters($sQuery);
    
    echo "Deleted ".query_affected_rows()." orphan comments.<br>";
}

function deleteOrphanVersions()
{
    $sQuery = "DELETE FROM appVersion WHERE appId = 0 and state != 'deleted'"; 
    $hResult = query_parameters($sQuery);
    
    echo "Deleted ".query_affected_rows()." orphan versions.<br>";
}

function purgeRejectedDistributions()
{
    $sQuery = "DELETE FROM distributions WHERE state = 'deleted'";
    $hResult = query_parameters($sQuery);
    
    echo "Removed " .query_affected_rows()." rejected distributions from database.<br>";
}

function purgeRejectedVendors()
{
    $sQuery = "DELETE FROM vendor WHERE state = 'deleted'";
    $hResult = query_parameters($sQuery);
    
    echo "Removed " .query_affected_rows()." rejected vendors from database.<br>";
}

function deleteOldComments()
{
    $sQuery = "DELETE FROM appComments USING appComments, appVersion WHERE appComments.versionId = appVersion.versionId AND appVersion.hasMaintainer = 'false' AND appComments.time <= DATE_SUB(CURDATE(), INTERVAL '12' MONTH)";
    $hResult = query_parameters($sQuery);
    
    echo "Deleted " .query_affected_rows()." comments older than 12 months from unmaintained entries.<br>";
}

function deleteOldErrorLogs()
{
    $sQuery = "DELETE FROM error_log WHERE submitTime <= DATE_SUB(CURDATE(), INTERVAL '12' MONTH)";
    $hResult = query_parameters($sQuery);
    
    echo "Deleted " .query_affected_rows()." error log entries over 12 months old from database.<br>";
}

function updateVersionRatings()
{ 
    $hResult = version::objectGetEntries('accepted');

    $i = 0;
    while($oRow = query_fetch_object($hResult))
    {
        $oVersion = new version(null, $oRow);
        $oVersion->updateRatingInfo();
        $i++;
    }

    echo "Updated $i entries";
}

function viewAppdbAdmins()
{
    echo html_frame_start("AppDB Admins","90%","",0);
    echo "<table width='100%' border=0 cellpadding=3 cellspacing=0>\n\n";
    echo "<tr class=color1>\n";
    echo "<td>Real name</td>\n";
    echo "<td>Email</td>\n";
    echo "<td>Last connected</td>\n";
    echo "</tr>\n\n";
    
    $sQuery = "SELECT user_list.userid, realname, email, stamp FROM user_list, user_privs WHERE user_list.userid = user_privs.userid AND user_privs.priv = 'admin' ORDER BY realname";

    $hResult = query_parameters($sQuery);

    $i = 0;
    while($oRow = query_fetch_object($hResult))
    {
        $oUser = new User($oRow->userid);
        echo "<tr class=color0>";
        echo "<td>".$oUser->objectMakeLink()."</td>\n";
        echo "<td>".$oUser->sEmail."</td>\n";
        echo "<td>".$oUser->sStamp."</td>\n";
        $i++;
    }  
    echo "</table>";
    echo html_frame_end();   
        
    echo "Found $i entries. <br>";
}

function showChoices()
{
    echo '<div class="list-group">';
  
    echo '<a href="admin.php?sAction=fixNoteLinks" class="list-group-item"><h4>Fix/Show note links</h4></a>';
    
    echo '<a href="admin.php?sAction=fixInvalidTestSubmitTime" class="list-group-item"><h4>Fix Invalid Test Submission Times</h4></a>';

    echo '<a href="admin.php?sAction=updateAppMaintainerStates" class="list-group-item"><h4>Update application maintainer states</h4></a>';
 
    echo '<a href="admin.php?sAction=updateVersionMaintainerStates" class="list-group-item"><h4>Update version maintainer states</h4></a>';

    echo '<a href="admin.php? sAction=deleteInactiveMaintainers" class="list-group-item"><h4>Delete inactive maintainers</h4></a>';
 
    echo '<a href="admin.php?sAction=deleteOrphanComments" class="list-group-item"><h4>Delete orphan comments</h4></a>';
 
    echo '<a href="admin.php?sAction=deleteOrphanVersions" class="list-group-item"><h4>Delete orphan versions</h4></a>';
 
    echo '<a href="admin.php?sAction=purgeRejectedDistributions" class="list-group-item"><h4>Purge rejected distributions</h4></a>';
 
    echo '<a href="admin.php?sAction=purgeRejectedVendors" class="list-group-item"><h4>Purge rejected vendors</h4></a>';
    
    echo '<a href="admin.php?sAction=deleteOldComments" class="list-group-item"><h4>Delete old comments</h4></a>';
    
    echo '<a href="admin.php?sAction=deleteOldErrorLogs" class="list-group-item"><h4>Delete old error logs</h4></a>';

    echo '<a href ="'.BASE.'objectManager.php?sClass=error_log&sTitle=View+Error_log" class="list-group-item"><h4>View Error log entries</h4></a>';
 
    echo '<a href="admin.php?sAction=updateVersionRatings" class="list-group-item"><h4>Update version ratings</h4></a>';

    echo '<a href="admin.php?sAction=viewAppdbAdmins" class="list-group-item"><h4>View AppDB admins</h4></a>';
    echo '</div>';   
}

switch(getInput('sAction', $aClean))
{   
    case 'updateAppMaintainerStates':
        updateAppMaintainerStates();
        break;

    case 'updateVersionMaintainerStates':
        updateVersionMaintainerStates();
        break;
        
    case 'deleteInactiveMaintainers';
        deleteInactiveMaintainers();
        break;

    case 'fixNoteLinks':
        fixNoteLinks();
        break;

    case 'fixInvalidTestSubmitTime';
        fixInvalidTestSubmitTime();
        break;
        
    case 'deleteOrphanComments':
        deleteOrphanComments();
        break;
        
    case 'deleteOrphanVersions':
        deleteOrphanVersions();
        break;  
        
    case 'purgeRejectedDistributions':
        purgeRejectedDistributions();
        break;
        
    case 'purgeRejectedVendors':
        purgeRejectedVendors();
        break; 
        
    case 'deleteOldComments':
        deleteOldComments();
        break;
        
    case 'deleteOldErrorLogs':
        deleteOldErrorLogs();
        break;
        
    case 'viewErrorLogEntries';
        viewErrorLogEntries();
        break;
    
    case 'updateVersionRatings':
        updateVersionRatings();
        break;
        
    case 'viewAppdbAdmins':
        viewAppdbAdmins();
        break;
        
        
     
    default:
        showChoices();
        break;
}

apidb_footer();

?>
