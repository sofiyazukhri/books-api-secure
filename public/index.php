<?php
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use App\Middleware\Cors;

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$app = AppFactory::create();

// 🚀 CRITICAL FIX: Put Body Parsing at the very top so it decodes the incoming JSON data first!
$app->addBodyParsingMiddleware();

// 🚀 Run CORS next so headers are attached to every single request or error response
$app->add(new Cors());

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

(require __DIR__ . '/../src/routes.php')($app);

$app->run();