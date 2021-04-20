<?php

return [
    'placeholder' => [
        'from_path'     => 'Dein Shortlink',
        'to_url'        => 'Weiterleitung zu https://...',
        'q'             => 'Suchbegriff',
        'email'         => 'E-Mail',
        'firstname'     => 'Vorname',
        'lastname'      => 'Nachname',

        'password_admin'  => 'Dein Administrator-Passwort',
        'password_old'  => 'Das aktuelle Passwort',
        'password_new'  => 'Ein neues Passwort',
        'password_cfm'  => 'Neues Passwort wiederholen'
    ],
    'label' => [
        'change-password' => 'Passwort ändern',
        'your-roles'    => 'Deine Benutzerrollen',
        'my-account'  => 'Mein Account',
        'user-account'  => 'Benutzer-Account',
        'user-account-create'  => 'Benutzer-Account erstellen',
        'user-roles' => 'Benutzer-Rollen',
        'setting'    => 'Einstellung'
    ],
    'description' => [
        'change-password' => 'Um dein Passwort zu ändern, gib dein altes Passwort ein. Und dann zweimal das neue Passwort. Das Passwort muss min. 8 Zeichen lang sein und Buchstaben und Zahlen beinhalten.',
        'change-password-admin' => 'Um das Benutzer-Passwort zu ändern, gib dein Administrator-Passwort ein. Und dann zweimal das neue Passwort. Das Passwort muss min. 8 Zeichen lang sein und Buchstaben und Zahlen beinhalten.',
        'your-roles' => 'Deine Benutzerrollen beeinflussen, was du machen darfst. Wenn du mehr Rechte benötigst, wende dich an deinen Admin.',
        'setting' => [
            'counterCountryOnForward' => '
                                          <p><strong>Erlaubte Werte: 1 oder 0</strong></p>
                                          <p>Diese Einstellung beeinflusst, ob beim Zählvorgang der Weiterleitung
                                          das Geo-Plugin aufgerufen wird, um die Länder-Herkunft des Klicks zu bestimmen.
                                          Da hier auf einen weiteren HTTP Request gewartet werden muss, wirkt sich diese Einstellung
                                          auf die Geschwindigkeit der Weiterleitung aus. Ebenfalls zu beachten sind mögliche Datenschutzbestimmungen.</p>',
            'counterGeoplugin' => '
                                    <p><strong>Muss eine valide URL sein und den Platzhalter {ip} beinhalten</strong></p>
                                    <p>Hier kann die URL zum gewünschten Geoplugin eingestellt werden. Der Platzhalter {ip} wird durch die User IP ersetzt.</p>
                                    <p>Die Einstellung greift erst, wenn auch "counterCountryOnForward" auf den Wert "1" (true) gesetzt wurde.',
            'defaultDomain'    => '
                                    <p><strong>Muss eine valide URL sein.</strong></p>
                                    <p>Die Standard-Weiterleitungs-Domain. Sie wird relevant, wenn der Wert "forwardAll" auf "1" (true) gesetzt wurde.
                                    In diesem Fall werden alle NICHT vorhandenen Pfade an die Standard-Weiterleitungs-Domain weitergeleitet.</p>
                                    <p>Beispiel: https://emsl.link/nicht/vorhanden leitet weiter an https://defaultdomain.com/nicht/vorhanden</p>',
            'forwardAll'       => '
                                    <p><strong>Erlaubte Werte: 1 oder 0</strong></p>
                                    <p>Diese Einstellung bestimmt, ob alle nicht vorhandenen Pfade an die "defaultDomain" weitergeleitet werden sollen</p>
                                    <p>Beispiel (forwardAll = 1): https://emsl.link/nicht/vorhanden leitet weiter an https://defaultdomain.com/nicht/vorhanden</p>
                                    <p>Beispiel (forwardAll = 0): https://emsl.link/nicht/vorhanden erzeugt eine 404-Fehlerausgabe</p>
            '
        ]
    ],
    'invalid' => [],
    'error' => [
        'path_exists' => 'Dieser Shortlink existiert schon.'
    ],
    'button' => [
        'create' => 'Shortlink erstellen',
        'search' => 'Suche',
        'save'   => 'Speichern',
        'addLocal' => 'Lokalisierung hinzufügen',
        'delete'    => 'Löschen'
    ],
    'success' => [
        'created' => 'Der Shortlink wurde erfolgreich erstellt',
        'search'  => 'Suchen'
    ],

    'action' => [
        'edit'      => 'bearbeiten',
        'delete'    => 'löschen'
    ]


];
