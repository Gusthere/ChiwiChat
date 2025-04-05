<?php

namespace Chiwichat\Chats\Services;

use Chiwichat\Chats\Utils\Database;
use Chiwichat\Chats\Utils\HttpHelper;
use Chiwichat\Chats\Utils\Auth;
use PDO;
use PDOException;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

class Conversation
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function createConversation($data)
    {
        try {
            v::arrayType()
                ->key('user_id', v::intVal()->positive()
                    ->setName('ID de usuario')
                    ->setTemplate('{{name}} debe ser un número entero positivo'))
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

            $stmt = $this->db->prepare("
                INSERT INTO conversations (user1_id, user2_id, created_at)
                VALUES (:user1_id, :user2_id, CURRENT_TIMESTAMP)
            ");
            $stmt->bindParam(':user1_id', $decoded['id'], PDO::PARAM_INT);
            $stmt->bindParam(':user2_id', $data['user_id'], PDO::PARAM_INT);
            $stmt->execute();

            $conversationId = $this->db->lastInsertId();

            $this->db->commit();
            return HttpHelper::sendJsonResponse([
                "mensaje" => "Conversación creada correctamente",
                "conversation_id" => $conversationId
            ], 201);
        } catch (PDOException $e) {
            $this->db->rollBack();
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al crear la conversación: " . $e->getMessage()],
                500
            );
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
                ->key('conversation_id', v::intVal()->positive()
                    ->setName('Conversación id')
                    ->setTemplate('{{name}} debe ser un entero positivo'))
                ->assert($data);
            $stmt = $this->db->prepare("
                SELECT * FROM conversations 
                WHERE conversation_id = :id
                ");
            $stmt->bindParam(':id', $data['conversation_id'], PDO::PARAM_INT);
            $stmt->execute();

            $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$conversation) {
                return HttpHelper::sendJsonResponse(
                    ["error" => "Conversación no encontrada"],
                    404
                );
            }

            return HttpHelper::sendJsonResponse([
                "conversation" => $conversation
            ]);
        } catch (PDOException $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al obtener la conversación"],
                500
            );
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

            $stmt = $this->db->prepare("
                SELECT *
                FROM conversations 
                WHERE user1_id = :user1_id
                OR user2_id = :user2_id
            ");
            $stmt->bindParam(':user1_id', $decoded['id'], PDO::PARAM_INT);
            $stmt->bindParam(':user2_id', $decoded['id'], PDO::PARAM_INT);
            $stmt->execute();

            $conversation = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return HttpHelper::sendJsonResponse([
                "conversations" => $conversation,
                "total" => count($conversation)
            ]);
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["errores" => $e->getMessages()], 400);
        } catch (PDOException $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al obtener los chats"],
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
