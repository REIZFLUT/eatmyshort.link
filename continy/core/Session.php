<?php

namespace Continy\Core;

class Session
{

    public static function init()
    {
        session_name(hash('sha256', 'CONTINY_SESSION' . Config::get('app/secret')));
        session_start();


        $_SESSION['__CSRF_Token'] = (isset($_SESSION['__CSRF_Token'])) ?
            $_SESSION['__CSRF_Token'] : hash('sha256', 'CONTINY_SESSION' . Config::get('app/secret') . microtime());

        if (!isset($_SESSION['canary'])) {
            session_regenerate_id(true);
            $_SESSION['canary'] = time();
        }
        // Regenerate session ID every five minutes:
        if ($_SESSION['canary'] < time() - 300) {
            session_regenerate_id(true);
            $_SESSION['canary'] = time();
        }

        self::unique_impression();
    }

    private static function unique_impression()
    {
        if (isset($_SESSION['uniqueImpression'])) {
            $oldUniqueHash = $_SESSION['uniqueImpression'];
            unset($_SESSION['uniqueData_' . $oldUniqueHash]);
            unset($_SESSION['uniqueOldVal_' . $oldUniqueHash]);
        }

        if (isset($_SESSION['nextUniqueImpression'])) {
            $_SESSION['uniqueImpression'] = $_SESSION['nextUniqueImpression'];
            unset($_SESSION['nextUniqueImpression']);
        } else {
            $_SESSION['uniqueImpression'] = hash('sha256', uniqid(Config::get('app/secret')));
        }
    }

    public static function next(array $data = [], array $oldVal = [])
    {
        if (!isset($_SESSION['nextUniqueImpression'])) {
            $hash = hash('sha256', uniqid(Config::get('app/secret')));
            $_SESSION['nextUniqueImpression'] = $hash;
        } else {
            $hash = $_SESSION['nextUniqueImpression'];
        }

        if (count($data) > 0) {
            $_SESSION['uniqueData_' . $hash] = $data;
        }

        if (count($oldVal) > 0) {
            $_SESSION['uniqueOldVal_' . $hash] = $oldVal;
        }

        return [$hash, $data];
    }

    public static function addNext(array $data = [], array $oldVal = [])
    {
        if (isset($_SESSION['nextUniqueImpression'])) {
            $hash = $_SESSION['nextUniqueImpression'];

            foreach ($data as $key => $value) {
                $_SESSION['uniqueData_' . $hash][$key] = $value;
            }

            foreach ($oldVal as $key => $value) {
                $_SESSION['uniqueOldVal_' . $hash][$key] = $value;
            }
        } else {
            self::next($data);
        }
    }


    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public static function unset($key)
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    public static function get($key = '')
    {
        if (empty($key)) {
            return $_SESSION;
        } else {
            return (isset($_SESSION[$key])) ? $_SESSION[$key] : NULL;
        }
    }

    public static function getAssoc($key, $subkey)
    {
        if (isset($_SESSION[$key][$subkey])) {
            return $_SESSION[$key][$subkey];
        } else {
            return NULL;
        }
    }

    public static function exists($key)
    {
        return isset($_SESSION[$key]);
    }

    public static function existsAssoc($key, $subkey)
    {
        return isset($_SESSION[$key][$subkey]);
    }
}
