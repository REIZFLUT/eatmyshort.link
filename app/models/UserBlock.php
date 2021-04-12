<?php

namespace App\Models;

use Continy\Core\Config;
use Continy\Core\Language;
use Continy\Core\Model;
use Continy\Core\MySQL;
use Continy\Core\Session;

class UserBlock extends Model
{

    public function __construct()
    {
        $this->init();
        $this->cleanup();
    }


    public function is($email)
    {
        $this->findByEmail($email);
        $is = (!empty($this->email)) ? true : false;
        self::setStatus($is);
        return $is;
    }

    public static function setStatus(bool $is)
    {
        if ($is) {
            Session::set('UserBlocked', true);
        } else {
            Session::unset('UserBlocked');
        }
    }

    public static function getStatus()
    {
        return Session::get('UserBlocked');
    }


    public function isIP()
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $result = MySQL::query('SELECT COUNT(*) as n FROM ' . $this->table . ' WHERE remote_ip = ?', [$ip]);
        $n = $result[0]['n'];
        $bl = Config::get('auth/blockIPWhen');


        if ($n >= $bl) {
            self::setStatus(true);
            return true;
        } else {
            self::setStatus(false);
            return false;
        }
    }

    public static function msg()
    {
        if (Session::exists('UserBlocked')) {
            return Language::get('login/error/blocked');
        } else {
            return NULL;
        }
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
        }
        return $result;
    }


    public static function cleanup()
    {
        $block_user_min   = intval(Config::get('auth/blockUser'));
        $block_user       = $block_user_min * 60;
        $block_expired_t  = time() - $block_user;
        $block_expired    = date('Y-m-d H:i:s', $block_expired_t);

        MySQL::query('DELETE FROM userblocks WHERE created_at < ?', [$block_expired]);
    }
}
