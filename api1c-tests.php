<?php
require_once("safely-php/assert.php");
require_once("models/soc1c.php");
require_once("models/api1c.php");

$expected_no = 0;
$completed_no = 0;

function testSOC_Cache2 () {
    global $assert;
    global $expected_no;
    global $completed_no;

    $expected_no += 1;
    print("\tstarting, testSOC_Cache2() $completed_no/$expected_no\n");
    $conf = new SOC_Configuration("SOC Cache Refresh", "soc1c.conf");
    $cache = new SOC_Cache2($conf);
    $assert->equal($cache->get("db_type"), "mysql", "Should have db_type = mysql");
    $assert->ok($cache->get("db_name"), "Should have db_name set");
    $assert->equal($cache->get("db_host"), "web-mysql.usc.edu", "Should have db_host set to web-mysql.usc.edu");
    $assert->ok($cache->get("db_password"), "Should have a db_password set.");
    $assert->ok($cache->open(), "Should be able to open cache");
    $json = $cache->getCache("/terms");
    $assert->ok($json, "Should get some terms data as JSON back" . $cache->message());
    $terms = json_decode($json, true);
    $assert->ok($terms["term"], "Should have some term data defined.");
    $assert->ok($cache->close(), "Should be able to close the cache");
    $completed_no += 1;
    print("\tcompleted, testSOC_Cache2() $completed_no/$expected_no\n");
}

function testPrototypeRefresh () {
    global $assert;
    global $expected_no;
    global $completed_no;

    $expected_no += 1;
    print("\tstarting, testPrototypeRefresh() $completed_no/$expected_no\n");
    $conf = new SOC_Configuration("SOC Prototype Refresh", "soc1c.conf");
    $assert->isTrue($conf->get("soc_namespace") !== false, "soc_namespace should be set and a string in soc1c.conf");

    $api1c = new SOC_API1C($conf);
    $assert->ok($api1c->open(), "Should be able to open the internal cache");
    $assert->ok($api1c->cache->db, "Should have a DB connection for cache");

    $now = Date("Y-m-d h:i:s");
    $result = $api1c->getTerms();
    $assert->isTrue($result, "getTerms should return true.");
    $json = $api1c->cache->getCache("/terms");
    $result = json_decode($json, true);
    $assert->isTrue(isset($result['term']), "api1c->cache->getCache('/terms') should return a term array " . print_r($result, true));
    $terms = $result['term'];
    $data = array();
    $data['cache_table'] = $api1c->get("soc_cache");
    $assert->ok($data['cache_table'], "Should have a cache_table saved. " . print_r($data, true));
    $data['now'] = $now;

    foreach ($terms as $term) {
        print("\t\tprocessing $term ");
        $assert->isTrue($api1c->getDepartments($term), "getDepartments should return true for $term.");
        $sql = "SELECT uri FROM {cache_table} WHERE uri LIKE '/departments/{term}' AND modified < '{now}'";
        $data['term'] = $term;
        $assert->isTrue($api1c->cache->mapExecute($sql, $data),"mapExecute($sql) should return true" . $api1c->cache->message());
        $depts = array();
        while ($row = $api1c->cache->getRow()) {
            $parts = explode("/",$row['uri']);
            $depts[] = $parts[2];
            print(".");
        }
        foreach ($depts as $dept) {
            print(" " . $dept);
            $assert->isTrue($api1c->getClasses($dept, $term), "getClasses($dept, $term) should return true.");
        }
        $sql = "SELECT uri FROM {cache_table} WHERE uri LIKE '/session/%{term}' AND modified < '{now}'";
        $assert->isTrue($api1c->cache->mapExecute($sql,$data),"mapExecute($sql) should return true");
        $sessions = array();
        while ($row = $api1c->cache->getRow()) {
            $parts = explode("/",$row['uri']);
            $sessions[] = $parts[2];
            print(".");
        }
        foreach ($sessions as $session) {
            print(" " . $session);
            $assert->isTrue($api1c->getSession($session, $term),"getSession($session, $term) should return true.");
        }
        print("\n");
        // Everything for term should have been modified.
        $sql = "SELECT uri FROM {cache_table} WHERE uri LIKE '%term' AND modified < '{now}'";
        $assert->isTrue($api1c->cache->mapExecute($sql, $data), "mapExecute($sql ...) should return true for term $term.");
        $row = $api1c->cache->getRow();
        $assert->isFalse($row, "row should have been false with everything modied for term $term.");
        $assert->isTrue($api1c->cache->get("SQLRowCount") === 0, "Row count should have been zero for term $term.");
    }
    $completed_no += 1;
    print("\ttestPrototypeRefresh() done $completed_no/$expected_no\n");
    $api1c->close();
}


if (php_sapi_name() !== 'cli') {
    echo 'This should be run from the command line.';
    exit(1);
}

echo 'Starting [soc_api1c-tests.php] ...' . PHP_EOL;
testSOC_Cache2();
testPrototypeRefresh();
echo "Success!! completed $completed_no/$expected_no" . PHP_EOL;

?>
