<?php

namespace App\Models;

use Continy\Core\Auth;
use Continy\Core\Model;
use Continy\Core\MySQL;
use Continy\Core\Config;

class PasswordReset extends Model
{


    public function __construct()
    {
        $this->init();
    }

    public function findByToken(string $token)
    {
        $result = MySQL::selectById($this->table, $token, $this->fields, 'token');

        if ($result != false) {
            $this->id       = $result[0]['id'];
            $this->email    = $result[0]['email'];
            $this->token    = $result[0]['token'];
            $this->ts       = $result[0]['ts'];
        }

        return $this;
    }

    public function autoCreate($email)
    {
        $this->token = Auth::makeToken();
        $this->email = $email;
        $this->create();
    }

    public static function cleanUp()
    {

        $ea = intval(Config::get('auth/resetTokenExpires')) * 60;
        $ts = time() - $ea;
        $expired = date('Y-m-d H:i:s', $ts);

        MySQL::query('DELETE FROM passwordresets WHERE ts < ?', [$expired]);
    }

    public static function valid(string $email, string $token){
        $result = MySQL::query('SELECT id, email, token, ts FROM passwordresets WHERE email = ? AND token = ?', [$email, $token]);
        if(count($result) > 0){
            return true;
        } else {
            return false;
        }
    }

    public static function remove(string $email){
        MySQL::query('DELETE FROM passwordresets WHERE email = ?', [$email]);
    }
}
