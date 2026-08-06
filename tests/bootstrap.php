<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

// Isolate SQLite per PHPUnit process after Dotenv so concurrent runners do not share
// /dev/shm/symfony-beacon-phpunit.db (readonly / lock errors under parallel coverage).
$testDbPath = sprintf('/dev/shm/symfony-beacon-phpunit-%d.db', getmypid());
$_SERVER['BEACON_TEST_DATABASE_URL'] = 'sqlite:///'.$testDbPath;
$_ENV['BEACON_TEST_DATABASE_URL'] = $_SERVER['BEACON_TEST_DATABASE_URL'];
putenv('BEACON_TEST_DATABASE_URL='.$_SERVER['BEACON_TEST_DATABASE_URL']);

if (!empty($_SERVER['APP_DEBUG'])) {
    umask(0000);
}

// Persist Halite key across KernelBrowser reboots. Without a file key the encrypt
// bundle keeps material in memory only; reboot generates a new key and API-secret
// decrypt fails on the next request (Envelope ingest 403, mailer settings, etc.).
$projectDir = dirname(__DIR__);
$secretsDir = $projectDir.'/var/secrets';
$keyFile = $secretsDir.'/.Halite.default.key';
if (!is_dir($secretsDir) && !@mkdir($secretsDir, 0777, true) && !is_dir($secretsDir)) {
    throw new RuntimeException('Unable to create Halite secrets directory: '.$secretsDir);
}
if (!is_file($keyFile)) {
    $console = $projectDir.'/bin/console';
    $cmd = escapeshellarg(\PHP_BINARY).' '.escapeshellarg($console)
        .' doctrine:encrypt:generate-secret-key -n --env=test --no-debug';
    exec($cmd.' 2>&1', $output, $exitCode);
    if (!is_file($keyFile)) {
        throw new RuntimeException('Unable to create Halite key file for tests (exit '.$exitCode.'): '.implode("\n", $output));
    }
    @chmod($keyFile, 0666);
}
