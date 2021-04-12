<?php

namespace Continy\Core;

class ValidationError
{

    public $key;
    public $value;
    public $rule;

    function __construct($key, $value, $rule)
    {

        $msg = Language::get('validationerror/' . $key);
        $msg = (empty($msg)) ? Language::get('default/message/ValidationError') : $msg;

        $this->key = $key;
        $this->value = $value;
        $this->rule = $rule;
        $this->message = $msg;
    }
}
