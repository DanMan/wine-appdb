<?php
require_once(BASE."include/config.php");

function mail_appdb($sEmailList,$sSubject,$sMsg)
{
    // NOTE: For AppDB developers: If email is disabled return from this function
    //  immediately. See include/config.php.sample for information
    if(defined("DISABLE_EMAIL"))
        return;

    $sHeaders  = "MIME-Version: 1.0\r\n";
    $sHeaders .= "From: AppDB <".APPDB_SENDER_EMAIL.">\r\n";
    $sHeaders .= "Reply-to: AppDB <".APPDB_SENDER_EMAIL.">\r\n";
    $sHeaders .= "X-Priority: 3\r\n";
    $sHeaders .= "X-Mailer: ".APPDB_OWNER." mailer\r\n";
    $sMsg  = trim(ereg_replace("\r\n","\n",$sMsg));
    $sMsg  = $sSubject."\n-------------------------------------------------------\n".$sMsg."\n\n";
    $sMsg .= "Best regards.\n";
    $sMsg .= "The AppDB team\n";
    $sMsg .= APPDB_ROOT."\n";
    $sMsg .= "\n\nIf you don't want to receive any other e-mail, please change your preferences:\n";
    $sMsg .= APPDB_ROOT."preferences.php\n";

    /* Print the message to the screen instead of sending it, if the PRINT_EMAIL
       option is set.  Output in purple to distinguish it from other messages */
    if(defined("PRINT_EMAIL"))
    {
        $sMsg = str_replace("\n", "<br />", $sMsg);
        addmsg("This mail would have been sent<br /><br />".
               "Subject: $sSubject<br /><br />".
               "Body:<br />$sMsg", "purple");
        return;
    }

    $bResult = mail(str_replace(" ",",",$sEmailList), "[AppDB] ".$sSubject, $sMsg, $sHeaders, "-f".APPDB_SENDER_EMAIL);
    if($bResult)
        addmsg("Message sent to: ".$sEmailList, "green");
    else 
        addmsg("Error while sending message to: ".$sEmailList, "red");
    return $bResult;
}
?>
