<?php
require 'vendor/autoload.php';

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
            'host' => getenv('DB_HOST'),
            'name' => getenv('DB_DATABASE'),
            'user' => getenv('DB_USER'),
            'pass' => getenv('DB_PASSWORD'),
            'port' => getenv('DB_CHATS_PORT'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_0900_ai_ci',
            'table_prefix' => '',
            'table_suffix' => ''
        ]
    ]
];