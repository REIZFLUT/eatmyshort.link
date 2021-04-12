<?php

namespace App\Models;

use Continy\Core\Model;

class UserAttempt extends Model
{

    public function __construct()
    {
        $this->init();
    }

    public function toBlock()
    {
    }
}
