<?php

return [

	// WEB Routes

	'public' => [
		'prefix'  => '',
		'http404' => 'controller:ShortlinkController@forward',
		'auth' => [
			'user' => ['public'], // magic role "public" is asigned to every visitor
		],
		'routes' => [
			['GET',  '/', 	   'ShortlinkController@forward'],
			
			['GET',  '/login', 'LoginController@index'],
			['POST', '/login', 'LoginController@login'],
		]
	],

	'loggedin' => [
		'prefix'  => '',
		'http403' => 'logout_redirect:/login',
		'auth' => [
			'user' => ['user', 'manager'],
		],
		'routes'  => [			
			['GET', 	'/shortlink', 			'ShortlinkController@index'],
			['POST', 	'/shortlink/create', 	'ShortlinkController@create'],

			['GET', 	'/shortlink/edit/{id}', 'ShortlinkController@edit'],
			['POST', 	'/shortlink/edit/{id}', 'ShortlinkController@update'],

			['DELETE', 	'/shortlink/delete/{id}', 'ShortlinkController@delete'],

			['GET', 	'/shortlink/localisation/edit/{id}', 'LocalisationController@edit'],
			['POST', 	'/shortlink/localisation/edit/{id}', 'LocalisationController@update'],

			['DELETE', 	'/shortlink/localisation/delete/{id}', 'LocalisationController@delete'],

			['GET', 	'/user/account', 		'UserController@myAccount'],
			['POST', 	'/user/account', 		'UserController@updateMyAccount'],

			['GET', 	'/language/{lng}', 		'DefaultController@languageSwitch'],
			['GET',  	'/logout', 				'LoginController@logout']
		]
	],

	'admin' => [
		'prefix'  => '',
		'auth' => [
			'user' => ['manager'],
		],
		'routes'  => [			
			['GET', 	'/user', 			 'UserController@index'],

			['GET', 	'/user/create', 	 'UserController@create'],
			['POST', 	'/user/create', 	 'UserController@make'],

			['GET', 	'/user/edit/{id}', 	 'UserController@edit'],
			['POST',	'/user/edit/{id}', 	 'UserController@update'],

			['DELETE',	'/user/delete/{id}', 'UserController@delete'],

			['GET', 	'/setting', 		 'SettingsController@index'],
			['GET', 	'/setting/edit/{id}','SettingsController@edit'],
			['POST', 	'/setting/edit/{id}','SettingsController@update']
		]
	],	
	

	// API Routes
/*
	'api' => [
		'prefix' => 'api',
		'csrf' => false,
		'http403' => 'controller:DefaultController@ApiHttp403',
		'http404' => 'controller:DefaultController@ApiHttp404',
		'auth' => [
			'token' => ['user'],
		],
		'routes' => [
			['GET',  '/v1/test/{id}', 'TestController@api'],
			['PATCH',  '/v1/test/{id}/update', 'TestController@apiEdit'],
		]
	],

	'publicapi' => [
		'prefix' => 'api',
		'csrf' => false,
		'routes' => [
			['POST', '/v1/auth', 'ApiController@requestToken']
		]
	]
	*/
];
