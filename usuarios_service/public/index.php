<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Chiwichat\Users\Services\User;
use Chiwichat\Users\Utils\HttpHelper;

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
$userService = new User();

// Enrutamiento principal
try {
    switch (true) {
        // POST /users - Crear usuario
        case $requestUri === '/users' && $method === 'POST':
            $data = HttpHelper::getJsonData();
            $userService->createUser($data);
            break;

        // GET /users/{username} - Obtener usuario
        case $requestUri === '/users/me' && $method === 'GET':
            $userService->Me();
            break;

        // GET /users/{username} - Obtener usuario
        case preg_match('#^/users/([\w-]+)$#', $requestUri, $matches) && $method === 'GET':
            $userService->searchUsers($matches[1]);
            break;

        // POST /login - Autenticación
        case $requestUri === '/login' && $method === 'POST':
            $data = HttpHelper::getJsonData();
            $userService->login($data['username'] ?? '');
            break;

        // GET /auth/check - Verificar token
        case $requestUri === '/auth/check' && $method === 'GET':
            $userService->checkUser();
            break;

        default:
            HttpHelper::sendJsonResponse([
                "error" => "Endpoint no encontrado",
                "endpoints_disponibles" => [
                    "POST /users" => "Crear nuevo usuario",
                    "GET /users/{username}" => "Obtener información de usuario",
                    "POST /login" => "Iniciar sesión",
                    "GET /auth/check" => "Verificar token JWT"
                ]
            ], 404);
    }
} catch (Throwable $e) {
    error_log('Error en el servidor: ' . $e->getMessage());
    HttpHelper::sendJsonResponse([
        "error" => "Error interno del servidor",
        "detalles" => $_ENV['APP_ENV'] === 'development' ? $e->getMessage() : null
    ], 500);
}