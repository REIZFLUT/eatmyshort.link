<?php
use Continy\Core\Language; 
use Continy\Core\Config;

echo Language::get('login/forgotmail/title')."\n";
echo Language::get('login/forgotmail/description')."\n"."\n";
echo Config::get('app/url').'/login/reset?token='.$reset->token 

?>
