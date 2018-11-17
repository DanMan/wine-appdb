<?php
/*************************************************/
/* Main Include Library for Application Database */
/*************************************************/

/**
 * load required modules
 */
require_once(BASE."include/config.php");
require(BASE."include/appdb.php");
require(BASE."include/util.php");
require(BASE."include/user.php");
require(BASE."include/session.php");
require(BASE."include/htmlmenu.php");
require(BASE."include/html.php");
require(BASE."include/error_log.php");
require(BASE."include/query.php");
require(BASE."include/table.php");
require_once(BASE."include/objectManager.php");

/**
 * setup error handing
 */
set_error_handler('appdb_error_handler');
register_shutdown_function('appdb_error_shutdown');

/**
 * global vars
 */

// script execution start time
$_APPDB_script_start_time = microtime_float();

// sidebar function callbacks
$_APPDB_sidebar_func_list = array();

// enable debug flag
$_APPDB_debug = false;

// debug log
$_APPDB_debugLog = "";

/**
 * Init Session (stores user info in session)
 */

$session = new session("whq_appdb");
$session->register("current");
if(!isset($_SESSION['current']))
{
    $_SESSION['current'] = new User();
}

// include filter.php to filter all REQUEST input
require(BASE."include/filter.php");

/**
 * turn on debuging admin pref
 */

if($_SESSION['current']->showDebuggingInfos())
    $_APPDB_debug = true;

/**
 * Start Debug Log
 */
appdb_debug("Starting Debug Log");


