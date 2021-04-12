<?php

namespace Continy\Core;



class Model
{

    protected $table;
    protected $fields;
    protected $jstr = [];

    protected function init()
    {
        $this->autoSetTable();
        $this->autoSetFields();
    }


    public function findById($id)
    {

        $this->autoSetTable();
        $this->autoSetFields();

        $fields = (is_array($this->fields)) ? $this->fields : '*';
        $result = MySQL::selectById($this->table, $id, $fields);

        if ($result !== false) {
            foreach ($result as $key => $value) {
                if (in_array($key, $this->jstr)) {
                    $decoded = json_decode($value);
                    $this->$key = ($decoded != NULL) ? $decoded : [];
                } else {
                    $this->$key = $value;
                }
            }
        }
        return $result;
    }

    public function filtered(array $fields = [], string $type = '\stdClass')
    {
        if ($type == 'assoc') {
            $array = [];
            foreach ($this->fields as $key) {
                if (in_array($key, $fields)) {
                    if (isset($this->$key)) {
                        $array[$key] = $this->$key;
                    }
                }
            }
            return $array;
        } elseif ($type == 'array') {
            $array = [];
            foreach ($this->fields as $key) {
                if (in_array($key, $fields)) {
                    if (isset($this->$key)) {
                        $array[] = $this->$key;
                    }
                }
                return $array;
            }
        } else {
            $object = new $type;
            foreach ($this->fields as $key) {
                if (in_array($key, $fields)) {
                    if (isset($this->$key)) {
                        $object->$key = $this->$key;
                    }
                }
            }
            return $object;
        }
    }

    public function create()
    {

        $this->autoSetTable();
        $this->autoSetFields();

        $insert = [];

        foreach ($this->fields as $key) {
            if (!empty($this->$key)) {
                $insert[$key] = (in_array($key, $this->jstr)) ? json_encode($this->$key) : $this->$key;
            }
        }

        $this->id = MYSQL::insert($this->table, $insert);
    }

    public function update()
    {

        $this->autoSetTable();
        $this->autoSetFields();

        if ($this->id > 0) {
            $update = [];

            foreach ($this->fields as $key) {
                $update[$key] = (in_array($key, $this->jstr)) ? json_encode($this->$key) : $this->$key;
            }

            return MYSQL::updateById($this->table, $this->id, $update);
        } else {
            return false;
        }
    }

    public function selfDelete()
    {
        $this->autoSetTable();
        $this->autoSetFields();

        if ($this->id > 0) {
            //echo 'self delete'.$this->table.':'.$this->id;;
            MYSQL::deleteById($this->table, $this->id);
        } else {
            return false;
        }
    }

    public function getTable(){
        return $this->table;
    }

    public function getFields(){
        return $this->fields;
    }

    public function import($assoc){
        foreach($assoc as $key => $val){
            $this->$key = $val;
        }
    }

    private function autoSetTable()
    {
        if (empty($this->table)) {
            $cn = strtolower(get_called_class());
            $pn = explode('\\', $cn);
            $auto_table = pluralize(array_pop($pn));
            $this->table = $auto_table;
        }

        return $this->table;
    }

    private function autoSetFields()
    {

        if ($this->fields == NULL) {
            $cols = MySQL::showColumns($this->table, true);
            $this->fields = $cols;
        }

        return $this->fields;
    }


}
