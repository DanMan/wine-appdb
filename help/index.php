<?php
/*********************************************/
/* Application Database Documentation Center */
/*********************************************/

require("path.php");
require(BASE."include/"."incl.php");

$help_path = BASE."/help";

if(isset($aClean['sTopic']))
{
    display_help($aClean['sTopic']);
} else {
    display_index();    
}

function display_index ()
{
    global $help_path;

    apidb_header("Documentation Index");

    echo "<div class='default_container'>\n";

    echo "<p><b>Providing all the help you need 24x7</b><p><hr noshade>\n";

    echo "<ul>\n";

    // read dir
    $files = array();
    $d = opendir($help_path);
    while($entry = readdir($d))
    {
        array_push($files, $entry);
    }
    closedir($d);
    
    // sort dir
    sort($files);
    
    // display dir
    while (list($key,$file) = each($files))
    {
        if(!preg_match("(([^.]+).help)", $file, $aMatches))
            continue;

        $id    = $aMatches[1];
        $title = get_help_title("$help_path/$file");

        echo "  <li> <a href=\"".BASE."help?sTopic=$id\"  style=\"cursor: help\"> $title </a><p /></li>\n";
    }

    echo "</ul><hr noshade>\n";

    echo "<p>Need more help? Contact us at <a href='mailto:".APPDB_OWNER_EMAIL."'>".APPDB_OWNER_EMAIL."</a><p>\n";

    echo "</div>\n";
    
    apidb_footer();
}

function display_help ($topic)
{
    global $help_path;

    $file  = "$help_path/$topic.help";
    $title = get_help_title($file);

    if(! $title) {
	$title = "Help on $topic";
    }
    
    apidb_header($title);
    echo "<div class='default_container'>\n";
    if(file_exists($file)) {
        include($file);  
    } else {
        echo "<p><b> No help available on that topic </b><p>\n";
    }
    echo "</div>\n";
        
    apidb_footer();
}

function get_help_title ($file)
{
    $fp = @fopen($file, "r");
    if(!$fp)
        return null;

    $line = fgets($fp, 1024);
    if(!$line)
        return null;

    $line = trim($line);

    if(preg_match("/^<!--TITLE: (.+)-->$/i", $line, $aMatches))
    {
        return $aMatches[1];
    }
    return "Internal Error: missing title";
}

?>
