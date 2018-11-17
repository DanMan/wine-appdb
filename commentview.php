<?php
/************************************************************/
/* view comments                                            */
/*                                                          */
/* script expects appId, versionId and threadId as argument */
/************************************************************/

/*
 * application environment
 */
require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/comment.php");

$iVersionId = getInput('iVersionId', $aClean);
$iThreadId = getInput('iThreadId', $aClean);

if(!$iVersionId)
    util_show_error_page_and_exit('No versionId defined');

apidb_header("Comments");

Comment::view_app_comments($iVersionId, $iThreadId);

apidb_footer();

