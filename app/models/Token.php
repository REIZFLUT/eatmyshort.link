<?php

namespace App\Models;

use Continy\Core\MySQL;
use Continy\Core\Model;
use Continy\Core\Config;
use App\Models\User;

class Token extends Model
{

    public function __construct($value = '')
    {
        $this->init();

        if (!empty($value)) {
            $this->value = $value;
            $result = $this->findByValue($value);
            if ($result == false) $this->id = 0;
        }
    }

    public function isValid()
    {
        if ($this->id > 0) {
            if (!$this->isExpired()) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function isExpired()
    {

        $ea = intval(Config::get('auth/tokenExpires')) * 60;
        $ts = strtotime($this->ts);
        $expires = $ts + $ea;

        if (time() > $expires) {
            $this->selfDelete();
            return true;
        } else {
            return false;
        }
    }

    public function findByValue($value)
    {
        $fields = (is_array($this->fields)) ? $this->fields : '*';
        $result = MySQL::selectById($this->table, $value, $fields, 'value');

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

    public function getUser()
    {
        $user = new User(false);
        if (isset($this->user_issued)) {
            $user->findById($this->user_issued);
        }
        return $user;
    }

    public static function cleanUp()
    {
        $ea = intval(Config::get('auth/tokenExpires')) * 60;
        $ts = time() - $ea;
        $expired = date('Y-m-d H:i:s', $ts);

        MySQL::query('DELETE FROM tokens WHERE ts < ?', [$expired]);
    }
}
