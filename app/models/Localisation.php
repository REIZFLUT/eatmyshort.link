<?php

namespace App\Models;

use Continy\Core\Model;
use Continy\Core\MySQL;

class Localisation extends Model
{

    public function __construct(bool $autoinit = false)
    {
        if ($autoinit) {
            $this->init();
        }
    }

    public function loadSlidLng(int $id, string $lng)
    {
        $fields = implode(', ', $this->fields);
        $query = 'SELECT ' . $fields . ' FROM ' . $this->table . ' WHERE sl_id = ? AND lng = ? LIMIT 0,1';
        $result = MySQL::query($query, [$id, $lng]);

        if ($result != false) {
            $this->import($result[0]);
        }

        return $result[0];
    }
}
