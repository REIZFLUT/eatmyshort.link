<?php

namespace Continy\Plugin;
use Continy\Core\ValidationError;
use Continy\Core\Request;

class EMSLValidation{

    private $rules;
    private $errors;

    public function __construct($rules)
    {
        $this->rules = $rules;
        $this->errors = [];
    }

    public function check(Request $request)
    {
        if (is_object($request->input) || $request->input == NULL) {
            $input = get_object_vars($request->input);
            return $this->validate($input);
        } elseif (is_array($request->input) || $request->input == NULL) {
            return $this->validate($request->input);
        } else {
            return false;
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getErrorMessages()
    {
        $messages = [];

        foreach ($this->errors as $ve) {
            $messages[$ve->key] = $ve->message;
        }

        return $messages;
    }

    public function validate($data)
    {
        foreach ($this->rules as $fieldname => $rules) {
            foreach ($rules as $rule) {
                if (method_exists($this, $rule)) {
                    $value = (isset($data[$fieldname])) ? $data[$fieldname] : NULL;
                    $error = self::{$rule}($fieldname, $value);
                    if ($error !== false) {
                        $this->errors[] = $error;
                    }
                }
            }
        }

        return (count($this->errors) > 0) ? false : true;
    }

    public static function required($key, $value)
    {
        return (empty($value)) ? new ValidationError($key, $value, 'required') : false;
    }
    
    public static function emsl_path($key, $value){
        if(preg_match('/[a-z0-9_-]+/s', $value, $match)){
            if(isset($match[0])){
                return ($match[0] == $value) ? false:new ValidationError($key, $value, 'emsl_path');
            } else {
                return new ValidationError($key, $value, 'emsl_path');
            }
        } else {
            return new ValidationError($key, $value, 'emsl_path');
        }
    }

}