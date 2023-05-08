<?php
require_once("Model.php");
require_once("SQLDb.php");

function SQLDb_strtotime($string) {
    if (trim($string)) {
        return strtotime($string);
    }
    return -1; // Time must be greater then -1 so this is an error
}

function SQLDb_now() {
    return date("Y-m-d")."T".date("H:i:sp");
}

function SQLDb_password($string) {
    if (trim($string)) {
        return md5($string);
    }
    return -1; // Time must be greater then -1 so this is an error
}

function SQLDb_init($dbhandle) {
    sqlite_create_function($dbhandle,"STRTOTIME","SQLDb_strtotime",1);
    sqlite_create_function($dbhandle,"NOW","SQLDb_now",0);
    sqlite_create_function($dbhandle,"PASSWORD","SQLDb_password",1);
}

class SQLDb extends Model {
    public function SQLDb($conf = NULL) {
        Model::Model($conf);
        $this->dbh = NULL;
        $this->qry = NULL;
        $this->rowCount = false;
        $dbtype = $this->get("db_type");
        $dbname = $this->get("db_name");
        if ($dbname !== false && $dbtype !== false && $dbtype == "sqlite") {
            $this->open();
        }
    }

    public function lastInsertRowId() {
        $db_type = $this->get("db_type");
        switch($db_type) {
            case 'mysql':
                return mysql_insert_id();
            default:
                return sqlite_last_insert_rowid($this->dbh);
        }
    }

    public function numRows() {
        return $this->rowCount;
    }

    public function escapeString($s) {
        $db_type = $this->get("db_type");
        switch($db_type) {
            case 'mysql':
                if ($this->dbh) {
                    return mysql_real_escape_string(str_replace("--","&mdash;",$s),$this->dbh);
                } else {
                    return mysql_escape_string(str_replace("--","&mdash;",$s));
                }
            default:
                return sqlite_escape_string(str_replace("--","&mdash;",$s));
        }
    }

    public function open($name = "") {
        $db_type = $this->get("db_type");
        switch($db_type) {
            case 'mysql':
                $this->get("db_host") !== false ? $mysql_host = $this->get("db_host") : $mysql_host = "localhost";
                $this->get("db_user") !== false ? $mysql_user = $this->get("db_user") : $mysql_user = "root";
                $this->get("db_password") !== false ? $mysql_password = $this->get("db_password") : $mysql_password = "";
                $this->get("db_name") !== false ? $mysql_database = $this->get("db_name") : $mysql_database = "mysql";

                $this->dbh = mysql_connect($mysql_host, $mysql_user, $mysql_password);
                if (! $this->dbh) {
                    $this->error('Could not connect: ' . mysql_error());
                } else {
                    $this->db_selected = mysql_select_db($mysql_database, $this->dbh);
                    if (!$this->db_selected) {
                        $this->error("Could not select Db: $mysql_database " . mysql_error());
                    }
                }
                break;
            default:
                if ($this->dbh) {
                    sqlite_close($this->dbh);
                }
                if ($name != "") {
                    $this->set("db_name",$name);
                }

                $this->get("db_perms") !== false ? $db_perms = $this->get("db_perms") : $db_perms = "0666";
                $db_name = $this->get("db_name");
                $this->dbh = sqlite_open($db_name,$db_perms,$error);
                if ($this->dbh) {
                    SQLDb_init($this->dbh);
                } else {
                    $this->error("error opening database ".$db_name.": ".$error);
                }
                break;
        }

        return ($this->errorCount() == 0);
    }

    public function execute($sql) {
        $lines = explode("\n",$sql);
        $sql_statement = "";
        // Find each SQL statement so we can process individual.
        // Strip comments and empty lines.
        foreach ($lines as $line) {
            $text = "";
            if (strpos($line,"--") === false) {
                $text = trim($line);
            } else if (strpos(trim($line),"--") > 1) {
                list($s,$junk) = explode('--',$line,2);
                trim($s) != "" ? $text = trim($s): "";
            }
            if ($text != "") {
                $sql_statement .= $text;
            }
        }
        $sql_statement = trim($sql_statement);
        if ($sql_statement != "") {
            $db_type = $this->get("db_type");
            switch($db_type) {
                case 'mysql':
                    if (stripos($sql_statement,"SELECT") !== false) {
                        $this->qry = mysql_query($sql_statement);
                        $this->qry ? $this->rowCount = mysql_num_rows($this->qry) : $this->rowCount = 0;
                    } else {
                        $this->qry = mysql_query($sql_statement);
                        // $this->rowCount = mysql_affected_rows($this->dbh);
                        $this->rowCount = mysql_affected_rows();
                    }
                    if (! $this->qry) {
                        $error = mysql_error();
                        $this->error("\nDB error: ".$error."\nDatabase: ".$this->get("db_name")."\nSQL statement:\n".$sql_statement);
                    }
                    break;
                default:
                    if (stripos($sql_statement,"SELECT") !== false) {
                        $this->qry = sqlite_query($this->dbh, $sql_statement, SQLITE_ASSOC,$error);
                        $this->rowCount = sqlite_num_rows($this->qry);
                    } else {
                        sqlite_exec($this->dbh, $sql_statement, $error);
                        $this->rowCount = sqlite_changes($this->dbh);
                    }
                    $error || $this->qry === false ? $this->error("\nDB error: ".$error."\nDatabase: ".$this->get("db_name")."\nSQL statement:\n".$sql_statement) : "";
                    break;
            }
        }

        return ($this->errorCount() == 0);
    }

    public function getRow() {
        $db_type = $this->get("db_type");
        switch($db_type) {
            case 'mysql':
                if ($this->qry) {
                    $result = mysql_fetch_array($this->qry, MYSQL_ASSOC);
                } else {
                    $this->error("no query available");
                }
                break;
            default:
                if ($this->qry) {
                    $result = sqlite_fetch_array($this->qry, SQLITE_ASSOC);
                } else {
                    $this->error("no query available");
                }
                break;
        }
        if ($result !== false) {
            foreach ($result as $key => $value) {
                $result[$key] = stripslashes($value);
            }
        }
        if ($this->errorCount() == 0) {
            return $result;
        }
        return false;
    }

    public function close() {
        $db_type = $this->get("db_type");
        switch($db_type) {
            case 'mysql':
                if ($this->dbh) {
                    //mysql_close($this->dbh);
                    //mysql_close($this->dbh);
                } else {
                    $this->error("database ".$this->get("db_name")." not open");
                }
                break;
            default:
                if ($this->dbh) {
                    sqlite_close($this->dbh);
                } else {
                    $this->error("database ".$this->get("db_name")." not open");
                }
                break;
        }
        return ($this->errorCount() == 0);
    }
}

?>