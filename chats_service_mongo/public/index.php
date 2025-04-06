<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Chiwichat\Chats\Services\Chat; // Usamos la clase fusionada
use Chiwichat\Chats\Utils\HttpHelper;

// Configuración de headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Ajustamos los métodos permitidos
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Extracción de ruta y método
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Instancia del servicio fusionado
$chat = new Chat();

// Enrutamiento principal
try {
    switch (true) {
        // POST /conversations - Crear nueva conversación
        case $requestUri === '/conversations' && $method === 'POST':
            $data = HttpHelper::getJsonData();
            $chat->createConversation($data);
            break;

        // GET /conversations - Obtener conversaciones del usuario
        case $requestUri === '/conversations' && $method === 'GET':
            $chat->getMyConversations();
            break;

        // GET /conversations/{id} - Obtener conversación específica (sin mensajes)
        case preg_match('#^/conversations/([a-f\d]{24})$#i', $requestUri, $matches) && $method === 'GET':
            $chat->getConversation(['conversation_id' => $matches[1]]);
            break;

        // POST /conversations/{id}/messages - Enviar mensaje a una conversación
        case $requestUri === '/messages' && $method === 'POST':
            $data = HttpHelper::getJsonData();
            $chat->sendMessage($data);
            break;

        // GET /conversations/{id}/messages - Obtener mensajes de una conversación
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