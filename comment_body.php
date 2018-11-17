<?php
/**
 * Ajax Loader for Loading an Inline Comment
 * ties to handler in utils.js
 */

require("path.php");
require(BASE."include/incl.php");
require_once(BASE."include/comment.php");

Comment::view_comment_body($aClean['iCommentId']);


