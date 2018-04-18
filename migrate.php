<?php
require_once __DIR__ . '/vendor/autoload.php';

(new \Dotenv\Dotenv(__DIR__))->load();

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

// setup the database connection
$capsule = new DB();

$capsule->addConnection([
    'driver' => 'mysql',
    'host' => getenv('DB_HOST'),
    'database' => getenv('DB_DATABASE'),
    'username' => getenv('DB_USERNAME'),
    'password' => getenv('DB_PASSWORD'),
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

// setup the logger
$logger = new Logger('shopware-sku-mapping');
$logger->pushHandler(
    (new RotatingFileHandler(__DIR__ . '/logs/le-grand-mappening'))
        ->setFormatter(new JsonFormatter())
);

$logger->pushHandler((new \Monolog\Handler\StreamHandler('php://stdout')));

$skuMapping = json_decode(file_get_contents(__DIR__ . '/sku-correction-mapping.json'), true);

$migrator = new Migrator($capsule->getConnection(), $logger, env('DRY_RUN', true));
$migrator($skuMapping);
