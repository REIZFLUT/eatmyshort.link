<?php

/*
 * Laufzeit-Konfiguration ohne .env-Datei im Repository.
 *
 * Diese Datei wird von public/index.php und bin/console VOR dem Symfony-Runtime
 * (vendor/autoload_runtime.php) eingebunden. Sie entscheidet, welche dot-env-Datei
 * geladen wird, und bricht ohne jegliche Konfiguration klar ab, statt den
 * Symfony-Runtime still auf den Dev-Modus zurueckfallen zu lassen.
 *
 * Geladen werden:
 *  - im Testlauf (APP_ENV=test) ausschliesslich .env.test,
 *  - sonst .env, ersatzweise die git-ignorierte .env.local.
 * .env.example wird NIE automatisch geladen - sie ist nur eine Kopiervorlage.
 */

declare(strict_types=1);

$projectDir = dirname(__DIR__);

// APP_ENV vor dem Runtime-Lauf aus der echten Umgebung lesen.
$env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null;

// Der PHP-CLI-Server und FPM-Pools ohne "E" in variables_order stellen Prozess-
// Umgebungsvariablen nur ueber getenv() bereit. Deshalb hier nachziehen und in
// $_SERVER/$_ENV spiegeln, damit auch der Symfony-Runtime APP_ENV sieht (sonst
// wuerde er beim deaktivierten Dotenv still auf "dev" zurueckfallen).
if (null === $env && false !== $value = getenv('APP_ENV')) {
    $env = $value;
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $value;
}

// Zu verwendende dot-env-Datei bestimmen (null = keine vorhanden).
$dotenvFile = null;

if ('test' === $env) {
    if (is_file($projectDir.'/.env.test')) {
        $dotenvFile = '.env.test';
    }
} else {
    foreach (['.env', '.env.local'] as $candidate) {
        if (is_file($projectDir.'/'.$candidate)) {
            $dotenvFile = $candidate;
            break;
        }
    }
}

// Fail-fast: ohne dot-env-Datei UND ohne gesetztes APP_ENV gibt es keine
// Konfiguration. Ohne .env-Datei muss APP_ENV (und die uebrige Konfiguration) als
// echte Umgebungsvariable gesetzt oder .env.example nach .env kopiert werden.
if (null === $dotenvFile && null === $env) {
    $message = 'EatMyShort.Link: Es liegt keine .env-Datei im Projekt und APP_ENV ist nicht gesetzt. '
        .'Entweder .env.example nach .env kopieren oder APP_ENV (und die uebrige Konfiguration) '
        .'als echte Umgebungsvariable setzen - siehe docs/INSTALL.md.';

    if ('cli' === \PHP_SAPI) {
        fwrite(\STDERR, $message."\n");
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Serverkonfiguration fehlt.';
    exit;
}

// Dem Symfony-Runtime mitteilen, welche Datei zu laden ist (oder dass keine
// geladen werden soll). disable_dotenv muss true sein, wenn keine Datei existiert,
// sonst wirft Dotenv::loadEnv() eine PathException.
$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'project_dir' => $projectDir,
    'dotenv_path' => $dotenvFile ?? '.env',
    'disable_dotenv' => null === $dotenvFile,
];
