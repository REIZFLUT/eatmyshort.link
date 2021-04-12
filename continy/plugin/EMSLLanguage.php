<?php

namespace Continy\Plugin;

use App\Models\EMSLConfig;
use Continy\Core\Language;
use Continy\Core\Config;

class EMSLLanguage extends Language{

    public static function replace(string $message)
    {
        $result = preg_match_all('/\{\{(.*?)\}\}/is', $message, $matches);

        if ($result) {
            $n = count($matches[1]);
            for ($i = 0; $i < $n; $i++) {
                $match = trim($matches[1][$i]);
                if (strpos($match, ':')) {
                    list($type, $value) = explode(':', $match);
                    switch ($type) {
                        case 'config':
                        case 'Config':
                            if (!strpos($match, 'secret')) {
                                $replace = Config::get($value);
                                $message = str_replace($matches[0][$i], $replace, $message);
                            }
                            break;
                        case 'emslconfig':
                        case 'EMSLConfig':
                            $replace = EMSLConfig::get($value);
                            $message = str_replace($matches[0][$i], $replace, $message);
                        break;

                        default:
                            // TO DO: do nothing for now
                            break;
                    }
                } else {
                    // TO DO: do nothing for now
                }
            }
            return $message;
        } else {
            return $message;
        }
    }

}