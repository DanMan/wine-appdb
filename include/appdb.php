<?php
/*************************************************/
/* Application Database Global Functions         */
/*************************************************/

/*
 * TODO:
 *   + replace all apidb_ calls with appdb_
 *   + addmsg should be removed, and calls replaced with session->addmsg
 */

/**
 * log debug message to debug console
 */
function appdb_debug($msg = "")
{
    if (APPDB_DEBUG == 1 or $GLOBALS['_APPDB_debug']) {
        $GLOBALS['_APPDB_debugLog'] .= "[".date("D M j G:i:s Y",time())."] ".$msg."\n";
    }
    return;
}

/**
 * output a debug string so it appears above all the webpage content
 * useful for seeing vars and objects in real time
 */
function appdb_dump($var = "")
{
    echo '<div class="appdb_dump"><xmp>';
    var_dump($var);
    echo "</xmp></div>\n";
}

/**
 * shutdown handler (FATAL errors)
 */
function appdb_error_shutdown()
{
    // get information about last error
    $error = error_get_last();
    // handle error if defined
    if (!empty($error))
    {
        // output error
        appdb_error_handler($error['type'], $error['message'], $error['file'], $error['line']);
    }
}

/**
 * error handler
 */
function appdb_error_handler($errno, $errstr, $errfile, $errline, $errcontext = null)
{
    global $config;

    // debug text
    $debug = "";

    // PHP error codes
    $err = array(
                 1    => 'E_ERROR',
                 2    => 'E_WARNING',
                 4    => 'E_PARSE',
                 8    => 'E_NOTICE',
                 16   => 'E_CORE_ERROR',
                 32   => 'E_CORE_WARNING',
                 64   => 'E_COMPILE_ERROR',
                 128  => 'E_COMPILE_WARNING',
                 256  => 'E_USER_ERROR',
                 512  => 'E_USER_WARNING',
                 1024 => 'E_USER_NOTICE',
                 2047 => 'E_ALL',
                 2048 => 'E_STRICT',
                 4096 => 'E_RECOVERABLE_ERROR',
                 8192 => 'E_DEPRECATED'
                );

    // clean up errfile
    $errfile = basename($errfile);

    // log PHP non-fatal code errors
    if ($errno == E_NOTICE or $errno == E_USER_NOTICE or $errno == E_USER_WARNING or $errno == E_STRICT or $errno == E_DEPRECATED)
        appdb_debug("error", "ERROR:[{$err[$errno]}] {$errfile}:{$errline} - {$errstr}");

    // do not fail on PHP STRICT or DEPRECIATED code on production site
    if ($errno and ($errno == E_STRICT or $errno == E_DEPRECATED) and !$config->test_mode)
        return;

    // don't exit on a notice or warning
    if ($errno == E_NOTICE or $errno == E_WARNING)
        return;

    // write to the error log
    error_log("[".date("D M j G:i:s Y",time())."] [".$err[$errno]."] ".$errfile.":".$errline." - ".$errstr."\n");

    // don't exit on a USER notice or warning, but log above
    if ($errno == E_USER_NOTICE or $errno == E_USER_WARNING)
        return;

    // show additional debug output
    $debug = "";
    if (APPDB_DEBUG === "1" and function_exists('debug_backtrace'))
    {
        // build context
        $ctx = '';
        if (!empty($errcontext))
        {
            foreach ($errcontext as $key => $value)
            {
                switch (gettype($value))
                {
                    case "string":
                        $ctx .= "[$key] => $value\n";
                        break;
                    default:
                        $ctx .= "[$key] => ".gettype($value)."\n";
                }
            }
            if (!empty($ctx))
                $debug = "<b>Context</b>: \n".$ctx."\n\n";
        }

        // build backtrace
        $backtrace = debug_backtrace();
        $output = "";
        foreach ($backtrace as $bt)
        {
           $args = '';
           if (isset($bt['args']))
           {
               foreach ($bt['args'] as $a)
               {
                   if (!empty($args))
                       $args .= ', ';
                   switch (gettype($a))
                   {
                       case 'integer':
                       case 'double':
                           $args .= $a;
                           break;
                       case 'string':
                           $a = $this->encode(substr($a, 0, 64)).((strlen($a) > 64) ? '...' : '');
                           $args .= "\"$a\"";
                           break;
                       case 'array':
                           $args .= 'Array('.count($a).')';
                           break;
                       case 'object':
                           $args .= 'Object('.get_class($a).')';
                           break;
                       case 'resource':
                           $args .= 'Resource('.strstr($a, '#').')';
                           break;
                       case 'boolean':
                           $args .= $a ? 'True' : 'False';
                           break;
                       case 'NULL':
                           $args .= 'Null';
                           break;
                       default:
                           $args .= 'Unknown';
                   }
               }
           }
           if (isset($bt['file']))
               $output .= "<b>file</b>: {$bt['file']}\n";
           if (isset($bt['line']))
               $output .= "<b>line</b>: {$bt['line']}\n";
           if (isset($bt['class']))
               $output .= "<b>call</b>: {$bt['class']}{$bt['type']}{$bt['function']}($args)\n";
           else if (isset($bt['type']))
               $output .= "<b>call</b>: {$bt['type']}{$bt['function']}($args)\n";
           else
               $output .= "call: {$bt['function']}($args)\n";
        }
        $debug .= "<b>Backtrace</b>: \n".$output."\n";
    }

    // display error page and exit
    appdb_clear_buffer();
    header("HTTP/1.1 500 Internal Server Error");
    include(BASE."include/header.php");
    echo "<h1>Ooops! Something has gone terribly wrong!</h1>\n";
    echo "<p>Our monkey train has derailed! Worry not, a webmaster gopher help army has been dispatched and is on the way.</p>";
    echo "<p>If this error continues to be a problem, please report it to us on our ";
    echo "<a href=\"https://forum.winehq.org/\">Forums</a></p>\n";
    echo "<hr>\n";
    echo "<h3>error details:</h3>\n";
    echo "<p><b>Error Message:</b> {$errstr}</p>\n";
    echo "<p><b>File:</b> {$errfile}:{$errline}</p>\n";
    if (!empty($debug)) {
        echo "<hr>\n";
        echo $debug;
    }
    include(BASE."include/footer.php");
    exit();
}

/**
 * clear the output buffer
 */
function appdb_clear_buffer()
{
    $status = ob_get_status();
    if (!empty($status) and !empty($status['level']))
    {
        for ($c = 0; $c < ($status['level'] + 1); $c++)
        {
            @eval("ob_end_clean();");
        }
    }
}

/**
 * return FULL url with docroot prepended
 */
function apidb_fullurl($path = "")
{
    return BASE.$path;
}

/**
 * return the full path of the calling script
 */
function appdb_fullpath($path)
{
    /* IE: we know this file is in /yyy/xxx/include, we want to get the /yyy/xxx 
    /* so we call dirname  on this file path twice */
    $fullpath = dirname(dirname(__FILE__))."//".$path;
    /* get rid of potential double slashes due to string concat */
    return str_replace("//", "/", $fullpath); 
}

/**
 * output the common apidb header
 *  title => sets the page title
 *  sHeaderCode => add additional code to <head> block
 */
function apidb_header($title = 0, $sHeaderCode = "")
{
    $realname = $_SESSION['current']->sRealname;

    // Set Page Title
    $page_title = $title;
    if ($title)
         $title = " - $title";

    // Display Header
    include(BASE."include/header.php");
}

/**
 * output the common apidb footer
 */
function apidb_footer()
{
    // log end time of script execution
    appdb_debug("Ending Log");
    appdb_debug("Script Execution time: ".round(microtime_float() - $GLOBALS['_APPDB_script_start_time'], 5)." seconds");

    // Display Footer
    if(!isset($header_disabled))
        include(BASE."include/footer.php");
}

/**
 * register a sidebar menu function
 * the supplied function is called when the sidebar is built
 */
function apidb_sidebar_add($funcname)
{
    global $_APPDB_sidebar_func_list;
    array_unshift($_APPDB_sidebar_func_list, $funcname);
}

/**
 * msgs will be displayed on the Next page view of the same user
 */
function addmsg($shText, $color = "black")
{
    $GLOBALS['session']->addmsg($shText, $color);
}

?>
