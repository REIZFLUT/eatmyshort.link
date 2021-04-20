<?php

namespace Continy\Core;

class Language
{

    private static $LNG;
    private static $code;
    private static $lngCodes;

    public static function init()
    {
        $mode     = Config::get('language/mode'); //predict, force
        $fallback = Config::get('language/fallback'); // 2 char
        $supported = Config::get('language/supported');
        self::laodAvailableLanguageCodes();

        switch ($mode) {
            case 'force':
                if (!isset($_SESSION['LANGUAGE'])) {
                    if (in_array($fallback, $supported)) {
                        $_SESSION['LANGUAGE'] = strtolower($fallback);
                    } else {
                        die('Could not set language. Language not supported');
                    }
                }
                break;
            default:
            case 'predict':
                $browser_lng = new BrowserLanguage();
                if (!isset($_SESSION['LANGUAGE'])) {
                    $useLng = $browser_lng->bestMatch($supported);
                    $_SESSION['LANGUAGE'] = $useLng['lng'];
                }
                break;
        }

        self::$code = $_SESSION['LANGUAGE'];
        self::loadLng();
    }


    private static function loadLng()
    {

        $dirpath = resourcesDir('/notifications/' . self::$code);
        $dir = array_diff(scandir($dirpath), ['..', '.']);

        foreach ($dir as $file) {
            $key = str_replace('.php', '', $file);
            $value = require_once $dirpath . '/' . $file;
            self::$LNG[$key] = $value;
        }
    }

    public static function get($path = '')
    {
        if ($path == '') {
            return self::$LNG;
        } else {
            $keys = explode('/', $path);
            $array = self::$LNG;
            $result = NULL;

            foreach ($keys as $key) {
                $checked = self::check_recursive($array, $key);
                if (is_array($checked)) {
                    $array  = $checked;
                    $result = $checked;
                } else {
                    $result = $checked;
                }
            }
            $result = ($result == NULL) ? '' : $result;

            return self::replace($result);
        }
    }

    public static function routeLink($lngPath, $routePath, $attrAssoc = [])
    {
        $content = self::get($lngPath);
        $route = Config::get('app/url') . $routePath;
        $output = '<a href="' . $route . '"';

        foreach ($attrAssoc as $key => $val) {
            $output .= ' ' . $key . '="' . $val . '"';
        }

        $output .= '>' . $content . '</a>';

        return $output;
    }


    public static function exists($path = '')
    {
        return !empty(self::get($path));
    }


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

    private static function check_recursive($array, $key)
    {
        if (is_array($array)) {
            if (array_key_exists($key, $array)) {
                return $array[$key];
            } else {
                return NULL;
            }
        } else {
            return $array;
        }
    }

    public function lngCodeExists(string $key)
    {
        return array_key_exists($key, self::$lngCodes);
    }

    public static function getAllLngCodes()
    {
        self::laodAvailableLanguageCodes();
        return self::$lngCodes;
    }

    public static function getExistingLngCodes(array $makeAvailable)
    {
        self::laodAvailableLanguageCodes();
        $available = [];

        foreach ($makeAvailable as $ma) {
            if (array_key_exists($ma, self::$lngCodes)) {
                $available[$ma] = self::$lngCodes[$ma];
            }
        }

        return $available;
    }

    private static function laodAvailableLanguageCodes()
    {
        // pushed this to the end, for convinience
        if (!isset(self::$lngCodes)) {
            self::$lngCodes = [
                'af' =>    'Afrikaans',
                'sq' =>    'Albanian',
                'an' =>    'Aragonese',
                'ar' =>    'Arabic (Standard)',
                'ar-dz' => 'Arabic (Algeria)',
                'ar-bh' => 'Arabic (Bahrain)',
                'ar-eg' => 'Arabic (Egypt)',
                'ar-iq' => 'Arabic (Iraq)',
                'ar-jo' => 'Arabic (Jordan)',
                'ar-kw' => 'Arabic (Kuwait)',
                'ar-lb' => 'Arabic (Lebanon)',
                'ar-ly' => 'Arabic (Libya)',
                'ar-ma' => 'Arabic (Morocco)',
                'ar-om' => 'Arabic (Oman)',
                'ar-qa' => 'Arabic (Qatar)',
                'ar-sa' => 'Arabic (Saudi Arabia)',
                'ar-sy' => 'Arabic (Syria)',
                'ar-tn' => 'Arabic (Tunisia)',
                'ar-ae' => 'Arabic (U.A.E.)',
                'ar-ye' => 'Arabic (Yemen)',
                'hy' =>    'Armenian',
                'as' =>    'Assamese',
                'ast' =>   'Asturian',
                'az' =>    'Azerbaijani',
                'eu' =>    'Basque',
                'bg' =>    'Bulgarian',
                'be' =>    'Belarusian',
                'bn' =>    'Bengali',
                'bs' =>    'Bosnian',
                'br' =>    'Breton',
                'bg' =>    'Bulgarian',
                'my' =>    'Burmese',
                'ca' =>    'Catalan',
                'ch' =>    'Chamorro',
                'ce' =>    'Chechen',
                'zh' =>    'Chinese',
                'zh-hk' => 'Chinese (Hong Kong)',
                'zh-cn' => 'Chinese (PRC)',
                'zh-sg' => 'Chinese (Singapore)',
                'zh-tw' => 'Chinese (Taiwan)',
                'cv' =>    'Chuvash',
                'co' =>    'Corsican',
                'cr' =>    'Cree',
                'hr' =>    'Croatian',
                'cs' =>    'Czech',
                'da' =>    'Danish',
                'nl' =>    'Dutch (Standard)',
                'nl-be' => 'Dutch (Belgian)',
                'en' =>    'English',
                'en-au' => 'English (Australia)',
                'en-bz' => 'English (Belize)',
                'en-ca' => 'English (Canada)',
                'en-ie' => 'English (Ireland)',
                'en-jm' => 'English (Jamaica)',
                'en-nz' => 'English (New Zealand)',
                'en-ph' => 'English (Philippines)',
                'en-za' => 'English (South Africa)',
                'en-tt' => 'English (Trinidad & Tobago)',
                'en-gb' => 'English (United Kingdom)',
                'en-us' => 'English (United States)',
                'en-zw' => 'English (Zimbabwe)',
                'eo' =>    'Esperanto',
                'et' =>    'Estonian',
                'fo' =>    'Faeroese',
                'fa' =>    'Farsi',
                'fj' =>    'Fijian',
                'fi' =>    'Finnish',
                'fr' =>    'French (Standard)',
                'fr-be' => 'French (Belgium)',
                'fr-ca' => 'French (Canada)',
                'fr-fr' => 'French (France)',
                'fr-lu' => 'French (Luxembourg)',
                'fr-mc' => 'French (Monaco)',
                'fr-ch' => 'French (Switzerland)',
                'fy' =>    'Frisian',
                'fur' =>   'Friulian',
                'gd' =>    'Gaelic (Scots)',
                'gd-ie' => 'Gaelic (Irish)',
                'gl' =>    'Galacian',
                'ka' =>    'Georgian',
                'de' =>    'German (Standard)',
                'de-at' => 'German (Austria)',
                'de-de' => 'German (Germany)',
                'de-li' => 'German (Liechtenstein)',
                'de-lu' => 'German (Luxembourg)',
                'de-ch' => 'German (Switzerland)',
                'el' =>    'Greek',
                'gu' =>    'Gujurati',
                'ht' =>    'Haitian',
                'he' =>    'Hebrew',
                'hi' =>    'Hindi',
                'hu' =>    'Hungarian',
                'is' =>    'Icelandic',
                'id' =>    'Indonesian',
                'iu' =>    'Inuktitut',
                'ga' =>    'Irish',
                'it' =>    'Italian (Standard)',
                'it-ch' => 'Italian (Switzerland)',
                'ja' =>    'Japanese',
                'kn' =>    'Kannada',
                'ks' =>    'Kashmiri',
                'kk' =>    'Kazakh',
                'km' =>    'Khmer',
                'ky' =>    'Kirghiz',
                'tlh' =>   'Klingon',
                'ko' =>    'Korean',
                'ko-kp' => 'Korean (North Korea)',
                'ko-kr' => 'Korean (South Korea)',
                'la' =>    'Latin',
                'lv' =>    'Latvian',
                'lt' =>    'Lithuanian',
                'lb' =>    'Luxembourgish',
                'mk' =>    'FYRO Macedonian',
                'ms' =>    'Malay',
                'ml' =>    'Malayalam',
                'mt' =>    'Maltese',
                'mi' =>    'Maori',
                'mr' =>    'Marathi',
                'mo' =>    'Moldavian',
                'nv' =>    'Navajo',
                'ng' =>    'Ndonga',
                'ne' =>    'Nepali',
                'no' =>    'Norwegian',
                'nb' =>    'Norwegian (Bokmal)',
                'nn' =>    'Norwegian (Nynorsk)',
                'oc' =>    'Occitan',
                'or' =>    'Oriya',
                'om' =>    'Oromo',
                'fa' =>    'Persian',
                'fa-ir' => 'Persian/Iran',
                'pl' =>    'Polish',
                'pt' =>    'Portuguese',
                'pt-br' => 'Portuguese (Brazil)',
                'pa' =>    'Punjabi',
                'pa-in' => 'Punjabi (India)',
                'pa-pk' => 'Punjabi (Pakistan)',
                'qu' =>    'Quechua',
                'rm' =>    'Rhaeto-Romanic',
                'ro' =>    'Romanian',
                'ro-mo' => 'Romanian (Moldavia)',
                'ru' =>    'Russian',
                'ru-mo' => 'Russian (Moldavia)',
                'sz' =>    'Sami (Lappish)',
                'sg' =>    'Sango',
                'sa' =>    'Sanskrit',
                'sc' =>    'Sardinian',
                'gd' =>    'Scots Gaelic',
                'sd' =>    'Sindhi',
                'si' =>    'Singhalese',
                'sr' =>    'Serbian',
                'sk' =>    'Slovak',
                'sl' =>    'Slovenian',
                'so' =>    'Somani',
                'sb' =>    'Sorbian',
                'es' =>    'Spanish',
                'es-ar' => 'Spanish (Argentina)',
                'es-bo' => 'Spanish (Bolivia)',
                'es-cl' => 'Spanish (Chile)',
                'es-co' => 'Spanish (Colombia)',
                'es-cr' => 'Spanish (Costa Rica)',
                'es-do' => 'Spanish (Dominican Republic)',
                'es-ec' => 'Spanish (Ecuador)',
                'es-sv' => 'Spanish (El Salvador)',
                'es-gt' => 'Spanish (Guatemala)',
                'es-hn' => 'Spanish (Honduras)',
                'es-mx' => 'Spanish (Mexico)',
                'es-ni' => 'Spanish (Nicaragua)',
                'es-pa' => 'Spanish (Panama)',
                'es-py' => 'Spanish (Paraguay)',
                'es-pe' => 'Spanish (Peru)',
                'es-pr' => 'Spanish (Puerto Rico)',
                'es-es' => 'Spanish (Spain)',
                'es-uy' => 'Spanish (Uruguay)',
                'es-ve' => 'Spanish (Venezuela)',
                'sx' =>    'Sutu',
                'sw' =>    'Swahili',
                'sv' =>    'Swedish',
                'sv-fi' => 'Swedish (Finland)',
                'sv-sv' => 'Swedish (Sweden)',
                'ta' =>    'Tamil',
                'tt' =>    'Tatar',
                'te' =>    'Teluga',
                'th' =>    'Thai',
                'tig' =>   'Tigre',
                'ts' =>    'Tsonga',
                'tn' =>    'Tswana',
                'tr' =>    'Turkish',
                'tk' =>    'Turkmen',
                'uk' =>    'Ukrainian',
                'hsb' =>   'Upper Sorbian',
                'ur' =>    'Urdu',
                've' =>    'Venda',
                'vi' =>    'Vietnamese',
                'vo' =>    'Volapuk',
                'wa' =>    'Walloon',
                'cy' =>    'Welsh',
                'xh' =>    'Xhosa',
                'ji' =>    'Yiddish',
                'zu' =>    'Zulu',
            ];
        }
    }
}
