<?php
// DEFAULT CONFIGURATION
use Continy\Core\App;

return [
    'app' => [
        'title'             => 'Continy App',
        'url'               => baseUrl(true),
        'methods'           => ['POST', 'PUT', 'PATCH', 'DELETE'], // allowed request methods, GET is allways allowed
        'mode'              => 'live'
    ],
    'paths' => [
        'routes'            => baseDir('/resources/routes.php'),
    ],
    'mysql' => [
        'host'              => '127.0.0.1',
        'port'              => '3306',
    ],

    'auth' => [
        'userModel'         => 'App\Models\User',
        'userAttemptModel'  => 'App\Models\UserAttempt',
        'tokenModel'        => 'App\Models\Token',
        'blockModel'        => 'App\Models\UserBlock',
        'userExpires'       => 5, //minutes
        'tokenExpires'      => 5, // minutes
        'maxLoginAttempts'  => 5, // attempts
        'blockUser'         => 5, //minutes
        'blockIP'           => 5, //minutes
        'blockIPWhen'       => 5, // when n Users are blocked from this IP
        'rolesAvailable'    => ['user', 'manager', 'admin'], // roles asignable to users
        'userDefaultRoles'  => ['user'], // role(s) a user gets automaticly after registration
        'superRoles'        => ['admin'] //role(s) a user can do everything with (be carefull!)
    ],

    'language' => [
        'mode'              => 'predict', //force; fallback language will be forced as main language
        'fallback'          => 'en',
        'supported'         => ['en', 'de']
    ],

];
