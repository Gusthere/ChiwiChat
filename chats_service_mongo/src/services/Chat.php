<?php

namespace Chiwichat\Chats\Services;

use Chiwichat\Chats\Utils\Database;
use Chiwichat\Chats\Utils\HttpHelper;
use Chiwichat\Chats\Utils\Auth;
use MongoDB\BSON\ObjectId;
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
            // Validar conversationId
            v::stringType()->notEmpty()->assert($conversationId);

            try {
                $objectIdConversationId = new ObjectId($conversationId);
            } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
                return HttpHelper::sendJsonResponse(["error" => "ID inválido"], 400);
            }

            // Validar parámetros de la solicitud
            $limit = isset($requestData['limit']) ? (int) $requestData['limit'] : 15;
            $limit = max(1, min($limit, 100)); // Limitar entre 1 y 100 para evitar abusos

            $filter = [];
            if (isset($requestData['before_date'])) {
                try {
                    $beforeDate = new DateTime($requestData['before_date']);
                    $now = new DateTime();

                    if ($beforeDate > $now) {
                        return HttpHelper::sendJsonResponse(["error" => "La fecha no puede ser superior a la actual"], 400);
                    }

                    // Convertir a formato MongoDB
                    $filter['messages.sent_at'] = ['$lt' => $beforeDate];
                } catch (\Exception $e) {
                    return HttpHelper::sendJsonResponse(["error" => "Formato de fecha inválido"], 400);
                }
            }

            // Pipeline de agregación para optimizar la consulta
            $pipeline = [
                ['$match' => [
                    '_id' => $objectIdConversationId,
                    '$or' => [
                        ['user1_id' => (int) $this->userData['id']],
                        ['user2_id' => (int) $this->userData['id']],
                    ]
                ]],
                ['$unwind' => '$messages'], // Primero desempaquetamos para mejor compatibilidad
                [
                    '$match' => isset($filter['messages.sent_at'])
                        ? ['messages.sent_at' => $filter['messages.sent_at']]
                        : []
                ],
                ['$sort' => ['messages.sent_at' => -1]],
                ['$limit' => $limit],
                ['$group' => [
                    '_id' => '$_id',
                    'messages' => ['$push' => '$messages']
                ]]
            ];

            // Ejecutar con opciones para MongoDB 4.4
            $options = [
                'allowDiskUse' => true // Permite usar disco para operaciones grandes
            ];

            $result = $this->conversationsCollection->aggregate($pipeline, $options)->toArray();
            
            if (empty($result)) {
                return HttpHelper::sendJsonResponse([
                    "mensaje" => "mensajes encontrados",
                    "mensajes" => [],
                    "total" => 0,
                    "limit" => $limit,
                    "before_date" => $beforeDate ?? null
                ]);
            }

            $messages = $result[0]['messages'];

            // Formatear mensajes para la respuesta
            $formattedMessages = array_map(function ($msg) {
                $msg = (object) $msg;
                $msg->message_id = isset($msg->message_id->{'$oid'}) ? $msg->message_id->{'$oid'} : (string) $msg->message_id;
                $msg->sent_at = $this->formatDate($msg->sent_at);
                return $msg;
            }, $messages);

            return HttpHelper::sendJsonResponse([
                "mensaje" => "mensajes encontrados",
                "mensajes" => $formattedMessages,
                "total" => count($formattedMessages),
                "limit" => $limit,
                "before_date" => $beforeDate ?? null
            ]);
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["errores" => $e->getMessages()], 400);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse(["error" => $e->getMessage()], 500);
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
