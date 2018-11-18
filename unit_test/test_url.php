<?php

/* Unit tests for functions in include/url.php */

require_once("path.php");
require_once("test_common.php");

function test_url_update()
{
    test_start(__FUNCTION__);

    $bSuccess = true; // default to success until we detect failure

    /* Log in */
    $sTestUser = __FUNCTION__."@localhost.com";
    $sTestPassword = "password";
    if(!$oUser = create_and_login_user($sTestUser, $sTestPassword))
    {
        error("Received error instead of SUCCESS('".SUCCESS."').");
        return FALSE;
    }

    $iAppId = 655000;
    $iVersionId = 655000;

    $oUser->addPriv("admin");

    $oUrl = new Url();
    $oUrl->create("Bad description", "http://www.badurl.com/", $iVersionId, $iAppId, TRUE);

    $sDescriptionNew = "Good description";
    $sUrlNew = "http://www.goodurl.com/";
    $iAppIdNew = $iAppId + 1;
    $iVersionIdNew = $iVersionId + 1;

    $oUrl->update($sDescriptionNew, $sUrlNew, $iVersionIdNew, $iAppIdNew, TRUE);

    if($oUrl->sDescription != $sDescriptionNew)
    {
        error("Description is '$oUrl->sDescription' instead of '$sDescriptionNew'");
        $bSuccess = false;
    }

    if($oUrl->sUrl != $sUrlNew)
    {
        error("Url is '$oUrl->sUrl' instead of '$sUrlNew'");
        $bSuccess = false;
    }

    if($oUrl->iVersionId != $iVersionIdNew)
    {
        error("VersionId is '$oUrl->iVersionId' instead of '$iVersionIdNew'");
        $bSuccess = false;
    }

    if($oUrl->iAppId != $iAppIdNew)
    {
        error("AppId is '$oUrl->iAppId' instead of '$iAppIdNew'");
        $bSuccess = false;
    }

    $oUrl->delete(TRUE);
    $oUser->delete();

    return $bSuccess;
}

if(!test_url_update())
{
    echo "test_url_update() failed!\n";
    $bTestSuccess = false;
} else
{
    echo "test_url_update() passed\n";
}


