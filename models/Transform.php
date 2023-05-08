<?php
require_once("Model.php");

class Transform extends Model {
    public function Transform ($conf = NULL) {
        Model::Model($conf);
        if ($this->get("Transform_Delimiter") !== false) {
            $this->set("Transform_OpenDelimiter","@");
            $this->set("Transform_CloseDelimiter","@");
        } else {
            if ($this->get("Transform_OpenDelimiter") === false) {
                $this->set("Transform_OpenDelimiter","@");
            }
            if ($this->get("Transform_CloseDelimiter") === false) {
                $this->set("Transform_CloseDelimiter","@");
            }
        }
    }

    public function TransformThis($data, $text) {
        if ($this->get("Transform_Delimiter") !== false) {
            $open_token = $this->get("Transform_Delimiter");
            $close_token = $this->get("Transform_Delimiter");
        } else {
            $open_token = $this->get("Transform_OpenDelimiter");
            $close_token = $this->get("Transform_CloseDelimiter");
        }
        $target = array();
        $replacement = array();
        foreach ($data as $key => $value) {
            $target[] = $open_token.trim($key).$close_token;
            isset($value) ? $replacement[] = $value : $replacement[] = "";
        }
        return str_replace($target,$replacement, $text);
    }
}

?>