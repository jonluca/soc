<?php
function debug($msg) {
    $fp = fopen("/info/www/test/soc/logs/debug.log","a");
    if ($fp) {
        fwrite($fp,$msg.PHP_EOL);
        fclose($fp);
    } else {
        die("Can't open log");
    }
}
?>