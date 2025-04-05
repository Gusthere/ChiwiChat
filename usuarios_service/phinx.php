<?php
require 'vendor/autoload.php';

use Chiwichat\Users\Utils\Env;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

return [
    'paths' => [
        'migrations' => __DIR__.'/db/migrations', // Ruta absoluta dentro del contenedor
        'seeds' => __DIR__.'/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'production' => [
            'adapter' => 'mysql',
            'host' => Env::env('DB_HOST'),
            'name' => Env::env('DB_DATABASE'),
            'user' => Env::env('DB_USER'),
            'pass' => Env::env('DB_PASSWORD'),
            'port' => Env::env('DB_USERS_PORT'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_0900_ai_ci',
            'table_prefix' => '',
            'table_suffix' => ''
        ]
    ]
];