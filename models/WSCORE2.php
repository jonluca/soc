<?php
error_reporting(E_ALL | E_STRICT);
@date_default_timezone_set(date_default_timezone_get());

/* Autoload models, when WSCORE doesn't define them explicitly */
function __autoload($class_name) {
    if (class_exists($class_name)) {
        // Don't do anything, we're OK.
    } else if (file_exists('models/'.$class_name.'.min.php')) {
        require_once('models/'.$class_name.'.min.php');
    } else if (file_exists('models/'.$class_name.'.php')) {
        require_once('models/'.$class_name.'.php');
    }
}

class WSCORE2 {
    public function __construct($conf = NULL) {
        $this->attributes = array();
        $this->message_queue = array();
        $this->db = NULL;
        $this->qry = NULL;

        if (is_object($conf) && method_exists($conf, "getKeys")) {
            foreach($conf->getKeys() as $key) {
                $this->set($key, $conf->get($key));
            }
        } else if (is_array($conf)) {
            foreach ($conf as $key => $value) {
                $this->set($key,$value);
            }
        } else if (is_string($conf)) {
            if (strpos(ltrim($conf),"[") !== false || strpos(ltrim($conf),"{") !== false) {
                /* First try parsing as a JSON file. */
                $obj = json_decode($conf);
                if ($obj !== false) {
                    foreach ($obj as $key => $value) {
                        $this->set($key,$value);
                    }
                } else {
                    $this->message("ERROR: Problem decoding JSON configuration");
                }
            } else {
                $this->simpleConf($conf);
            }
        }
    }

    public function simpleConf($configuration) {
        $lines = explode("\n",$configuration);
        $i = 1;
        foreach ($lines as $line) {
            if (strpos($line,"#") !== false) {
                list($line, $comment) = explode("#", $line);
            }
            if (trim($line) != "") {
                if (strpos($line,":") !== false) {
                    list($key, $value) = explode(":",$line,2);
                    $this->set($key, $value);
                } else {
                    $this->message("ERROR: ".$i." doesn't make sense [$line]");
                }
            }
            $i++;
        }
        if ($this->errorCount() === 0) {
            return true;
        }
        return false;
    }


    public function getKeys() {
        if (isset($this->attributes) && is_array($this->attributes)) {
            return array_keys($this->attributes);
        }
        return false;
    }

    public function set ($key, $value = NULL) {
        if ($value === NULL) {
            unset($this->attributes[$key]);
            return true;
        }
        $this->attributes[$key] = $value;
        return isset($this->attributes[$key]);
    }

    public function get ($key) {
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }
        return false;
    }

    public function map($src, $obj, $verbose = false) {
        $escape_chars = '';
        if (strpos($src, '"') !== false) {
            $escape_chars .= '"';
        }
        if (strpos($src, "'") !== false) {
            $escape_chars .= "'";
        }
        foreach ($obj as $key => $value) {
            if ($escape_chars !== '') {
                /* Check to see if we need to double escape for JSON content being
                   mapped. */
                if (strpos($value,'\"') === false && strpos($value,"\'") === false) {
                    $src = str_replace('{' . $key . '}', addcslashes($value, $escape_chars), $src);
                } else {
                    $src = str_replace('{' . $key . '}', addcslashes(str_replace(array('\"', "\'"), array('\\'.'\\'.'"', '\\'.'\\'."'"), $value), $escape_chars), $src);
                }
            } else {
                $src = str_replace('{' . $key . '}', $value, $src);
            }
        }
        if ($verbose === true) {
            $this->message("Map(): $src");
        }
        return $src;
    }

    public function message ($msg = NULL) {
        if ($msg === NULL) {
            $result = implode("\n", $this->message_queue);
            unset($this->message_queue);
            $this->message_queue = array();
            return $result;
        }
        $this->message_queue[] = $msg;
        return true;
    }

    public function errorCount () {
        $error_count = 0;
        foreach($this->message_queue as $msg) {
            if (stripos($msg, "ERROR:") !== false) {
                $error_count++;
            }
        }
        return $error_count;
    }

    public function open () {
        switch($this->get("db_type")) {
            case 'mysql':
                $mysql_host = $this->get("db_host");
                if ($mysql_host === false) {
                    $this->message("ERROR: You must specify a database host to open.");
                    return false;
                }
                $mysql_user = $this->get("db_user");
                if ($mysql_user === false) {
                    $this->message("ERROR: You must specify a database user to open.");
                    return false;
                }
                $mysql_password = $this->get("db_password");
                if ($mysql_password === false) {
                    $this->message("ERROR: You must specify a database password to open.");
                    return false;
                }
                $mysql_database = $this->get("db_name");
                if ($mysql_database === false) {
                    $this->message("ERROR: You must specify a database name to open.");
                    return false;
                }

                $this->db = @mysql_connect($mysql_host, $mysql_user, $mysql_password);
                if (! $this->db) {
                    $this->db = NULL;
                    $this->message('ERROR: ' . mysql_error());
                    return false;
                } else {
                    if (! mysql_select_db($mysql_database, $this->db)) {
                        $this->message("ERROR: Could not select database: $mysql_database " . mysql_error());
                        return false;
                    }
                }
                return true;
            case 'sqlite':
                $sqlite_database = $this->get("db_name");
                if ($sqlite_database === false) {
                    $this->message("ERROR: You must specify a database name to open.");
                    return false;
                }
                $this->db = sqlite_open($sqlite_database,'0666',$error);
                if ($this->db === false) {
                    $this->message("error opening database ".$db_name.": ".$error);
                    return false;
                }
                return true;
            default:
                $this->message("ERROR: Database type not set.");
                return false;
        }
        return false;
    }

    public function release () {
        if ($this->db === NULL) {
            $this->message("ERROR: Database not configured.");
            return false;
        }
        if ($this->qry === NULL) {
            $this->message("ERROR: no result set to free");
            return false;
        }
        switch($this->get("db_type")) {
            case 'mysql':
                if ($this->qry && mysql_free_result($this->qry) === true) {
                    $this->qry = NULL;
                    return true;
                }
                $this->message("ERROR: " . mysql_error($this->db));
                return false;
            case 'sqlite':
                // FIXME: there should be some similar method to mysql_free_result ...
                unset($this->qry);
                $this->qry = NULL;
                return true;
            default:
                $this->message("ERROR: ".$this->get("db_type")." not supported for release.");
                return false;
        }
        return true;
    }

    public function execute ($sql, $verbose = false) {
        if ($this->db === NULL) {
            $this->message("ERROR: Database not configured.");
            return false;
        }
        if ($verbose === true) {
            $this->message("SQL: $sql");
        }
        switch($this->get("db_type")) {
            case 'mysql':
                $this->set('SQLRowCount', 0);
                $this->qry = mysql_query($sql);
                if ($this->qry === false) {
                    $this->message("ERROR: SQL [$sql] " . mysql_error());
                    return false;
                }
                if ($this->qry) {
                    $num_rows = @mysql_num_rows($this->qry);
                    if ($num_rows === false) {
                        $this->set("SQLRowCount", mysql_affected_rows());
                    } else {
                        $this->set("SQLRowCount", $num_rows);
                    }
                }
                return true;
            case 'sqlite':
                $this->set('SQLRowCount', 0);
                if (stripos($sql,"SELECT ") !== false) {
                    $this->qry = sqlite_query($this->db, $sql, SQLITE_ASSOC, $error);
                    if ($this->qry) {
                        $this->set('SQLRowCount', sqlite_num_rows($this->qry));
                    }
                } else {
                    $this->qry = @sqlite_exec($this->db, $sql, $error);
                    if (! $this->qry) {
                        $this->message("ERROR: SQL [$sql] " . $error);
                        return false;
                    } else {
                        $this->set('SQLRowCount', sqlite_changes($this->db));
                    }
                }
                return true;
            default:
                $this->message("ERROR: Database type not set.");
                return false;
        }
        return false;
    }


    public function mapExecute ($sql, $obj, $verbose = false) {
        if ($this->get("db_type") === "sqlite") {
            $sql = str_replace(array("\\'","\\\""), array("''",'""'), $this->map($sql, $obj, $verbose));
            return $this->execute($sql, $verbose);
        }
        return $this->execute($this->map($sql, $obj, $verbose), $verbose);
    }

    public function getRow ($verbose = false) {
        if ($this->db === NULL) {
            $this->message("ERROR: Database not configured.");
            return false;
        }
        switch($this->get("db_type")) {
            case 'mysql':
                if ($this->qry) {
                    return mysql_fetch_array($this->qry, MYSQL_ASSOC);
                } else {
                    $this->message("WARNING: no query available");
                }
                break;
            case 'sqlite':
                if ($this->qry) {
                    return sqlite_fetch_array($this->qry, SQLITE_ASSOC);
                } else if ($verbose === true) {
                    $this->message("WARNING: no query available");
                }
                return false;
            default:
                $this->message("ERROR: Database type not set.");
                break;
        }
        return false;
    }

    public function lastInsertRowId () {
        if ($this->db === NULL) {
            $this->message("ERROR: Database not configured.");
            return false;
        }
        switch($this->get("db_type")) {
            case 'mysql':
                return mysql_insert_id();
            case 'sqlite':
                return sqlite_last_insert_rowid($this->db);
            default:
                $this->message("ERROR: Database type not set.");
                break;
        }
        return false;
    }

    public function close () {
        if ($this->db === NULL) {
            $this->message("ERROR: Database not configured.");
            return false;
        }
        switch($this->get("db_type")) {
            case 'mysql':
                mysql_close($this->db);
                return true;
            case 'sqlite':
                sqlite_close($this->db);
                return true;
            default:
                $this->message("ERROR: Database type not set.");
                break;
        }
        return false;
    }
}

?>