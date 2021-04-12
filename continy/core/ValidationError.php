<?php

namespace Continy\Core;

class ValidationError{

    public $key;
    public $value;
    public $rule;

    function __construct($key, $value, $rule)
    {
        $this->key = $key;
        $this->value = $value;
        $this->rule = $rule;
        $this->message = 'Dieses Feld weißt einen Fehler auf.'; 


    }

}