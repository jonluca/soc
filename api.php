<?php
error_reporting(E_ALL | E_STRICT);
date_default_timezone_set("America/Los_Angeles");

require_once("models/soc1c.php");
require_once("models/api1c.php");

isset($_GET) ? $get = $_GET : $get = array();
isset($_POST) ? $post = $_POST : $post = array();
isset($_SESSION) ? $session = $_SESSION : $session = array();
isset($_SERVER)  ? $server  = $_SERVER  : $server  = array();
isset($_FILE) ? $file = $_FILE : $server = array();
isset($argv[1]) ? $server['PATH_INFO'] = trim($argv[1]) : "";

session_start();
if (isset($_FILE)) {
    $file = $_FILE;
} else {
    $file = NULL;
}
if (isset($_SERVER['PATH_INFO']) && trim (substr($_SERVER['PATH_INFO'],1))) {
    $api = new SOC_API1C(new SOC_Configuration("SOC API 1C - Schedule of Classes","soc1c.conf"));
    if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] === '/help') {
        header("Location: " . $api->get("soc_uri") .  "/help.php");
        exit(0);
    }
    //$api->open();
    if (isset($_GET['refresh'])){
        $refreshkey = $api->get("soc_cache_refresh_key");
        if ($refreshkey === false) {
            die("Refresh from API not turned on");
        }
        $keyprovided = urldecode($_GET['refresh']);
        if (strcmp($refreshkey, $keyprovided) !== 0) {
            die("What? $keyprovided ");
        }
        $api->set("cache_replace",true);
    }
    $api->eventloop($_GET, $_POST, $_SESSION, $_SERVER, $file);
    header("Content Type: ".$api->contentType()); // now done in api-render()
    if ($api->errorCount() == 0) {
        if (isset($_GET['jsonp_callback'])) {
            $jsonp_callback = strip_tags(str_replace(array('"',"'",' '),'', urldecode($_GET['jsonp_callback'])));
            echo $jsonp_callback. '('.$api->render().')';
        } else {
            echo $api->render();
        }
    } else {
        if (isset($_GET['jsonp_callback'])) {
            $jsonp_callback = strip_tags(str_replace(array('"',"'",' '),'', urldecode($_GET['jsonp_callback'])));
            echo 'var ' . $jsonp_callback. ' = function () { return ('.json_encode(array('error' => $api->errors())).');};';
        } else {
            echo json_encode(array('error' => $api->errors()));
        }
    }
    $api->close();
} else {
    die("asdf");
    header("Location: ../help");
    exit(0);
}
?>
