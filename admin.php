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

function showChoices()
{
    echo '<a href="admin.php?sAction=fixNoteLinks">Fix/Show note links</a><br />';
    echo '<a href="admin.php?sAction=updateAppMaintainerStates">Update application maintainer states</a><br />';
    echo '<a href="admin.php?sAction=updateVersionMaintainerStates">Update version maintainer states</a><br />';
    echo '<a href="admin.php?sAction=deleteOrphanComments">Delete Orphan Comments</a><br>';
    echo '<a href="admin.php?sAction=deleteOrphanVersions">Delete Orphan Versions</a><br>';   
}

switch(getInput('sAction', $aClean))
{
    case 'updateAppMaintainerStates':
        updateAppMaintainerStates();
        break;

    case 'updateVersionMaintainerStates':
        updateVersionMaintainerStates();
        break;

    case 'fixNoteLinks':
        fixNoteLinks();
        break;
        
    case 'deleteOrphanComments':
        deleteOrphanComments();
        break;
        
    case 'deleteOrphanVersions':
        deleteOrphanVersions();
        break;   
     
    default:
        showChoices();
        break;
}

apidb_footer();

?>
