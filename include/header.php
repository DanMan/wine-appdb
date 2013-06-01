<?php
/*********************************/
/* Application Database - Header */
/*********************************/
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <title>WineHQ <?php echo $title; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta HTTP-EQUIV="Expires" CONTENT="Mon, 06 Jan 1990 00:00:01 GMT">
    <meta HTTP-EQUIV="Pragma" CONTENT="no-cache">
    <meta name="description" content="Open Source Software for running Windows applications on other operating systems.">
    <meta name="keywords" content="windows, linux, macintosh, solaris, freebsd">
    <meta name="robots" content="index, follow">
    <meta name="copyright" content="Copyright WineHQ.org All Rights Reserved.">
    <meta name="language" content="English">
    <meta name="revisit-after" content="1">
    <link rel="stylesheet" href="<?php echo BASE; ?>styles.css" type="text/css" media="screen">
    <script language="JavaScript" src="<?php echo BASE; ?>jquery.js" type="text/javascript"></script>
    <script language="JavaScript" src="<?php echo BASE; ?>utils.js" type="text/javascript"></script>
    <link rel="stylesheet" href="<?php echo BASE; ?>apidb.css" type="text/css">
    <link rel="stylesheet" href="<?php echo BASE; ?>application.css" type="text/css">
    <script type="text/javascript" 
    src="//ajax.googleapis.com/ajax/libs/prototype/1.6.0.2/prototype.js"></script>
    <script src="<?php echo BASE; ?>scripts.js" type="text/javascript"></script>
    <link rel="icon" type="image/png" href="<?php echo BASE; ?>images/winehq_logo_16.png">
    <link rel="shortcut icon" type="image/png" href="<?php echo BASE; ?>images/winehq_logo_16.png">
    <link title="AppDB" type="application/opensearchdescription+xml" rel="search" href="<?php echo BASE; ?>opensearch.xml">
</head>
<body>

<div id="logo_glass"><a href="<?php echo BASE; ?>"><img src="<?php echo BASE; ?>images/winehq_logo_glass_sm.png" alt=""></a></div>
<div id="logo_text"><a href="<?php echo BASE; ?>"><img src="<?php echo BASE; ?>images/winehq_logo_text.png" alt="WineHQ" title="WineHQ"></a></div>

<div id="logo_blurb"><?php echo preg_replace("/^ - /", "", $title); ?></div>

<div id="search_box">
  <form action="//www.winehq.org/search" id="cse-search-box" style="margin: 0; padding: 0;">
    <input type="hidden" name="cx" value="partner-pub-0971840239976722:w9sqbcsxtyf">
    <input type="hidden" name="cof" value="FORID:10">
    <input type="hidden" name="ie" value="UTF-8">
    <span style="color: #ffffff;">Search:</span> <input type="text" name="q" size="20">
  </form>
  <script type="text/javascript" src="//www.google.com/coop/cse/brand?form=cse-search-box&amp;lang=en"></script>
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
