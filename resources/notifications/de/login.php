<?php

return [
    'placeholder' => [
        'email'         => 'E-Mail',
        'password'      => 'Passwort'
    ],
    'error' => [
        'failed'        => 'Login fehlgeschlagen. Bitte versuche es nocheinmal.',
        'blocked'       => 'Zu vile Login-Versuche. Du bist für {{config:blockUser}} Minuten beblockt.',
        'unknownEmail'  => 'Unbekannte E-Mail'
    ],
    'success' => [
        'resetMailSent' => 'Wiederherstellungs-E-Mail wurde verschickt.'
    ],
    'button' => [
        'submit'        => 'Anmelden',
        'resetPwd'      => 'Passwort zurücksetzen'
    ],
    'link' => [
        'forgotPwd'     => 'Passwort vergessen',
        'back'          => 'Zurück zum Login'
    ],
    'forgotmail'      => [
        'subject'       => 'Bitte setzte dein Passwort zurück',
        'title'         => 'Passwort zurücksetzen',
        'description'   => 'Du erhältst diese E-Mail, weil auf der Seite {{Config:app/url}} ein Antrag, zum Zurücksetzen deines Passworts gestellt wurde. Der Link ist {{Config:auth/resetTokenExpires}} Minuten gültig.',
        'clickThisLink' => 'Hier klicken um das Passwort zurückzusetzen',
        'companyName'   => '{{EMSLConfig:company}}',
        'companyAddress' => '{{EMSLConfig:companyAddress}}'
    ]
];
