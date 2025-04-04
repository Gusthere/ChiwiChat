<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Chiwichat\Chats\Services\Chat;
use Chiwichat\Chats\Services\UserChat;
use Chiwichat\Chats\Utils\HttpHelper;

// Configuración de headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejo de CORS para preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Extracción de ruta y método
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Instancia del servicio
$chatService = new Chat();
$userChatUserService = new UserChat();

// Enrutamiento principal
try {
    switch (true) {
        /*
            Antes de hacer cualquier acción debe se debe validar
            por el servicio de usuarios que el token sea valido
            y que exista el usuario

            --Se recomienda instalar librería para hacer peticiones al anterior servicio--
        */
    }
} catch (Throwable $e) {
    error_log('Error en el servidor: ' . $e->getMessage());
    HttpHelper::sendJsonResponse([
        "error" => "Error interno del servidor",
        "detalles" => $_ENV['APP_ENV'] === 'development' ? $e->getMessage() : null
    ], 500);
}