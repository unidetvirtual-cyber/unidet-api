<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

require __DIR__ . '/../src/Routes.php';

$app->addRoutingMiddleware();

// CORS preflight
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// CORS headers
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);

    $origin = getenv('CORS_ALLOWED_ORIGINS') ?: ($_ENV['CORS_ALLOWED_ORIGINS'] ?? '');
    if ($origin) {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, Origin, X-Requested-With')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }

    return $response;
});

$app->addErrorMiddleware(true, true, true);

$app->run();
