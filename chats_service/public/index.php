<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Chiwichat\Chats\Services\Message;
use Chiwichat\Chats\Services\Conversation;
use Chiwichat\Chats\Utils\HttpHelper;

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

// Instancias de los servicios
$messageService = new Message();
$conversationService = new Conversation();

// Enrutamiento principal
try {
    switch (true) {
        // POST /conversations - Crear nueva conversación
        case $requestUri === '/conversations' && $method === 'POST':
            $data = HttpHelper::getJsonData();
            $conversationService->createConversation($data);
            break;

        // GET /conversations - Obtener conversaciones del usuario
        case $requestUri === '/conversations' && $method === 'GET':
            $conversationService->getMyConversations();
            break;

        // GET /conversations/{id} - Obtener conversación específica
        case preg_match('#^/conversations/(\d+)$#', $requestUri, $matches) && $method === 'GET':
            $conversationService->getConversation(['conversationId' => $matches[1]]);
            break;

        // POST /messages - Enviar mensaje
        case $requestUri === '/messages' && $method === 'POST':
            $data = HttpHelper::getJsonData();
            $messageService->sendMessage($data);
            break;

        // GET /conversations/{id}/messages - Obtener mensajes de conversación
        case preg_match('#^/conversations/(\d+)/messages$#', $requestUri, $matches) && $method === 'GET':
            $messageService->getConversationMessages($matches[1]);
            break;

        default:
            HttpHelper::sendJsonResponse([
                "error" => "Endpoint no encontrado",
                "endpoints_disponibles" => [
                    "POST /conversations" => "Crear nueva conversación",
                    "GET /conversations" => "Obtener conversaciones del usuario",
                    "GET /conversations/{id}" => "Obtener conversación específica",
                    "POST /messages" => "Enviar mensaje",
                    "GET /conversations/{id}/messages" => "Obtener mensajes de conversación"
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