<?php

namespace Continy\Core;

class OldVal
{

    public static function get($key)
    {
        $hash = Session::get('uniqueImpression');
        return Session::getAssoc('uniqueOldVal_' . $hash, $key);
    }

    public static function exists($key)
    {
        $hash = Session::get('uniqueImpression');
        return Session::existsAssoc('uniqueOldVal_' . $hash, $key);
    }

    public static function getOr($key, $altVal)
    {
        if (self::exists($key)) {
            return self::get($key);
        } else {
            return $altVal;
        }
    }
}