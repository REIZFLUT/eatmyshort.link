<?php

namespace Continy\Core;

use Continy\Core\ValidationError;

class Validation
{

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

    public static function email($key, $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return new ValidationError($key, $value, 'email');
        } else {
            return false;
        }
    }

    public static function secure_password($key, $value)
    {
        if (strlen($value) < 8) {
            return new ValidationError($key, $value, 'password_to_short');
        } elseif (!preg_match("#[0-9]+#", $value)) {
            return new ValidationError($key, $value, 'password_no_number');
        } elseif (!preg_match("#[a-zA-Z]+#", $value)) {
            return new ValidationError($key, $value, 'password_no_letter');
        } else {
            return false;
        }
    }
}
