<?php

namespace Chiwichat\Chats\Services;

use Chiwichat\Chats\Utils\Database;
use Chiwichat\Chats\Utils\HttpHelper;
use MongoDB\BSON\ObjectId;
use DateTime;
use MongoDB\BSON\UTCDateTime;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;
use Dotenv\Dotenv;
use Chiwichat\Chats\Utils\Env;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

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
                ->key('userId', v::intVal()->positive()
                    ->setName('ID de usuario')
                    ->setTemplate('{{name}} debe ser un número entero positivo'))
                ->key('username', v::alnum()->noWhitespace()->length(3, 30)
                    ->setName('Usuario')
                    ->setTemplate('{{name}} debe contener solo letras y números (3-30 caracteres)'))
                ->assert($data);

            $existingConversation = $this->conversationsCollection->findOne([
                '$or' => [
                    ['user1Id' => (int) $this->userData['id'], 'user2Id' => (int) $data['userId']],
                    ['user1Id' => (int) $data['userId'], 'user2Id' => (int) $this->userData['id']],
                ]
            ]);

            if ($existingConversation) {
                return HttpHelper::sendJsonResponse([
                    "mensaje" => "Ya existe una conversación con este usuario",
                    "conversationId" => (string) $existingConversation->_id
                ], 200);
            }

            $insertResult = $this->conversationsCollection->insertOne([
                'user1Id' => (int) $this->userData['id'],
                'user1Username' => (string) $this->userData['username'],
                'user2Id' => (int) $data['userId'],
                'user2Username' => (string) $data['username'],
                'updatedAt' => new UTCDateTime(),
                'messages' => []
            ]);

            if ($insertResult->getInsertedCount() > 0) {
                $conversationId = (string) $insertResult->getInsertedId();
                return HttpHelper::sendJsonResponse([
                    "mensaje" => "Conversación creada correctamente",
                    "conversationId" => $conversationId
                ], 201);
            } else {
                return HttpHelper::sendJsonResponse(
                    ["error" => "Error al crear la conversación en MongoDB"],
                    500
                );
            }
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["error" => $e->getMessages()], 400);
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
                ->key('conversationId', v::stringType()->notEmpty()
                    ->setName('Conversación id')
                    ->setTemplate('{{name}} debe ser una cadena no vacía'))
                ->assert($data);

            try {
                $conversationId = new ObjectId($data['conversationId']);
            } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
                return HttpHelper::sendJsonResponse(["error" => "ID de conversación inválido"], 400);
            }

            $conversation = $this->conversationsCollection->findOne([
                '_id' => $conversationId,
                '$or' => [
                    ['user1Id' => (int) $this->userData['id']],
                    ['user2Id' => (int) $this->userData['id']],
                ]
            ]);

            if (!$conversation) {
                return HttpHelper::sendJsonResponse(["error" => "No participante"], 403);
            }

            return HttpHelper::sendJsonResponse([
                "mensaje" => "Conversación encontrada",
                "conversation" => [
                    "conversationId" => (string) $conversation->_id,
                    "user1Id" => $conversation->user1Id,
                    "user1Username" => $conversation->user1Username,
                    "user2Id" => $conversation->user2Id,
                    "user2Username" => $conversation->user2Username,
                    "updatedAt" => $this->formatDate($conversation->updatedAt), // Formatear fecha
                ]
            ]);
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["error" => $e->getMessages()], 400);
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
            $userId = (int) $this->userData['id'];

            $pipeline = [
                [
                    '$match' => [
                        '$or' => [
                            ['user1Id' => $userId],
                            ['user2Id' => $userId]
                        ]
                    ]
                ],
                [
                    '$addFields' => [
                        'lastMessage' => [
                            '$arrayElemAt' => ['$messages', -1]
                        ],
                        'unreadCount' => [
                            '$size' => [
                                '$filter' => [
                                    'input' => '$messages',
                                    'as' => 'msg',
                                    'cond' => [
                                        '$and' => [
                                            ['$eq' => ['$$msg.status', 0]],
                                            ['$eq' => ['$$msg.receiverId', $userId]]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    '$sort' => ['updatedAt' => -1]
                ],
                [
                    '$project' => [
                        'conversationId' => ['$toString' => '$_id'],
                        'user1Id' => 1,
                        'user1Username' => 1,
                        'user2Id' => 1,
                        'user2Username' => 1,
                        'updatedAt' => 1,
                        'lastMessage' => 1,
                        'unreadCount' => 1
                    ]
                ]
            ];


            $conversations = $this->conversationsCollection->aggregate($pipeline)->toArray();
            // Preparar mensajes para desencriptación
            $messagesToDecrypt = [];
            $messageIndexMap = []; // Para mapear mensajes a conversaciones

            foreach ($conversations as $index => $conv) {
                if (isset($conv->lastMessage) && is_object($conv->lastMessage) && isset($conv->lastMessage->encryptedContent)) {
                    $messagesToDecrypt[] = [
                        'messageId' => (string) $conv->lastMessage->messageId,
                        'userId' => $conv->lastMessage->receiverId,
                        'encryptedMessage' => $conv->lastMessage->encryptedContent,
                        'date' => $this->formatDate($conv->lastMessage->sentAt),
                        'status' => $conv->lastMessage->status
                    ];
                    $messageIndexMap[] = $index;
                }
            }

            // Desencriptar todos los mensajes en una sola llamada
            if (!empty($messagesToDecrypt)) {
                $thirdPartyApiUrl = Env::env("URL_CRYPTO") . '?action=decrypt';
                $ch = curl_init($thirdPartyApiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['messages' => $messagesToDecrypt]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    $decodedResponse = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($decodedResponse['decryptedMessage'])) {
                        // Asignar mensajes desencriptados a las conversaciones correspondientes
                        foreach ($decodedResponse['decryptedMessage'] as $i => $decryptedMsg) {
                            $convIndex = $messageIndexMap[$i];
                            $conversations[$convIndex]->lastMessage->decryptedContent = $decryptedMsg['message'];
                        }
                    }
                }
            }

            // Formatear conversaciones
            $formattedConversations = array_map(function ($conv) {
                $lastMessage = null;

                if (isset($conv->lastMessage) && is_object($conv->lastMessage)) {
                    $lastMessage = [
                        "message" => $conv->lastMessage->decryptedContent ?? $conv->lastMessage->encryptedContent ?? null,
                        "receiverId" => $conv->lastMessage->receiverId ?? null,
                        "sentAt" => isset($conv->lastMessage->sentAt)
                            ? $this->formatDate($conv->lastMessage->sentAt)
                            : null,
                        "status" => $conv->lastMessage->status ?? null
                    ];
                }

                return [
                    "conversationId" => $conv->conversationId,
                    "user1Id" => $conv->user1Id,
                    "user1Username" => $conv->user1Username,
                    "user2Id" => $conv->user2Id,
                    "user2Username" => $conv->user2Username,
                    "updatedAt" => $this->formatDate($conv->updatedAt),
                    "lastMessage" => $lastMessage,
                    "unreadCount" => $conv->unreadCount
                ];
            }, $conversations);

            return HttpHelper::sendJsonResponse([
                "mensaje" => "Conversaciones encontradas",
                "conversations" => $formattedConversations,
                "total" => count($formattedConversations)
            ]);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => $e->getMessage()],
                500
            );
        }
    }

    public function sendMessage($data)
    {
        try {
            v::arrayType()
                ->key('conversationId', v::stringType()->notEmpty())
                ->key('content', v::stringType()->notEmpty())
                ->assert($data);
            try {
                $conversationId = new ObjectId($data['conversationId']);
            } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
                return HttpHelper::sendJsonResponse(["error" => "ID de conversación inválido"], 400);
            }

            $conversation = $this->conversationsCollection->findOne([
                '_id' => $conversationId,
                '$or' => [
                    ['user1Id' => (int) $this->userData['id']],
                    ['user2Id' => (int) $this->userData['id']],
                ]
            ]);

            if (!$conversation) {
                return HttpHelper::sendJsonResponse(["error" => "No participante"], 403);
            }

            $newMessageId = new ObjectId();
            $receiverId = (int) ($this->userData['id'] == $conversation->user1Id) ? $conversation->user2Id : $conversation->user1Id;

            $thirdPartyApiUrl = Env::env("URL_CRYPTO") . '?action=encrypt'; // URL de ejemplo
            // Configurar la petición cURL
            $ch = curl_init($thirdPartyApiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "senderId" => $this->userData['id'],
                "recipientId" => $receiverId,
                "message" => $data['content']
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            // Ejecutar la petición y obtener la respuesta
            $response = curl_exec($ch);
            curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Verificar si la petición fue exitosa

            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return HttpHelper::sendJsonResponse(
                    [
                        "error" => "La API de terceros devolvió un JSON inválido",
                        "detalles" => $response
                    ],
                    502 // Bad Gateway
                );
            }

            // Caso 2: Falta la clave 'decryptedMessage'
            if (!isset($decodedResponse['encryptedMessage'])) {
                return HttpHelper::sendJsonResponse(
                    [
                        "error" => "La API de terceros no devolvió mensaje cifrado",
                        "detalles" => $decodedResponse
                    ],
                    502
                );
            }

            $encryptedMesage = $decodedResponse['encryptedMessage'];

            $now = new UTCDateTime(); // Fecha actual en formato MongoDB

            $updateResult = $this->conversationsCollection->updateOne(
                ['_id' => $conversationId],
                [
                    '$push' => [
                        'messages' => [
                            'messageId' => $newMessageId,
                            'receiverId' => $receiverId,
                            'encryptedContent' => $encryptedMesage,
                            'sentAt' => $now,
                            'status' => 0
                        ]
                    ],
                    '$set' => [
                        'updatedAt' => $now
                    ]
                ]
            );

            if ($updateResult->getModifiedCount() > 0) {
                return HttpHelper::sendJsonResponse([
                    "mensaje" => "Mensaje enviado",
                    "messageId" => (string) $newMessageId,
                    "content" => [
                        'conversationId' => $conversationId,
                        'from' => $receiverId,
                        'message' => $data['content'],
                        'date' => $this->formatDate($now) // Conversión legible
                    ]
                ], 201);
            } else {
                return HttpHelper::sendJsonResponse(["error" => "Error al enviar mensaje"], 500);
            }
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["error" => $e->getMessages()], 400);
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
                    ['user1Id' => (int) $this->userData['id']],
                    ['user2Id' => (int) $this->userData['id']]
                ]
            ];

            // Manejo del filtro por fecha
            $beforeDate = null;
            $messagesFilter = [];

            if (isset($requestData['beforeDate'])) {
                try {
                    $beforeDate = new DateTime($requestData['beforeDate']);
                    $now = new DateTime();

                    if ($beforeDate > $now) {
                        return HttpHelper::sendJsonResponse(
                            ["error" => "La fecha no puede ser superior a la actual"],
                            400
                        );
                    }

                    // Convertir a UTCDateTime para comparación exacta
                    $utcBeforeDate = new UTCDateTime($beforeDate);
                    $messagesFilter = ['messages.sentAt' => ['$gt' => $utcBeforeDate]];
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
                [
                    '$group' => [
                        '_id' => '$_id',
                        'messages' => ['$push' => '$messages']
                    ]
                ]
            ]);

            // Ejecutar la consulta
            $result = $this->conversationsCollection->aggregate($pipeline)->toArray();

            // Procesar resultados
            $messages = [];
            if (!empty($result)) {
                $messages = $result[0]['messages'] instanceof \MongoDB\Model\BSONArray
                    ? $result[0]['messages']->getArrayCopy()
                    : (array) $result[0]['messages'];
            }

            // Formatear los mensajes
            $formattedMessages = array_map(function ($msg) {
                return [
                    'messageId' => (string) $msg['messageId'],
                    'userId' => $msg['receiverId'],
                    'encryptedMessage' => $msg['encryptedContent'],
                    'date' => $this->formatDate($msg['sentAt']),
                    'status' => $msg['status']
                ];
            }, $messages);
            // Si hay mensajes, enviarlos a la API de terceros
            if (!empty($formattedMessages)) {
                $thirdPartyApiUrl = Env::env("URL_CRYPTO") . '?action=decrypt'; // URL de ejemplo
                // Configurar la petición cURL
                $ch = curl_init($thirdPartyApiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['messages' => $formattedMessages]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json'
                ]);

                // Ejecutar la petición y obtener la respuesta
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                // Verificar si la petición fue exitosa
                // if ($httpCode === 200) {
                $decodedResponse = json_decode($response, true);
                // Caso 1: JSON inválido
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return HttpHelper::sendJsonResponse(
                        [
                            "error" => "La API de terceros devolvió un JSON inválido",
                            "detalles" => $response
                        ],
                        502 // Bad Gateway
                    );
                }

                // Caso 2: Falta la clave 'decryptedMessage'
                if (!isset($decodedResponse['decryptedMessage'])) {
                    return HttpHelper::sendJsonResponse(
                        [
                            "error" => "La API de terceros no devolvió mensajes descifrados",
                            "detalles" => $decodedResponse
                        ],
                        502
                    );
                }

                // Éxito: Reemplazar mensajes cifrados por los descifrados
                $formattedMessages = $decodedResponse['decryptedMessage'];
                // } else {
                //     return HttpHelper::sendJsonResponse([
                //         "error" => "Error al desencriptar los mensajes",
                //         "detalles" => [$response, "code" => $httpCode]
                //     ], 500);
                // }
            }

            return HttpHelper::sendJsonResponse([
                'mensaje' => 'Mensajes encontrados',
                'mensajes' => $formattedMessages,
                'total' => count($formattedMessages),
                'limit' => $limit,
                'beforeDate' => $beforeDate ? $beforeDate->format('Y-m-d H:i:s') : null
            ]);
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            return HttpHelper::sendJsonResponse(["error" => "ID de conversación inválido"], 400);
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["error" => $e->getMessages()], 400);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse([
                "error" => "Error interno del servidor 3",
                "detalles" => $e->getMessage()
            ], 500);
        }
    }

    public function updateMessagesStatus($conversationId, $requestData = [])
    {
        try {
            // Validación de parámetros
            v::stringType()->notEmpty()->assert($conversationId);
            v::keySet(
                v::key('beforeDate', v::stringType()->notEmpty()),
                v::key('status', v::intType()->between(1, 2))
            )->assert($requestData);

            $objectIdConversationId = new ObjectId($conversationId);
            $userId = (int) $this->userData['id'];
            $newStatus = (int) $requestData['status'];

            // Procesamiento de fecha con ajuste de 1 segundo
            $beforeDate = new DateTime($requestData['beforeDate']);
            $beforeDate->modify('+1 second'); // Añadimos 1 segundo
            $utcBeforeDate = new UTCDateTime($beforeDate->getTimestamp() * 1000);

            // 1. Verificar que el usuario pertenece a la conversación
            $conversation = $this->conversationsCollection->findOne([
                '_id' => $objectIdConversationId,
                '$or' => [
                    ['user1Id' => $userId],
                    ['user2Id' => $userId]
                ]
            ]);

            if (!$conversation) {
                return HttpHelper::sendJsonResponse(
                    ["error" => "Conversación no encontrada o no autorizado"],
                    404
                );
            }

            // 2. Actualizar mensajes que cumplan:
            // - receiverId = usuario actual
            // - status < nuevo status
            // - sentAt <= fecha proporcionada + 1 segundo
            $updateResult = $this->conversationsCollection->updateOne(
                [
                    '_id' => $objectIdConversationId,
                    'messages' => [
                        '$elemMatch' => [
                            'receiverId' => $userId,
                            'status' => ['$lt' => $newStatus],
                            'sentAt' => ['$lte' => $utcBeforeDate]
                        ]
                    ]
                ],
                [
                    '$set' => [
                        'messages.$[elem].status' => $newStatus
                    ]
                ],
                [
                    'arrayFilters' => [
                        [
                            'elem.receiverId' => $userId,
                            'elem.status' => ['$lt' => $newStatus],
                            'elem.sentAt' => ['$lte' => $utcBeforeDate]
                        ]
                    ]
                ]
            );

            return HttpHelper::sendJsonResponse([
                "mensaje" => "Estatus actualizado",
                "updatedCount" => $updateResult->getModifiedCount()
            ]);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse([
                "error" => "Error en la actualización",
                "detalles" => $e->getMessage(),
                "trace" => $e->getTraceAsString() // Solo para desarrollo
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
