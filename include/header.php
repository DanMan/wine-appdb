<?php
/*********************************/
/* Application Database - Header */
/*********************************/
?><!doctype html>
<html lang="en">
<head>
    <title>WineHQ <?php echo $title; ?></title>

    <meta charset="utf-8">
    <meta name="description" content="Open Source Software for running Windows applications on other operating systems.">
    <meta name="keywords" content="windows, linux, macintosh, solaris, freebsd">
    <meta name="robots" content="index, follow">
    <meta name="copyright" content="Copyright WineHQ.org All Rights Reserved.">
    <meta name="language" content="English">
    <meta name="revisit-after" content="1">

    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.1/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css" type="text/css" media="all">
    <link rel="stylesheet" href="<?php echo BASE; ?>styles.css" type="text/css" media="all">

    <script src="https://code.jquery.com/jquery-2.2.3.min.js" type="text/javascript"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js" type="text/javascript"></script>
    <script src="<?php echo BASE; ?>utils.js"></script>

    <link rel="shortcut icon" type="image/png" href="<?php echo BASE; ?>images/winehq_logo_16.png">
    <link title="AppDB" type="application/opensearchdescription+xml" rel="search" href="<?php echo BASE; ?>opensearch.xml">
</head>
<body>

<nav>
    <div id="whq-tabs">
        <div class="whq-tabs-menu">&#9776;</div>
        <ul>
            <li><a href="https://www.winehq.org/">WineHQ</a></li>
            <li><a href="https://wiki.winehq.org/">Wiki</a></li>
            <li class="s"><a href="https://appdb.winehq.org/">AppDB</a></li>
            <li><a href="https://bugs.winehq.org/">Bugzilla</a></li>
            <li><a href="https://forums.winehq.org/">Forums</a></li>
        </ul>
    </div>
    <div class="clear"></div>
</nav>

<div id="whq-logo-glass"><a href="https://www.winehq.org/"><img src="https://www.winehq.org/images/winehq_logo_glass.png" alt=""></a></div>
<div id="whq-logo-text"><a href="https://www.winehq.org/"><img src="https://www.winehq.org/images/winehq_logo_text.png" alt="WineHQ" title="WineHQ"></a></div>

<div id="whq-search_box">
    <form action="https://www.winehq.org/search" id="cse-search-box">
        <div class="input-group input-group-sm">
            <span class="input-group-addon"><span class="glyphicon glyphicon-search"></span></span>
            <input type="text" name="q" size="20" id="searchInput" class="form-control">
        </div>
    </form>
</div>

<?php

// Display Status Messages
$GLOBALS['session']->dumpmsgbuffer();
if (is_array($GLOBALS['session']->msg) and count($GLOBALS['session']->msg) > 0)
{
    echo "<div id=\"whq-alert\">\n";
    foreach ($GLOBALS['session']->msg as $msg)
    {
        if (empty($msg))
            continue;
        $msg_color = (!empty($msg['color']) ? " style=\"color: {$msg['color']};\"" : '');
        echo "<p{$msg_color}><i class=\"fa fa-exclamation-circle\"></i> {$msg['msg']}</p>\n";
        unset($msg_color);
    }
    echo "</div>\n";
}

// Display Sidebar
global $_APPDB_sidebar_func_list;
echo "<div id=\"sidebar\">\n";

// TURN on GLOBAL ADMIN MENU
if ($_SESSION['current']->hasPriv("admin"))
{
    include(BASE."include/sidebar_admin.php");
    apidb_sidebar_add("global_admin_menu");
}
else if ($_SESSION['current']->isMaintainer())
{
    /* if the user maintains anything, add their menus */
    include(BASE."include/sidebar_maintainer_admin.php");
    apidb_sidebar_add("global_maintainer_admin_menu");
}

// Login Menu
include(BASE."include/sidebar_login.php");
apidb_sidebar_add("global_sidebar_login");

// Main Menu
include(BASE."include/sidebar.php");
apidb_sidebar_add("global_sidebar_menu");

// LOOP and display menus
for($i = 0; $i < sizeof($_APPDB_sidebar_func_list); $i++)
{
    $func = $_APPDB_sidebar_func_list[$i];
    $func();
}
echo "</div>\n";

?>

<div id="whq-page-body">
<!-- Start Content -->
