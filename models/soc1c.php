<?php
/***************************************************************************
 * soc.php - Basic Objects used in SOC and IUI
 *
 * @author R. S. Doiel rsdoiel@usc.edu
 *
 * @copyright copyright (c) 2007 University of Southern California
 *
 ***************************************************************************/
require_once("WSCORE2.php");

function fmt($msg, $otag = "",$ctag = "<br />\n") {
    if (isset($_SERVER['HTTP_HOST'])) {
        return $otag.$msg.$ctag;
    } else {
        if (strpos($otag,"<li>") !== false) {
            return " * ".$msg."\n";
        } else {
            return $msg."\n";
        }
    }
}


class SOC_Widget extends WSCORE2 {

    function SOC_Widget($conf = NULL) {
        if (is_string($conf) && file_exists($conf)) {
            SOC_Widget::conf($conf);
        } else {
            WSCORE2::__construct($conf);
        }
        $this->version_msg = "SOC1C";
    }


    function remove($key) {
        if (isset($this->attributes[$key])) {
            unset($this->attributes[$key]);
        }
        return ! isset($this->attributes[$key]);
    }


    function version () {
        return $this->version_msg;
    }


    function error ($msg) {
        return $this->message("ERROR: ".$msg);
    }


    function errors ($prefix = "\nWARNING: ") {
        $msg = $this->message();
        if ($msg !== false) {
            return $prefix.$msg;
        }
        return false;
    }


    function errorReset() {
        for ($i = 0; $i < count($this->message_queue); $i++) {
            if (strpos($this->message_queue[$i],"ERROR:") !== false) {
                $this->message_queue[$i] = "";
            }
        }
        return true;
    }


    function conf ($configuration) {
        if (! file_exists($configuration)) {
            $this->error("Can't find $configuration file.");
            return false;
        }
        if (! is_readable($configuration)) {
            $this->error("Can't read $configuration file.");
            return false;
        }
        $config_text = file($configuration);
        foreach ($config_text as $line) {
            // Strip off comments and white space
            if (strstr($line,'#')) {
                list($src,$comment) = explode('#',$line,2);
                $line = $src;
            }
            // Set key value pairs.
            if (trim($line)) {
                list ($key, $value) = explode(':',$line,2);
                $this->set(trim($key),trim($value));
            }
        }
        return true;
    }
}


class SOC_Cache2 extends WSCORE2 {
    public function __construct ($conf = NULL) {
        WSCORE2::__construct ($conf);
    }

    public function createTables () {
        switch ($this->get("db_type")) {
            case 'sqlite':
                // sqlite2 doesn't support IF EXISTS so just blindly run this
                // and assume it works.
                $this->execute("DROP TABLE soc_cache");
                $this->execute("CREATE TABLE soc_cache (cache_id INTEGER AUTO_INCREMENT PRIMARY KEY, uri VARCHAR(255), src TEXT, modified TIMESTAMP)");
                if ($this->errorCount() === 0) {
                    return true;
                }
                break;
            case 'mysql':
                $this->execute("DROP TABLE IF EXISTS soc_cache");
                $this->execute("CREATE TABLE IF NOT EXISTS soc_cache (cache_id INTEGER AUTO_INCREMENT PRIMARY KEY, uri VARCHAR(255), src MEDIUMTEXT, modified TIMESTAMP)");
                if ($this->errorCount() === 0) {
                    return true;
                }
                break;
            default:
                $this->message("ERROR: Database type not configured.");
                return false;
        }
        return false;
    }

    public function setCache($uri, $src = false, $verbose = false) {
        $this->execute("DELETE FROM soc_cache WHERE uri = '" .trim($uri) . "'", $verbose);
        if ($src === false) {
            // Clear the item from the cache
            return ($this->errorCount() === 0);
        }
        if ($this->errorCount() > 0) {
            $this->message();// Ugly, Dump the errors in the message queue.
        }
        if ($this->get("db_type") == 'mysql') {
            $src = mysql_escape_string(mb_convert_encoding($src,"UTF-8"));
        }
        $this->execute("INSERT INTO soc_cache (uri, src) VALUES ('".$uri."', '".$src."')", $verbose);
        if ($this->errorCount() > 0) {
            $this->message("ERROR: cache not set for $uri");
            return false;
        }
        return true;
    }

    public function getCache($uri, $verbose = false) {
        $data = array('uri' => $uri);
        $this->mapExecute("SELECT src FROM soc_cache WHERE uri = '{uri}'", $data, $verbose);
        $row = $this->getRow();
        $this->release();
        if (isset($row['src'])) {
            return $row['src'];
        }
        return false;
    }

    public function listCache ($verbose = false) {
        $rows = array();
        $this->execute("SELECT uri FROM soc_cache ORDER BY modified", $verbose);
        while ($row = $this->getRow()) {
            if (isset($row['uri'])) {
                $rows[] = $row['uri'];
            }
        }
        $this->release();
        return $rows;
    }
}

class SOC_Configuration extends SOC_Widget {

    function SOC_Configuration ($app_name="soc", $conf="soc1c.conf") {
        SOC_Widget::SOC_Widget();
        // Setup pathing to see if this solves the Smarty reference problem.
        $php_include_path = get_include_path ();
        if (! strstr("$php_include_path", ".")) {
            set_include_path(".:" . $php_include_path);
        }

        SOC_Widget::set('app_name', $app_name);
        SOC_Widget::set('soc_conf', $conf);

        if (isset($_SERVER) && isset($_SERVER['HTTP_HOST'])) {
            SOC_Widget::set('hostname', $_SERVER['HTTP_HOST']);
        } else {
            SOC_Widget::set('hostname', "web-app.usc.edu");
        }
        SOC_Widget::set('soc_path', "/www/ws/$app_name");
        SOC_Widget::set('soc_uri', "http://".
            SOC_Widget::get('hostname').
            "/ws/$app_name");

        SOC_Widget::set("db_type", "sqlite");
        SOC_Widget::set("db_name", "soc.sq2");

        if (file_exists ("../_conf/$conf")) {
            SOC_Widget::set('soc_conf', "../_conf/$conf");
        } else if (file_exists ("_conf/$conf")) {
            SOC_Widget::set('soc_conf', "_conf/$conf");
        } else if (file_exists("$conf")) {
            SOC_Widget::set('soc_conf', "./$conf");
        } else {
            exit("<strong>WARNING: can't find $conf file.  Application isn't configured.</strong><br />\n");
        }

        SOC_Widget::conf(SOC_Widget::get('soc_conf'));
    }
}
?>
