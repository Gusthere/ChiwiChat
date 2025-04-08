<?php

namespace Chiwichat\Chats\Services;

use Chiwichat\Chats\Utils\Database;
use Chiwichat\Chats\Utils\HttpHelper;
use MongoDB\BSON\ObjectId;
use DateTime;
use MongoDB\BSON\UTCDateTime;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

class Chat
{
    private $db;
    private $conversationsCollection;
    private $userData;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->conversationsCollection = $this->db->selectCollection('conversations');
    }

    public function setUserData($userData)
    {
        $this->userData = $userData;
    }

    public function createConversation($data)
    {
        try {
            v::arrayType()
                ->key('user_id', v::intVal()->positive()
                    ->setName('ID de usuario')
                    ->setTemplate('{{name}} debe ser un número entero positivo'))
                ->assert($data);

            $existingConversation = $this->conversationsCollection->findOne([
                '$or' => [
                    ['user1_id' => (int) $this->userData['id'], 'user2_id' => (int) $data['user_id']],
                    ['user1_id' => (int) $data['user_id'], 'user2_id' => (int) $this->userData['id']],
                ]
            ]);

            if ($existingConversation) {
                return HttpHelper::sendJsonResponse([
                    "mensaje" => "Ya existe una conversación con este usuario",
                    "conversation_id" => (string) $existingConversation->_id
                ], 200);
            }

            $insertResult = $this->conversationsCollection->insertOne([
                'user1_id' => (int) $this->userData['id'],
                'user2_id' => (int) $data['user_id'],
                'created_at' => new UTCDateTime(),
                'messages' => []
            ]);

            if ($insertResult->getInsertedCount() > 0) {
                $conversationId = (string) $insertResult->getInsertedId();
                return HttpHelper::sendJsonResponse([
                    "mensaje" => "Conversación creada correctamente",
                    "conversation_id" => $conversationId
                ], 201);
            } else {
                return HttpHelper::sendJsonResponse(
                    ["error" => "Error al crear la conversación en MongoDB"],
                    500
                );
            }
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["errores" => $e->getMessages()], 400);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => $e->getMessage()],
                401
            );
        }
    }

    public function getConversation($data)
    {
        try {
            v::arrayType()
                ->key('conversation_id', v::stringType()->notEmpty()
                    ->setName('Conversación id')
                    ->setTemplate('{{name}} debe ser una cadena no vacía'))
                ->assert($data);

            try {
                $conversationId = new ObjectId($data['conversation_id']);
            } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
                return HttpHelper::sendJsonResponse(["error" => "ID de conversación inválido"], 400);
            }

            $conversation = $this->conversationsCollection->findOne([
                '_id' => $conversationId,
                '$or' => [
                    ['user1_id' => (int) $this->userData['id']],
                    ['user2_id' => (int) $this->userData['id']],
                ]
            ]);

            if (!$conversation) {
                return HttpHelper::sendJsonResponse(["error" => "No participante"], 403);
            }

            return HttpHelper::sendJsonResponse([
                "mensaje" => "Conversación encontrada",
                "conversation" => [
                    "conversation_id" => (string) $conversation->_id,
                    "user1_id" => $conversation->user1_id,
                    "user2_id" => $conversation->user2_id,
                    "created_at" => $this->formatDate($conversation->created_at), // Formatear fecha
                ]
            ]);
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["errores" => $e->getMessages()], 400);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => $e->getMessage()],
                401
            );
        }
    }

    public function getMyConversations()
    {
        try {
            $conversations = $this->conversationsCollection->find([
                '$or' => [
                    ['user1_id' => (int) $this->userData['id']],
                    ['user2_id' => (int) $this->userData['id']],
                ]
            ])->toArray();

            $formattedConversations = array_map(function ($conv) {
                return [
                    "conversation_id" => (string) $conv->_id,
                    "user1_id" => $conv->user1_id,
                    "user2_id" => $conv->user2_id,
                    "created_at" => $this->formatDate($conv->created_at), // Formatear fecha
                ];
            }, $conversations);

            return HttpHelper::sendJsonResponse([
                "mensaje" => "Mensajes encontrados",
                "conversations" => $formattedConversations,
                "total" => count($formattedConversations)
            ]);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => $e->getMessage()],
                401
            );
        }
    }

    public function sendMessage($data)
    {
        try {
            v::arrayType()
                ->key('conversation_id', v::stringType()->notEmpty())
                ->key('content', v::stringType()->notEmpty())
                ->assert($data);
            try {
                $conversationId = new ObjectId($data['conversation_id']);
            } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
                return HttpHelper::sendJsonResponse(["error" => "ID de conversación inválido"], 400);
            }

            $conversation = $this->conversationsCollection->findOne([
                '_id' => $conversationId,
                '$or' => [
                    ['user1_id' => (int) $this->userData['id']],
                    ['user2_id' => (int) $this->userData['id']],
                ]
            ]);

            if (!$conversation) {
                return HttpHelper::sendJsonResponse(["error" => "No participante"], 403);
            }

            $newMessageId = new ObjectId();
            $receiver_id = (int) ($this->userData['id'] == $conversation->user1_id) ? $conversation->user2_id : $conversation->user1_id;

            $updateResult = $this->conversationsCollection->updateOne(
                ['_id' => $conversationId],
                ['$push' => [
                    'messages' => [
                        'message_id' => $newMessageId,
                        'receiver_id' => $receiver_id,
                        'encrypted_content' => $data['content'],
                        'sent_at' => new UTCDateTime(),
                    ]
                ]]
            );

            if ($updateResult->getModifiedCount() > 0) {
                return HttpHelper::sendJsonResponse([
                    "mensaje" => "Mensaje enviado",
                    "message_id" => (string) $newMessageId
                ], 201);
            } else {
                return HttpHelper::sendJsonResponse(["error" => "Error al enviar mensaje"], 500);
            }
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["errores" => $e->getMessages()], 400);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse(["error" => $e->getMessage()], 401);
        }
    }

    public function getConversationMessages($conversationId, $requestData = [])
    {
        try {
            // Validación del ID de conversación
            v::stringType()->notEmpty()->assert($conversationId);
            $objectIdConversationId = new ObjectId($conversationId);

            // Configuración de límite
            $limit = isset($requestData['limit']) ? (int) $requestData['limit'] : 15;
            $limit = max(1, min($limit, 100));

            // Filtro base para la conversación
            $conversationFilter = [
                '_id' => $objectIdConversationId,
                '$or' => [
                    ['user1_id' => (int) $this->userData['id']],
                    ['user2_id' => (int) $this->userData['id']]
                ]
            ];

            // Manejo del filtro por fecha
            $beforeDate = null;
            $messagesFilter = [];

            if (isset($requestData['before_date'])) {
                try {
                    $beforeDate = new DateTime($requestData['before_date']);
                    $now = new DateTime();

                    if ($beforeDate > $now) {
                        return HttpHelper::sendJsonResponse(
                            ["error" => "La fecha no puede ser superior a la actual"],
                            400
                        );
                    }

                    // Convertir a UTCDateTime para comparación exacta
                    $utcBeforeDate = new UTCDateTime($beforeDate);
                    $messagesFilter = ['messages.sent_at' => ['$gt' => $utcBeforeDate]];
                } catch (\Exception $e) {
                    return HttpHelper::sendJsonResponse(
                        ["error" => "Formato de fecha inválido"],
                        400
                    );
                }
            }

            // Pipeline de agregación
            $pipeline = [
                ['$match' => $conversationFilter],
                ['$unwind' => '$messages']
            ];

            // Aplicar filtro de mensajes si existe
            if (!empty($messagesFilter)) {
                $pipeline[] = ['$match' => $messagesFilter];
            }

            // Continuación del pipeline
            $pipeline = array_merge($pipeline, [
                ['$limit' => $limit],
                ['$group' => [
                    '_id' => '$_id',
                    'messages' => ['$push' => '$messages']
                ]]
            ]);

            // Ejecutar la consulta
            $result = $this->conversationsCollection->aggregate($pipeline)->toArray();

            // Procesar resultados
            $messages = [];
            if (!empty($result)) {
                $messages = $result[0]['messages'] instanceof \MongoDB\Model\BSONArray
                    ? $result[0]['messages']->getArrayCopy()
                    : (array)$result[0]['messages'];
            }

            // Formatear los mensajes
            $formattedMessages = array_map(function ($msg) {
                return [
                    'message_id' => (string) $msg['message_id'],
                    'receiver_id' => $msg['receiver_id'],
                    'encrypted_content' => $msg['encrypted_content'],
                    'sent_at' => $this->formatDate($msg['sent_at'])
                ];
            }, $messages);

            return HttpHelper::sendJsonResponse([
                'mensaje' => 'Mensajes encontrados',
                'mensajes' => $formattedMessages,
                'total' => count($formattedMessages),
                'limit' => $limit,
                'before_date' => $beforeDate ? $beforeDate->format('Y-m-d H:i:s') : null
            ]);
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            return HttpHelper::sendJsonResponse(["error" => "ID de conversación inválido"], 400);
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["errores" => $e->getMessages()], 400);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse([
                "error" => "Error interno del servidor",
                "detalles" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formatea un objeto UTCDateTime de MongoDB a una cadena de fecha estándar.
     *
     * @param UTCDateTime|null $utcDateTime
     * @param string $format Formato de fecha de PHP (ver date()). Por defecto: 'Y-m-d H:i:s'
     * @return string|null
     */
    private function formatDate(?UTCDateTime $utcDateTime, string $format = 'Y-m-d H:i:s'): ?string
    {
        if ($utcDateTime === null) {
            return null;
        }
        $dateTime = $utcDateTime->toDateTime();
        $dateTime->setTimezone(new \DateTimeZone('America/Caracas')); // Ajustar a tu zona horaria (Barquisimeto)
        return $dateTime->format($format);
    }
}
