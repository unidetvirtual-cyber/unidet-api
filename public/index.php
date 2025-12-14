<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require __DIR__ . '/../vendor/autoload.php';

/* =========================
 * .env (solo local)
 * ========================= */
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

/* =========================
 * Slim
 * ========================= */
$app = AppFactory::create();

/* =========================
 * Detectar Azure
 * ========================= */
$isAzure = (bool)(getenv('WEBSITE_INSTANCE_ID') || getenv('WEBSITE_SITE_NAME'));

/* =========================================================
 * Azure router dual:
 * 1) /index.php/ping     (PATH_INFO)
 * 2) /index.php?r=news   (QUERY)  <-- IMPORTANTE: usa r=news (SIN /)
 * ========================================================= */
$hasR = $isAzure && isset($_GET['r']) && is_string($_GET['r']) && trim($_GET['r']) !== '';

if ($hasR) {
    // acepta r=news o r=/news (pero en Azure usa r=news)
    $route = '/' . ltrim(trim((string)$_GET['r']), '/');

    // conserva otros query params: ?r=news&page=2
    $rest = $_GET;
    unset($rest['r']);
    $qs = http_build_query($rest);

    $_SERVER['QUERY_STRING'] = $qs;
    $_SERVER['REQUEST_URI']  = $route . ($qs ? ('?' . $qs) : '');
    $_SERVER['PATH_INFO']    = $route;

    // En modo ?r=, Slim debe ver basePath vacío
    $app->setBasePath('');
} else {
    // Modo normal:
    if ($isAzure) {
        $app->setBasePath('/index.php');
    } else {
        // Local (XAMPP/subcarpeta)
        $basePath = (string)($_ENV['BASE_PATH'] ?? getenv('BASE_PATH') ?? '');
        $basePath = rtrim(trim($basePath), '/');
        if ($basePath !== '') {
            $app->setBasePath($basePath);
        }
    }
}

$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

/* =========================
 * Errors
 * ========================= */
$appDebugRaw = (string)($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false');
$appDebug = in_array(strtolower($appDebugRaw), ['1','true','yes','on'], true);
$app->addErrorMiddleware($appDebug, true, true);

/* =========================
 * CORS
 * ========================= */
$allowedOriginsEnv = (string)($_ENV['ALLOWED_ORIGINS'] ?? getenv('ALLOWED_ORIGINS') ?? 'http://localhost:5173');
$allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $allowedOriginsEnv))));

$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

$app->add(function (Request $request, RequestHandler $handler) use ($allowedOrigins): Response {
    $origin = $request->getHeaderLine('Origin');

    $allowOrigin = $allowedOrigins[0] ?? 'http://localhost:5173';
    if ($origin && in_array($origin, $allowedOrigins, true)) {
        $allowOrigin = $origin;
    }

    if (strtoupper($request->getMethod()) === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
    } else {
        $response = $handler->handle($request);
    }

    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
        ->withHeader('Vary', 'Origin')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

/* =========================
 * Rutas
 * ========================= */
require __DIR__ . '/../src/Routes.php';

$app->run();
