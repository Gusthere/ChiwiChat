<?php

namespace Chiwichat\Users\Utils;

use PDO;
use PDOException;
use Dotenv\Dotenv;
use Chiwichat\Users\Utils\HttpHelper;

class Database {
    private static $instance;
    private $db;
    
    private function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $host = $_ENV['DB_HOST'];
        $dbname = $_ENV['DB_DATABASE'];
        $username = $_ENV['DB_USER'];
        $password = $_ENV['DB_PASSWORD'];
        $port = $_ENV['DB_USERS_PORT'] ?? '3306'; // Usa 3306 como valor por defecto

        try {
            $this->db = new PDO(
                "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", 
                $username, 
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false // Para mayor seguridad
                ]
            );
        } catch (PDOException $e) {
            HttpHelper::sendJsonResponse(["error" => "Error de conexión a la base de datos: " . $e->getMessage()], 500);
            exit();
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance->db;
    }
}