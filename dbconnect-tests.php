<?php
require_once("safely-php/assert.php");
require_once("models/soc1c.php");
require_once("models/api1c.php");

if (php_sapi_name() !== 'cli') {
    echo 'This should be run from the command line.';
    exit(1);
}

$assert->ok(file_exists("_conf/soc1c.conf"), "configuration file not found.");
$api = new SOC_API1C(new SOC_Configuration("TEST db connect", "_conf/soc1c.conf"));
$assert->ok($api, "Should have a new SOC_API1C object.");
$assert->ok($api->open(), "Should be able to open db.");
echo "Open: ".$api->message().PHP_EOL;

$sql = "REPLACE INTO test (id, email, name, url, published) VALUES (1, 'rsdoiel@usc.edu', 'R. S. Doiel', 'http://rsdoiel.github.io', NOW())";
$assert->ok($api->execute($sql), "Should be able to replace a row [$sql]");

$sql = "SELECT * FROM test LIMIT 1;";
$assert->ok($api->execute($sql), "Should be able to execute [$sql]");
echo "SQL [$sql]: ".$api->message().PHP_EOL;
echo "Should get some rows back.".PHP_EOL;
$i = 0;
while ($row = $api->getRow()) {
    echo "Row: ".print_r($row,true).PHP_EOL;
    $i += 1;
}
$assert->ok($i > 0, "Should have more then zero rows.");
$api->close();
echo "Close DB: ".$api->message().PHP_EOL;
?>