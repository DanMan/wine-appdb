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
    <link rel="stylesheet" href="<?php echo BASE; ?>styles.css" type="text/css" media="screen">
    <link rel="stylesheet" href="<?php echo BASE; ?>apidb.css" type="text/css">
    <link rel="stylesheet" href="<?php echo BASE; ?>application.css" type="text/css">

    <script src="https://code.jquery.com/jquery-2.2.3.min.js" type="text/javascript"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js" type="text/javascript"></script>
    <script src="<?php echo BASE; ?>utils.js"></script>

    <link rel="shortcut icon" type="image/png" href="<?php echo BASE; ?>images/winehq_logo_16.png">
    <link title="AppDB" type="application/opensearchdescription+xml" rel="search" href="<?php echo BASE; ?>opensearch.xml">
</head>
<body>

<div id="logo_glass"><a href="<?php echo BASE; ?>"><img src="<?php echo BASE; ?>images/winehq_logo_glass_sm.png" alt=""></a></div>
<div id="logo_text"><a href="<?php echo BASE; ?>"><img src="<?php echo BASE; ?>images/winehq_logo_text.png" alt="WineHQ" title="WineHQ"></a></div>

<div id="logo_blurb"><?php echo preg_replace("/^ - /", "", $title); ?></div>

<div id="search_box">
  <form action="//www.winehq.org/search" id="cse-search-box" style="margin: 0; padding: 0;">
    <span style="color: #ffffff;">Search:</span> <input type="text" name="q" size="20">
  </form>
</div>

<div id="tabs">
    <ul>
        <li><a href="//www.winehq.org/">WineHQ</a></li>
        <li><a href="http://wiki.winehq.org/">Wiki</a></li>
        <li class="s"><a href="//appdb.winehq.org/">AppDB</a></li>
        <li><a href="//bugs.winehq.org/">Bugzilla</a></li>
        <li><a href="//forum.winehq.org/">Forums</a></li>
    </ul>
</div>

<div id="main_content">

  <div class="rbox">
  <b class="rtop"><b class="r1"></b><b class="r2"></b><b class="r3"></b><b class="r4"></b></b>
    <div class="content" style="padding: 20px 20px 10px 80px">
    <!-- Start Content -->
