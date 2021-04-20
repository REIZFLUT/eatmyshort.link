<?php

namespace Continy\Core;

class BrowserLanguage{

    private $lngs = [];

    public function __construct()
    {
        if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
           $blng = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        } else {
            $blng = Config::get('language/fallback');
        }

        $b_acc_lngs = explode(',', $blng);

        foreach($b_acc_lngs as $bal){
            $bale = explode(';', $bal);
            $baln = count($bale);
            
            if($baln > 1){

                for($i=1;$i<$baln;$i++){
                    list($key, $val) = explode('=', $bale[$i]);
                    $this->lngs[$bale[0]] = ($key == 'q')? [$key => floatval($val)]:[$key => $val];
                }
            } else {
                $this->lngs[$bale[0]] = ['q' => floatval(1)];
            }
        }
    }

    public function getLngs(){
        return $this->lngs;
    }

    public function getMainLng(){

        $main = Config::get('language/fallback');
        $q = 0;

        foreach($this->lngs as $lngKey => $lngVal){
            if(isset($lngVal['q'])){
                if($lngVal['q'] > $q){
                    $q = $lngVal['q'];
                    $main = $lngKey;
                }
            }
        }
        return $main;
    }

    public function bestMatch(array $lngs){

        $bestMatch = Config::get('language/fallback');
        $q = 0;

        foreach($lngs as $lng){
            if(array_key_exists($lng, $this->lngs)){
                if(isset($this->lngs[$lng]['q'])){
                    if($this->lngs[$lng]['q'] > $q){
                        $bestMatch = $lng;
                        $q = $this->lngs[$lng]['q'];
                    }
                }
            }
        }

        return ['lng' => $bestMatch, 'q' => $q];

    }

}