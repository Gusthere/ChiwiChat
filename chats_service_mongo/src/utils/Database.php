<?php

namespace Chiwichat\Chats\Utils;

use MongoDB\Client;
use MongoDB\Driver\Exception\Exception as MongoDBException;
use Dotenv\Dotenv;
use Chiwichat\Chats\Utils\Env;
use Chiwichat\Chats\Utils\HttpHelper;

class Database {
    private static $instance;
    private $client;
    private $db;
    
    private function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Configuración para MongoDB
        $host = Env::env('MONGO_HOST');
        $username = Env::env('MONGO_USER');
        $password = Env::env('MONGO_PASSWORD');
        $database = Env::env('MONGO_DATABASE');

        try {
            // Crear conexión a MongoDB
            $this->client = new Client(
                "mongodb://{$username}:{$password}@{$host}:27017",
                [
                    'authSource' => 'admin',
                    'connectTimeoutMS' => 5000,
                    'socketTimeoutMS' => 5000,
                    'serverSelectionTimeoutMS' => 5000,
                    'retryReads' => true,
                    'retryWrites' => true
                ]
            );

            // Seleccionar la base de datos
            $this->db = $this->client->selectDatabase($database);

            // Forzar una operación simple para verificar la conexión
            $this->db->command(['ping' => 1]);

        } catch (MongoDBException $e) {
            HttpHelper::sendJsonResponse(
                ["error" => "Error de conexión a MongoDB: " . $e->getMessage()], 
                500
            );
            exit();
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance->db;
    }

    // Método adicional para obtener el cliente completo si es necesario
    public static function getClient() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance->client;
    }
}