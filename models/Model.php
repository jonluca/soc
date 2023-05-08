<?php
error_reporting(E_ALL | E_STRICT);
date_default_timezone_set("America/Los_Angeles");

class Model {
    protected $version_msg = "2.0rc";
    protected $attributes = array();
    protected $js_attributes = array();
    protected $error_msgs = array();
    protected $confpath = "";

    function Model($conf = NULL) {
        if ($conf === NULL) {
            // Don't do anything.
        } else if (is_object($conf) && method_exists($conf,"getKeys") && method_exists($conf,"get")) {
            foreach ($conf->getKeys() as $key) {
                $this->set($key,$conf->get($key));
            }
        } else if (file_exists($conf) && is_readable($conf)) {
            $this->confpath = $conf;
            $this->parseConf(file($conf));
        } else if (file_exists("_conf/".$conf) && is_readable("_conf/".$conf)) {
            $this->confpath = "_conf/".$conf;
            $this->parseConf(file("_conf/".$conf));
        } else if (file_exists("../_conf/".$conf) && is_readable("../_conf/".$conf)) {
            $this->confpath = "../_conf/".$conf;
            $this->parseConf(file("../_conf/".$conf));
        } else if (file_exists("../../_conf/".$conf) && is_readable("../../_conf/".$conf)) {
            $this->confpath = "../../_conf/".$conf;
            $this->parseConf(file("../../_conf/".$conf));
        } else {
            $this->error("Can't access configuration ".$conf);
        }
    }

    private function parseConf($lines) {
        foreach($lines as $line) {
            // Strip Comments
            strpos($line,"#") !== false ? list($content,$junk) = explode("#",trim($line),2):$content = trim($line);
            // Skip empty lines
            if (trim($content) != "") {
                // Look for public/private key pairs
                list ($key,$value) = explode(":",$content,2);
                $isPrivate = false;
                if (strpos($value,"private:") === 0) {
                    list($junk,$value) = explode(":",$value,2);
                    $isPrivate = true;
                }
                $isPrivate === false ? $this->js_attributes[$key] = $value:"";
                $this->attributes[$key] = $value;
            }
        }
    }

    function get($key) {
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }
        return false;
    }

    function getKeys() {
        return array_keys($this->attributes);
    }

    function set($key,$value = NULL,$is_js_attribute = false) {
        if ($value === NULL) {
            if (isset($this->attributes[$key])) {
                unset($this->attributes[$key]);
            }
            if (isset($this->js_attributes[$key])) {
                unset($this->js_attributes[$key]);
            }
        } else {
            $this->attributes[$key] = $value;
            if ($is_js_attribute) {
                $this->js_attributes[$key] = $value;
            } else if (isset($this->js_attributes[$key])) {
                unset($this->js_attributes[$key]);
            }
            return isset($this->attributes[$key]);
        }
        return true;
    }

    function remove($key) {
        return $this->set($key,NULL);
    }

    function version () {
        return $this->version_msg;
    }

    function error ($msg) {
        return ($this->error_msgs[] = $msg);
    }


    function errorCount() {
        return count($this->error_msgs);
    }


    function errors ($prefix = "\nWARNING: ", $separator = "\n\t") {
        return $prefix.implode($separator,$this->error_msgs);
    }


    function errorReset() {
        $this->error_msgs = array();
        return true;
    }

    public function toJS($js_object_name) {
        $text = "$js_object_name = new Object();\n";
        foreach ($this->js_attributes as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $text .= $js_object_name."['$key'] = eval('".json_encode($value)."');\n";
            } else if (is_numeric($value)) {
                $text .= $js_object_name."['$key'] = ".$value.";\n";
            } else if ($value === true) {
                $text .= $js_object_name."['$key'] = true;\n";
            } else if ($value === false) {
                $text .= $js_object_name."['$key'] = false;\n";
            } else {
                $text .= $js_object_name."['$key'] = '".str_replace(array("'","\\"),array("\\'",'\\'),$value)."';\n";
            }
        }
        return $text;
    }
}

?>