<?php

namespace Continy\Core;


class Notification
{
    public static function get($key)
    {
        $hash = Session::get('uniqueImpression');
        return Session::getAssoc('uniqueData_' . $hash, $key);
    }

    public static function toast($key, $class = '', $attrAssoc = [])
    {

        $content = self::get($key);

        if (!empty($content)) {
            $output  = '<div ';
            $output .= (!empty($class)) ? 'class="' . $class . '"' : '';
            foreach ($attrAssoc as $key => $val) {
                $output .= $key . '="' . $val . '"';
            }
            $output .= '>' . $content . '</div>' . "\n";
            return $output;
        } else {
            return '';
        }
    }
}
