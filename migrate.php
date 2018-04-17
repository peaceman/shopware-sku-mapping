<?php
require_once __DIR__ . '/vendor/autoload.php';

(new \Dotenv\Dotenv(__DIR__))->load();

use Illuminate\Database\Capsule\Manager as DB;
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

$capsule->setAsGlobal();

$skuMapping = json_decode(file_get_contents(__DIR__ . '/sku-correction-mapping.json'), true);

DB::table('s_order_details')->limit(10)->orderBy('id')->each(function ($sOrderDetails) {
    dd($sOrderDetails);
});

