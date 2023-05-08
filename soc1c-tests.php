
<?php
require_once("safely-php/assert.php");
require_once("models/WSCORE2.php");
require_once("models/soc1c.php");

$expected_no = 3;
$completed_no = 0;

function testBasicMethods () {
    global $assert;
    global $completed_no;

    $conf = new WSCORE2();
    $conf->set("soc_namespace", "http://tempuri.org/");
    $conf->set("soc_wsdl", "https://camel2.usc.edu/SOC-WS/soc-ws.asmx");
    $conf->set("iui_wsdl", "https://camel2.usc.edu/SOC-WS/iui-ws.asmx");

    $assert->isTrue($conf->get("soc_namespace") === "http://tempuri.org/", "Configuration should have been set to http://tempuri.org/ for soc_namespace");
    $obj = new SOC_Widget($conf);
    $assert->isTrue($obj->get("soc_namespace") == $conf->get("soc_namespace"), "Namespace should be set to ".$conf->get("soc_namespace")." but found ".$obj->get("soc_namespace"));
    $completed_no += 1;
}

function testSOC_Configuration () {
    global $assert;
    global $completed_no;

    $conf = new SOC_Configuration("Test SOC API1C","soc1c.conf");
    $assert->isTrue($conf->get("soc_namespace") !== false, "Should have soc_namespace set if configuration was read properly.");

    $obj = new SOC_Widget($conf);
    $assert->isTrue($conf->get("soc_namespace") === $obj->get("soc_namespace"), "Should have soc_namespace set in new obj of class SOC_Widget.");
    $assert->isTrue($obj->version() === "SOC1C", "Should have version set.");
    $completed_no += 1;
}

if (php_sapi_name() !== 'cli') {
    echo 'This should be run from the command line.';
    exit(1);
}
echo 'Starting [soc1c-tests.php] ...' . PHP_EOL;
echo "\tstarting, testBasicMethods() $completed_no/$expected_no\n";
testBasicMethods();
echo "\tstarting, testSOC_Configuration() $completed_no/$expected_no\n";
testSOC_Configuration();
echo "Success!! completed $completed_no/$expected_no" . PHP_EOL;
?>