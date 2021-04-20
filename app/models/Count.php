<?php

namespace App\Models;

use Continy\Core\BrowserLanguage;
use Continy\Core\Language;
use Continy\Core\Model;
use Continy\Plugin\Browser;

class Count extends Model
{

    protected $table;

    public function __construct()
    {
        $this->table = 'counter';
        $this->init();
    }

    public function auto()
    {
        if (self::isUsed()) {
            $browser = new Browser();
            $browserLang = new BrowserLanguage();
            $this->browser_name     = $browser->getBrowser();
            $this->browser_version  = $browser->getVersion();
            $this->platform         = $browser->getPlatform();

            $device = 'desktop';
            if ($browser->isMobile()) {
                $device = 'mobile';
            } elseif ($browser->isTablet()) {
                $device = 'tablet';
            } elseif ($browser->isRobot()) {
                $device = 'robot';
            }

            $this->device = (isset($this->device)) ? $this->device:$device;
            $this->remote_ip = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : $this->remote_ip;
            $this->lang = (isset($this->lang)) ? $this->lang:$browserLang->getMainLng();

            if (self::countryOnForward()) {
                if (filter_var($this->remote_ip, FILTER_VALIDATE_IP)) {
                    $this->country = self::getCountryFromIp($this->remote_ip);
                }
            }

            $this->create();
            return true;
        } else {
            return false;
        }
    }

    public static function getCountryFromIp(string $ip)
    {

        if (!empty($ip)) {
            $geoUrl = EMSLConfig::get('counterGeoplugin');
            $url = str_replace('{ip}', $ip, $geoUrl);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            curl_close($ch);

            $object = json_decode($output);
            if (is_object($object)) {
                return $object->geoplugin_countryCode;
            } else {
                return '';
            }
        } else {
            return '';
        }
    }

    public static function isUsed()
    {
        $is = intval(EMSLConfig::get('useCounter'));
        return ($is > 0) ? true : false;
    }

    public static function countryOnForward()
    {
        $is = intval(EMSLConfig::get('counterCountryOnForward'));
        return ($is > 0) ? true : false;
    }
}
