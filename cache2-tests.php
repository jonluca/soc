<?php
require_once("models/WSCORE2.php");
require_once("safely-php/assert.php");
require_once("models/soc1c.php");

$expected_no = 2;
$completed_no = 0;

function testBasic () {
    global $assert;
    global $completed_no;


    $conf = new SOC_Configuration("SOC Cache2 Test", "test_soc1c.conf");
    $obj = new SOC_Cache2($conf);
    $assert->isTrue(is_object($obj), "Constructor worked.");
    $methods = array("set", "get", "open", "close", "execute", "mapExecute", "map", "setCache", "getCache", "listCache");
    foreach ($methods as $method_name) {
        $assert->isTrue(method_exists($obj, $method_name), "has $method_name method.");
    }
    $assert->isTrue($obj->open(), "open() SOC Cach2");
    $assert->isTrue($obj->createTables(), "createTables() should return true. " . $obj->message());
    $pages = array();
    for ($i = 0; $i < 10; $i++ ) {
        $page =<<<HTML
 <html>
   <head>
     <title>Test Page $i</title>
   </head>
   <body>
     <h1>Test Page $i</h1>
     <p>
       <a href="http://www.usc.edu">USC Home Page</a>
     </p>
   </body>
 </html>
 HTML;
        $assert->isTrue($obj->setCache("/test$i", $page, true), "Should be able to save /test$i. " . $obj->message());
        $assert->isTrue($obj->getCache("/test$i", true) === $page, "Cache should return page for /test$i. " . $obj->message());
        $pages[$i] = $page;
    }

    $cachedPages = $obj->listCache(true);
    foreach ($cachedPages as $uri) {
        $i = str_replace('/test','',$uri);
        $assert->isTrue($obj->getCache($uri) === $pages[$i], "Cached page for $uri ($i). " .  $obj->message());
    }
    $assert->isTrue($obj->close(), "close() SOC Cache2.");

    $completed_no += 1;
}

function testQuoteFix () {
    global $assert;
    global $completed_no;

    $conf = new SOC_Configuration("SOC Cache2 Test", "test_soc1c.conf");
    $obj = new SOC_Cache2($conf);
    $obj->open();
    $page =<<<HTML
 <html>
   <head>
     <title>Quote's Test Page</title>
   </head>
   <body>
     <h1>Quote's Test Page</h1>
     <p>
       <a href="http://www.usc.edu">USC Home Page</a>
     </p>
     <p>Fred's comment was about quoting</p>
   </body>
 </html>
 HTML;
    $expected = "single quote: ' -> " . str_replace("'", "\\'", $page);
    $result = $obj->map("single quote: ' -> {page}", array('page' => $page), true);
    $assert->isTrue(strpos($result, $expected) === 0, "map() expected\n[" . $expected . "]\n[" . $result . "] " . strpos($result, $expected));
    $assert->isTrue($obj->setCache("/Qtest", $page, true), "Should be able to save /Qtest. " . $obj->message());
    $assert->isTrue($obj->getCache("/Qtest", true) === $page, "Cache should return page for /Qtest. " . $obj->message());
    $obj->close();
    $completed_no += 1;
}

if (php_sapi_name() !== 'cli') {
    echo 'This should be run from the command line.';
    exit(1);
}

echo 'Starting [soc_cache2-tests.php] ...' . PHP_EOL;
$assert->ok(file_exists("_conf/test_soc1c.conf"), "Should have a test configuration file.");
echo "\tstarting, testBasic() $completed_no/$expected_no\n";
testBasic();
echo "\tstarting, testQuoteFix() $completed_no/$expected_no\n";
testQuoteFix();
echo "Success!! completed $completed_no/$expected_no" . PHP_EOL;
?>
