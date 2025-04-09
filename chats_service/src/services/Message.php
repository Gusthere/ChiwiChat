<?php

namespace Chiwichat\Chats\Services;

use Chiwichat\Chats\Utils\Database;
use Chiwichat\Chats\Utils\HttpHelper;
use Chiwichat\Chats\Utils\Auth;
use PDO;
use PDOException;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

class Message
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function sendMessage($data)
    {
        try {
            v::arrayType()
                ->key('conversationId', v::intVal()->positive()
                    ->setName('Conversación id')
                    ->setTemplate('{{name}} debe ser un entero positivo'))
                ->key('content', v::stringType()->notEmpty()
                    ->setName('Contenido')
                    ->setTemplate('{{name}} no puede estar vacío'))
                ->assert($data);

            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';

            if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                throw new \Exception("Token no proporcionado");
            }

            $token = $matches[1];
            $decoded = Auth::decodeToken($token);

            if (empty($decoded['username'])) {
                throw new \Exception("Token inválido");
            }

            $this->db->beginTransaction();

            // Verificar que el remitente es participante
            $checkStmt = $this->db->prepare("
                SELECT 1 FROM conversations 
                WHERE conversationId = :conversationId AND (user1Id = :user1Id OR user2Id = :user2Id)
            ");
            $checkStmt->bindParam(':conversationId', $data['conversationId'], PDO::PARAM_INT);
            $checkStmt->bindParam(':user1Id', $decoded['id'], PDO::PARAM_INT);
            $checkStmt->bindParam(':user2Id', $decoded['id'], PDO::PARAM_INT);
            $checkStmt->execute();

            if (!$checkStmt->fetch()) {
                return HttpHelper::sendJsonResponse(
                    ["error" => "El remitente no es participante de esta conversación"],
                    403
                );
            }

            $stmt = $this->db->prepare("
                INSERT INTO messages 
                (conversationId, sender_id, encryptedContent) 
                VALUES (:conversationId, :sender_id, :content)
            ");
            $stmt->bindParam(':conversationId', $data['conversationId'], PDO::PARAM_INT);
            $stmt->bindParam(':sender_id', $decoded['id'], PDO::PARAM_INT);
            $stmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
            $stmt->execute();

            $messageId = $this->db->lastInsertId();

            $this->db->commit();

            return HttpHelper::sendJsonResponse([
                "mensaje" => "Mensaje enviado correctamente",
                "messageId" => $messageId
            ], 201);
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["error" => $e->getMessages()], 400);
        } catch (PDOException $e) {
            $this->db->rollBack();
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al enviar el mensaje: " . $e->getMessage()],
                500
            );
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => $e->getMessage()],
                401
            );
        }
    }

    public function getConversationMessages($conversationId)
    {
        try {
            v::intVal()->positive()->assert($conversationId);

            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';

            if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                throw new \Exception("Token no proporcionado");
            }

            $token = $matches[1];
            $decoded = Auth::decodeToken($token);

            if (empty($decoded['username'])) {
                throw new \Exception("Token inválido");
            }

            // Verificar que el remitente es participante
            $checkStmt = $this->db->prepare("
                SELECT 1 FROM conversations 
                WHERE conversationId = :conversationId AND (user1Id = :user1Id OR user2Id = :user2Id)
            ");
            
            $checkStmt->bindParam(':conversationId', $conversationId, PDO::PARAM_INT);
            $checkStmt->bindParam(':user1Id', $decoded['id'], PDO::PARAM_INT);
            $checkStmt->bindParam(':user2Id', $decoded['id'], PDO::PARAM_INT);
            $checkStmt->execute();

            if (!$checkStmt->fetch()) {
                return HttpHelper::sendJsonResponse(
                    ["error" => "El usuario no es participante de esta conversación"],
                    403
                );
            }

            $stmt = $this->db->prepare("
                SELECT messageId, sender_id, encryptedContent as content, sentAt
                FROM messages 
                WHERE conversationId = :conversationId
                ORDER BY sentAt DESC
            ");
            $stmt->bindParam(':conversationId', $conversationId, PDO::PARAM_INT);
            $stmt->execute();

            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return HttpHelper::sendJsonResponse([
                "mensajes" => $messages,
            ]);
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["error" => $e->getMessages()], 400);
        } catch (PDOException $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al obtener mensajes"],
                500
            );
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => $e->getMessage()],
                401
            );
        }
    }
}
