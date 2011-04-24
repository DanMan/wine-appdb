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
    while($oRow = mysql_fetch_object($hResult))
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
    while($oRow = mysql_fetch_object($hResult))
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
    while(($oRow = mysql_fetch_object($hResult)))
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
            $sFix = "INSERT INTO tags_NoteVersion_assignments (tagId,taggedId) VALUES('$iVersionId','$iNoteId')";
            echo "$sFix<br />";
        }

        echo '<br />';
    }
    

    // Notes shown for all versions
    $hResult = query_parameters("SELECT * FROM appNotes WHERE versionId = '?'", APPNOTE_SHOW_FOR_VERSIONS);

    echo '<br /><br />The following notes are set to show for all versions:<br />';
    while(($oRow = mysql_fetch_object($hResult)))
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
            $sFix = "INSERT INTO tags_NoteVersion_assignments (tagId,taggedId) VALUES('$iVersionId','$iNoteId')";
            echo "$sFix<br />";
        }

        echo '<br />';
    }

    // Notes shown for specific versions
    $hResult = query_parameters("SELECT * FROM appNotes WHERE versionId = '?'", APPNOTE_SHOW_FOR_SPECIFIC_VERSIONS);

    echo '<br /><br />The following notes are set to show for specific versions:<br />';
    while(($oRow = mysql_fetch_object($hResult)))
    {
        $oNote = new note(null, $oRow);
        $iNoteId = $oNote->objectGetId();
        $oApp = new Application($oNote->iAppId);
        echo 'ID: '.$oNote->objectGetId().'<br />';
        echo 'App: '.$oApp->objectMakeLink().'<br />';
        echo '<br />';
        
        $hResult = query_parameters("SELECT DISTINCT(versionId) FROM appNotes WHERE linkedWith = '?'", $oNote->objectGetId());
        
        while(($oRow = mysql_fetch_object($hResult)))
        {
            $iVersionId = $oRow->versionId;
            $sFix = "INSERT INTO tags_NoteVersion_assignments (tagId,taggedId) VALUES('$iVersionId','$iNoteId')";
            echo "$sFix<br />";
        }
    }

    // Notes shown for app
    $hResult = query_parameters("SELECT * FROM appNotes WHERE versionId = '?'", APPNOTE_SHOW_FOR_APP);

    echo '<br /><br />The following notes are set to show on app page:<br />';
    while(($oRow = mysql_fetch_object($hResult)))
    {
        $oNote = new note(null, $oRow);
        $oApp = new Application($oNote->iAppId);
        echo 'ID: '.$oNote->objectGetId().'<br />';
        echo 'App: '.$oApp->objectMakeLink().'<br />';
        echo '<br />';
    }
}

function showChoices()
{
    echo '<a href="admin.php?sAction=fixNoteLinks">Fix/Show note links</a><br />';
    echo '<a href="admin.php?sAction=updateAppMaintainerStates">Update application maintainer states</a><br />';
    echo '<a href="admin.php?sAction=updateVersionMaintainerStates">Update version maintainer states</a><br />';
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

    default:
        showChoices();
        break;
}

apidb_footer();

?>
