<?php

namespace App\Models;

use Continy\Core\Model;
use Continy\Core\MySQL;

class EMSLConfig extends Model{

    public function __construct()
    {
        $this->table = 'config';
        $this->init();
    }


    public static function get($key){
       $result = MYSQL::selectById('config', $key, ['k', 'v'], 'k');
       if($result !== false){
           return $result['v'];
       } else {
           return '';
       }
    }

    public static function bool($key){
        $result = self::get($key);
        return ($result) ? true:false;
    }


}