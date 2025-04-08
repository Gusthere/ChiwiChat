<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Chiwichat\Chats\Services\Chat;
use Chiwichat\Chats\Utils\HttpHelper;
use Chiwichat\Chats\Utils\Auth;

// Configuración de headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

date_default_timezone_set("America/Caracas");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Extracción de ruta y método
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Validar token JWT para todas las rutas
$userData = Auth::validateToken();

// Instancia del servicio fusionado con datos del usuario
$chat = new Chat();

// Pasar datos del usuario al servicio si es necesario
$chat->setUserData($userData);

// Enrutamiento principal
try {
    switch (true) {
        // POST /conversations - Crear nueva conversación (protegido)
        case $requestUri === '/conversations' && $method === 'POST':
            $data = HttpHelper::getJsonData();
            $chat->createConversation($data);
            break;

        // GET /conversations - Obtener conversaciones del usuario (protegido)
        case $requestUri === '/conversations' && $method === 'GET':
            $chat->getMyConversations();
            break;

        // GET /conversations/{id} - Obtener conversación específica (protegido)
        case preg_match('#^/conversations/([a-f\d]{24})$#i', $requestUri, $matches) && $method === 'GET':
            $chat->getConversation(['conversation_id' => $matches[1]]);
            break;

        // POST /messages - Enviar mensaje a una conversación (protegido)
        case $requestUri === '/messages' && $method === 'POST':
            $data = HttpHelper::getJsonData();
            $chat->sendMessage($data);
            break;

        // GET /conversations/{id}/messages - Obtener mensajes de una conversación (protegido)
        case preg_match('#^/conversations/([a-f\d]{24})/messages$#i', $requestUri, $matches) && $method === 'GET':
            $chat->getConversationMessages($matches[1]);
            break;

        default:
            HttpHelper::sendJsonResponse([
                "error" => "Endpoint no encontrado",
                "endpoints_disponibles" => [
                    "POST /conversations" => "Crear nueva conversación",
                    "GET /conversations" => "Obtener conversaciones del usuario",
                    "GET /conversations/{conversation_id}" => "Obtener conversación específica",
                    "POST /messages" => "Enviar mensaje a una conversación",
                    "GET /conversations/{conversation_id}/messages" => "Obtener mensajes de una conversación"
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
