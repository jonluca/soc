<?php
error_reporting(E_ALL | E_STRICT);
date_default_timezone_set("America/Los_Angeles");

require_once("models/classTextile.php");
require_once("models/soc1c.php");


$conf = new SOC_Configuration("SOC Help","soc1c.conf");
$soc_uri = $conf->get("soc_uri");
$soc_path = $conf->get("soc_path");
$filename = "";

if (! defined("hu")) {
    define('hu',$soc_uri."/");
}

echo<<<EOF
 <html>
 <head>
 <title>Schedule of Classes Web Services API</title>
 
 <!-- BEGIN default CSS and JS, always put CSS before JS, so that JS can load a
 dditional CSS -->
   <link rel="stylesheet" href="http://www.usc.edu/assets/css/default.css" type="
 text/css" media="screen" />
   <style type="text/css" media="all">@import url("$soc_uri/css/nav.css");</style
 >
   <style type="text/css" media="all">@import url("$soc_uri/css/cal_small.css");</style>
 <!-- END default CSS and JS, always put CSS before JS so that JS can load addi
 toinal CSS -->
 
 <!-- BEGIN Generic SOC CSS/JS -->
   <link rel="stylesheet" href="$soc_uri/css/base.css" type="text/css" />
   <link rel="stylesheet" href="$soc_uri/css/nav_base.css" type="text/css" />
   <link rel="stylesheet" href="$soc_uri/css/basic.css" type="text/css" media="all" />
 <!-- END Generic SOC CSS/JS -->  
 
 </head>
 <body>
 <!-- start usc monogram logo -->
 <!-- end usc monogram logo -->
 
 <style type="text/css">
 body { margin: 0px; }
 .block { display: block }
 </style>
 <table width="100%" cellspacing="0" cellpadding="0" border="0">
 <tr><td height="25" align="left" valign="top" bgcolor="#990000">
 <a href="http://www.usc.edu">
 <img src="http://www.usc.edu/usc/img/01/usc-name-white-cardinal.gif" border="0" 
 alt="University of Southern California" class="block" width="255" height="25" />
 </a>
 </td></tr></table>
 
 <!-- begin monogram bar -->
 <!-- end monogram bar -->
 <div class="header">
 <h2>ITS Web Services</h2>
 <hr />
 <h1>Schedule of Classes API</h1>
 </div>
 <hr />
 <p />
 <div style="margin-top:10px;margin-bottom:10px;margin-left:20px;">
 
 EOF;

if (isset($_SERVER['PATH_INFO']) && trim($_SERVER['PATH_INFO'])) {
    $filename = $soc_path."/notes/".trim(substr($_SERVER['PATH_INFO'],1));
}

if ($filename != "" && file_exists($filename)) {

    $soc_uri != false ? $soc_uri = print("<a href='".$conf->get("soc_uri")."/help'>Top</a></ p>\n") : true;
    $text = file_get_contents($filename);
    $textile = new Textile();
    echo "<div>\n".$textile->TextileThis($text)."\n</div>\n";
} else {
    echo<<<EOF
 SOC(Schedule of Classes) API is a web service to integrate Schedule of Classes content into other USC web sites. It is not an end services as such though the on-line Schedule of Classes uses the SOC API.  If you want to familiarize yourself with what is available see the <a href='$soc_uri/help/README.txt'>README</a> and <a href='$soc_uri/help/SOC_API.txt'>SOC API</a> docs.
 
 <h3>Developer</h3>
 <ul>
 
 EOF;

    chdir($soc_path."/notes");
    $txt_files = glob("*.txt");
    chdir($soc_path);
    $exclude_files = array();
    $exclude_files[] = "Configuration.txt";
    $exclude_files[] = "Participants.txt";
    $exclude_files[] = "INSTALLATION.txt";
    $exclude_files[] = "doxy-uscmonogram.txt";
    $exclude_files[] = "element breakdown for SOC.txt";

    foreach ($txt_files as $filename) {
        if (in_array($filename, $exclude_files) === false) {
            $link = $conf->get("soc_uri")."/help/".$filename;
            $label = str_replace(array(".txt","_"),array(""," "),$filename);
            echo "\t<li><a href='$link'>$label</a></li>\n";
        }
    }
    echo<<<EOF
 <li><a href='$soc_uri/docs/html'>Source Code Documentation</a></li>
 </ul>
 <p />
 
 <h3>The AIS/REG source data</h3>
 <ul>
   <li><a href='https://camel2.usc.edu/SOC-WS/soc-ws.asmx'>REG/SOC SOAP service documentation</a></li>
 </ul>
 
 EOF;
}

echo<<<EOF
 </div>
 </body>
 </html>
 
 EOF;
?>