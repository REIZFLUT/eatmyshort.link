<?php

namespace App\Models;

class ListableUser extends User
{

    public function __construct()
    {
        $this->table = 'users';
        $this->init();
    }
}
