<?php
/*****************/
/* Login SideBar */
/*****************/

require_once(BASE."include/maintainer.php");
require_once(BASE."include/application.php");
require_once(BASE."include/user.php");
require_once(BASE."include/monitor.php");

function global_sidebar_login() {
  
    $g = new htmlmenu("User Menu");
    
    if($_SESSION['current']->isLoggedIn())
    {

        $g->add("Logout", BASE."account.php?sCmd=logout");
        $g->add("Preferences", BASE."preferences.php");
        
        /* if this user maintains any applications list them */
        /* in their sidebar */
        $apps_user_maintains = Maintainer::getAppsMaintained($_SESSION['current']);
        if($apps_user_maintains)
        {
            $g->add('Maintainership Overview', BASE.'objectManager.php?sClass=maintainerView&iId='.
                                               $_SESSION['current']->iUserId.'&sTitle=Your+Maintained+Apps');

            $g->addmisc("");
            $g->addmisc("You maintain:\n");

            while(list($index, list($appId, $versionId, $superMaintainer)) = each($apps_user_maintains))
            {
                $oApp = new application($appId);
                if($superMaintainer)
                    $g->add($oApp->sName."*", $oApp->objectMakeUrl(),"center");
                else
                {
                    $oVersion = new version($versionId);
                    $g->add(version::fullName($versionId),
                            $oVersion->objectMakeUrl(), "center");
                }
            }
        }

        /* Display the user's rejected applications */
        $iAppsRejected = application::objectGetEntriesCount('rejected');
        if($iAppsRejected && !$_SESSION['current']->hasPriv("admin"))
        {
          $g->add("Review Rejected Apps ($iAppsRejected)", BASE."objectManager.php?".
                  "sClass=application_queue&amp;bIsQueue=true&amp;bIsRejected=true&amp;sTitle=".
                  "Rejected+Applications", "center");
        }

        /* Display the user's rejected versions */
        $iVersionsRejected = version::objectGetEntriesCount('rejected');
        if($iVersionsRejected && !$_SESSION['current']->hasPriv("admin"))
        {
            $g->add("Review Rejected Versions ($iVersionsRejected)",
                    BASE."objectManager.php?sClass=version_queue&amp;bIsRejected=true".
                    "&amp;bIsQueue=true&amp;sTitle=Rejected+Versions", "center");
        }

        /* Display the user's rejected test results */
        $iTestDataRejected = testData::objectGetEntriesCount('rejected');
        if($iTestDataRejected && !$_SESSION['current']->hasPriv("admin"))
            $g->add("Review Rejected Test Results ($iTestDataRejected)",
                    BASE."objectManager.php?sClass=testData_queue&amp;".
                    "sAction=view&amp;bIsQueue=true&amp;bIsRejected=true&amp;sTitle=".
                    "Rejected+Test+Results", "center");

        $aMonitored = Monitor::getVersionsMonitored($_SESSION['current']);
        if($aMonitored)
        {
            $g->addmisc("");
            $g->addmisc("You monitor:\n");

            while(list($i, list($iAppId, $iVersionId)) = each($aMonitored))
            {
              $oVersion = new version($iVersionId);
              $g->add(version::fullName($iVersionId), $oVersion->objectMakeUrl(), "center");
            }
        }

        /* Display a link to the user's queued items,
           but not for admins, as theirs are auto-accepted */
        if(!$_SESSION['current']->hasPriv("admin"))
        {
            $g->addmisc("");
            $g->add("Your queued items", BASE."queueditems.php");
        }

    } else
    {
        $g->add("Log in", login_url());
        $g->add("Register", BASE."account.php?sCmd=new");
    }

    $g->done();

}

