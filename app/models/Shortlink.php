<?php

namespace App\Models;

use Continy\Core\Collection;
use Continy\Core\Language;
use Continy\Core\Model;
use Continy\Core\MySQL;

class Shortlink extends Model
{

    public function __construct(bool $autoinit = false)
    {
        if ($autoinit) {
            $this->init();
        }
    }

    public static function pathExists($path)
    {
        $path = filter_var($path, FILTER_SANITIZE_URL);
        $result = MYSQL::query('SELECT COUNT(*) as n FROM shortlinks WHERE from_path = ?', [$path]);
        $n = ($result !== false) ? $result[0]['n'] : 0;
        return ($n > 0) ? true : false;
    }


    public function loadFromPath($path)
    {
        $result = MySQL::selectById($this->table, $path, $this->fields, 'from_path');
        if ($result !== false) {
            $this->import($result);
        }
    }

    public static function sanitziePath($path)
    {
        $path = trim($path);
        $path = strtolower($path);
        $path = str_replace(['/', '\\', "\n", "\t"], '', $path);
        $path = urlencode($path);
        $path = str_replace(['+', '%E3%A4', '%E3%84', '%E3%B6', '%E3%96', '%E3%BC', '%E3%9C', '%E3%FF',], ['-', 'ae', 'ae', 'oe', 'oe', 'ue', 'ue', 'ss'], $path);

        $path = filter_var($path, FILTER_SANITIZE_URL);
        echo $path;
        return $path;
    }

    public function hasLocals()
    {
        $result = MySQL::query('SELECT COUNT(*) as n FROM localisations WHERE sl_id = ?', [$this->id]);
        if ($result == false) {
            $this->hasLocals = false;
            return false;
        } else {
            $this->hasLocals = true;
            return ($result[0]['n'] > 0) ? true : false;
        }
    }

    public function hasLocal($lng)
    {
        $result = MySQL::query('SELECT COUNT(*) as n FROM localisations WHERE sl_id = ? AND lng = ?', [$this->id, $lng]);
        if ($result == false) {
            $this->hasLocals = false;
            return false;
        } else {
            $this->hasLocals = true;
            return ($result[0]['n'] > 0) ? true : false;
        }
    }

    public function locals()
    {
        if (!isset($this->locals)) {
            $this->locals = $this->getLocals();
        }
        return $this->locals;
    }

    public function getLocals()
    {
        $collection = new Collection('Localisation');
        $collection->allFromForeignId('sl_id', $this->id);
        return $collection;
    }

    public function getLocalCodes(){
        $result = MySQL::query('SELECT lng FROM localisations WHERE sl_id = ?', [$this->id]);
        if ($result == false) {
            return [];
        } else{
            $codes = [];
            foreach($result as $local){
                $codes[] = $local['lng'];
            }
            return $codes;
        }
    }

}
