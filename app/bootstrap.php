<?php

declare(strict_types=1);

use App\Core\Response;
use App\Core\Session;
use App\Core\View;

$config = require __DIR__ . '/../config/config.php';

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set((string) $config['timezone']);
header_remove('X-Powered-By');

$isProduction = $config['app_env'] === 'production';
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/app.log');
error_reporting(E_ALL);

header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($isProduction && parse_url((string) $config['app_url'], PHP_URL_SCHEME) === 'https') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    if (!(error_reporting() & $severity)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception) use ($config): void {
    $message = str_replace(["\r", "\n"], ' ', $exception->getMessage());
    error_log(sprintf(
        'Unhandled exception %s: %s at %s:%d',
        get_class($exception),
        $message,
        $exception->getFile(),
        $exception->getLine()
    ));

    try {
        $view = new View(__DIR__ . '/Views');
        Response::html($view->render('errors/500'), 500)->send();
    } catch (Throwable) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="id"><body><h1>Terjadi kesalahan pada server.</h1></body></html>';
    }
});

$session = new Session($config);
$session->start();

return $config;
