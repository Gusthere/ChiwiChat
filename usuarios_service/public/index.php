<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Chiwichat\Users\Services\User;
use Chiwichat\Users\Utils\HttpHelper;
use Chiwichat\Users\Utils\Auth;

// Configuración de headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Extracción de ruta y método
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Instancia del servicio
$userService = new User();

// Endpoints que NO requieren autenticación
$publicEndpoints = [
    'POST /users' => true,    // Crear usuario
    'POST /login' => true     // Login
];

// Verificar si la ruta actual requiere autenticación
$currentRoute = "$method $requestUri";

// Para rutas con parámetros como GET /users/{username}
if ($method === 'GET' && preg_match('#^/users/([\w-]+)$#', $requestUri)) {
    $currentRoute = 'GET /users/{username}';
}

if (!isset($publicEndpoints[$currentRoute])) {
    // Validar token JWT para rutas protegidas
    $userData = Auth::validateToken();
    // Pasar los datos del usuario al servicio si es necesario
    $userService->setUserData($userData);
}

// Enrutamiento principal
try {
    switch (true) {
        // POST /users - Crear usuario (público)
        case $requestUri === '/users' && $method === 'POST':
            $data = HttpHelper::getJsonData();
            $userService->createUser($data);
            break;

        // GET /users/me - Obtener mi usuario (protegido)
        case $requestUri === '/users/me' && $method === 'GET':
            $userService->Me();
            break;

        // GET /users/{username} - Obtener usuario (protegido)
        case preg_match('#^/users/([\w-]+)$#', $requestUri, $matches) && $method === 'GET':
            $userService->searchUsers($matches[1]);
            break;

        // POST /login - Autenticación (público)
        case $requestUri === '/login' && $method === 'POST':
            $data = HttpHelper::getJsonData();
            $userService->login($data['username'] ?? '');
            break;

        // GET /auth/check - Verificar token (protegido)
        case $requestUri === '/auth/check' && $method === 'GET':
            $userService->checkUser();
            break;

        default:
            HttpHelper::sendJsonResponse([
                "error" => "Endpoint no encontrado",
                "endpoints_disponibles" => [
                    "POST /users" => "Crear nuevo usuario",
                    "GET /users/me" => "Ver datos del usuario",
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