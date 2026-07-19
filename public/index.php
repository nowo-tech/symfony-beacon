<?php

declare(strict_types=1);

/**
 * Symfony front controller — entry point for classic and worker HTTP modes.
 *
 * Classic (FRANKENPHP_MODE=classic):
 *   php_server runs this script per request → HttpKernelRunner.
 *
 * Worker (FRANKENPHP_MODE=worker):
 *   FrankenPHP boots this same file once, sets FRANKENPHP_WORKER=1,
 *   and Symfony Runtime selects FrankenPhpWorkerRunner, which enters the
 *   frankenphp_handle_request() loop keeping the Kernel in memory.
 *
 * No runtime/frankenphp-symfony or custom APP_RUNTIME needed on Symfony ≥7.4.
 *
 * @see frankenphp/docker-entrypoint.sh
 * @see Symfony\Component\Runtime\Runner\FrankenPhpWorkerRunner
 */

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
