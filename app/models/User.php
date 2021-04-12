<?php

namespace App\Models;

use Continy\Core\Model;
use Continy\Core\MySQL;
use Continy\Core\Session;


class User extends Model
{

    protected $jstr = ['roles'];

    public function __construct($autofind = true)
    {
        $this->init();
        if ($autofind) {
            $this->autoFind();
        }
    }

    public function autoFind()
    {
        if (Session::exists('USER_ID')) {
            $id = Session::get('USER_ID');
            $result = $this->findById($id);

            if (($result == false)) {
                $this->id = 0;
                $this->roles = [];
            }
        } else {
            $this->id = 0;
            $this->roles = [];
        }
    }

    public function logOut()
    {
        Session::unset('USER_ID');
        $this->setAuthInactive();
    }

    public function findByEmail(string $email)
    {

        $fields = (is_array($this->fields)) ? $this->fields : '*';
        $result = MySQL::selectById($this->table, $email, $fields, 'email');

        if ($result !== false) {
            foreach ($result as $key => $value) {
                if (in_array($key, $this->jstr)) {
                    $decoded = json_decode($value);
                    $this->$key = ($decoded != NULL) ? $decoded : [];
                } else {
                    $this->$key = $value;
                }
            }
        } else {
            $this->id = 0;
        }
        return $result;
    }

    public function hasARoleOf(array $roles)
    {
        foreach ($this->roles as $role) {
            if (in_array($role, $roles)) return true;
        }
    }

    public function roleIfHas($role)
    {
        return (in_array($role, $this->roles)) ? $role : NULL;
    }

    public function setNewPwd($input)
    {
        $this->passwd = password_hash($input, PASSWORD_DEFAULT);
        return $this->passwd;
    }

    public function hashPwd($input)
    {
        return password_hash($input, PASSWORD_DEFAULT);
    }

    public function verifyPwd($input)
    {
        return password_verify($input, $this->passwd);
    }

    public function setAuthActive()
    {
        $this->auth_active = 1;
        $this->last_active = date('Y-m-d H:i:s');
        $this->update();
    }

    public function setAuthInactive()
    {
        $this->auth_active = 0;
        $this->last_active = NULL;
        $this->update();
    }

    public function clearAttempts()
    {
        MYSQL::query('DELETE FROM userattempts WHERE email = ?', [$this->email]);
    }

    public static function hasRole(string $role)
    {
        if ($role == 'public') {
            return true;
        } else {
            $user = new User();
            return (in_array($role, $user->roles) || in_array('admin', $user->roles)) ? true : false;
        }
    }

    public static function exists(string $email){
        $email = filter_var($email, FILTER_SANITIZE_STRING);
        $result = MySQL::selectById('users', [$email], 'id', 'email');
        if($result == false){
            return false;
        } else {
            return (count($result) > 0) ? true:false;
        }
        

    }
}
