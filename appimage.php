<?php
/**
 * Shows a thumbnail or a full size screenshot.
 *
 * Mandatory parameters:
 *  - iId, image identifier
 * 
 * Optional parameters:
 *  - bThumbnail, "true" if we want to see a thumbnail, "false" otherwise
 *  - sREQUEST_METHOD
 * 
 * TODO:
 *  - rename and document sREQUEST_METHOD
 *  - replace iId with iScreenshotId
 */

/**
 *  parse all the date formats required by HTTP 1.1 into PHP time values
 */
function pHttpDate($sDate) {
   $iDate = strtotime($sDate);
   if ($iDate != -1) return $iDate;
        /* the RFC also requires asctime() format... */
   $aTs = strptime($sDate,"%a %b  %e %H:%M:%S %Y");
   $iDate = gmmktime($aTs[2],$aTs[1],$aTs[0],$aTs[4],$aTs[3],$aTs[5],0);
   return $iDate;
}

// application environment
require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/screenshot.php");

// an image doesn't have a link, so a cookie makes no sense
header("Set-Cookie: ");
header("Pragma: ");

// is the user supposed to be viewing this image ?
if(!$_SESSION['current']->canViewImage($aClean['iId']))
    util_show_error_page_and_exit("Insufficient privileges.");

$oScreenshot = new Screenshot($aClean['iId']);
$fImage = fopen(appdb_fullpath("data/screenshots/".$oScreenshot->sUrl), "rb");

/* if we can open the image we should get its last modified time and read */
/* a few bytes from its header information and close it */
if($fImage)
{
   $fstat_val = fstat($fImage);
   $iModTime = $fstat_val['mtime'];
   $sMagic = fread($fImage, 8); /* read 8 bytes from the header, that lets us idenfity the type of
                                 image without loading it */
   fclose($fImage); /* don't leave the fopened image open */
}

/* identify what kind of image this is, if we can't identify it */
/* we should report that its a bad image */
if (strcmp("\x89PNG\r\n\x1A\n", $sMagic)==0) 
{
    header("Content-Type: image/png");
} else if (preg_match("^\xD8\xFF^", $sMagic)) {
    header("Content-Type: image/jpeg");
} else {
    header("500 Bad image format");
    exit;
}

header("Cache-Control: public");
header("Expires: ");

/* if the browser is asking if the file was modified since a particular date */
/* and the date is the same that the file was modified, then we can report */
/* that the file wasn't modified, the browser can used the cached image */
if (isset($iModTime)) {
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $iModTime == pHttpDate($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        header("HTTP/1.0 304 Not Modified");
        exit;
    }
    header("Last-Modified: " . gmdate("D, d M Y H:i:s", $iModTime) . " GMT");
}
if(!$aClean['bThumbnail'])
    $oScreenshot->output_screenshot(false);
else
    $oScreenshot->output_screenshot(true);


