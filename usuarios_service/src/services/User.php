<?php

namespace Chiwichat\Users\Services;

use Chiwichat\Users\Utils\Database;
use Chiwichat\Users\Utils\Auth;
use Chiwichat\Users\Utils\HttpHelper;
use PDO;
use PDOException;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createUser($data) {
        try {
            v::arrayType()
                ->key('username', v::alnum()->noWhitespace()->length(3, 30)->setName('Usuario'))
                ->key('email', v::email()->setName('Correo electrónico'))
                ->key('nombre', v::stringType()->length(2, 20)->setName('Nombre'))
                ->key('apellido', v::stringType()->length(2, 20)->setName('Apellido'))
                ->assert($data);
    
        } catch (NestedValidationException $e) {
            $errors = $e->getMessages([
                'alnum' => '{{name}} debe contener solo letras y números',
                'noWhitespace' => '{{name}} no debe contener espacios',
                'length' => '{{name}} debe tener entre {{minValue}} y {{maxValue}} caracteres',
                'stringType' => '{{name}} debe ser texto',
                'email' => '{{name}} debe ser un correo electrónico válido'
            ]);
            return HttpHelper::sendJsonResponse(["errores" => $errors], 400);
        }
    
        try {
            $this->db->beginTransaction();
    
            // Generar llave secreta única
            $secretKey = bin2hex(random_bytes(32)); // 64 caracteres hexadecimales
    
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, nombre, apellido, `key`) 
                VALUES (:username, :email, :nombre, :apellido, :key)
            ");
            $stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
            $stmt->bindParam(':nombre', $data['nombre'], PDO::PARAM_STR);
            $stmt->bindParam(':apellido', $data['apellido'], PDO::PARAM_STR);
            $stmt->bindParam(':key', $secretKey, PDO::PARAM_STR);
            $stmt->execute();
    
            $id = $this->db->lastInsertId();
            $this->db->commit();
    
            return HttpHelper::sendJsonResponse([
                "mensaje" => "Usuario creado correctamente",
                "userId" => $id,
                "key" => $secretKey // Opcional: devolver la llave generada
            ], 201);
        } catch (PDOException $e) {
            $this->db->rollBack();
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al crear el usuario: " . $e->getMessage()], 
                500
            );
        }
    }

    public function searchUsers($searchTerm) {
        try {
            v::stringType()->length(2, 100)->setName('Término de búsqueda')->assert($searchTerm);
        } catch (NestedValidationException $e) {
            $errors = $e->getMessages([
                'stringType' => 'El término de búsqueda debe ser texto',
                'length' => 'El término de búsqueda debe tener entre {{minValue}} y {{maxValue}} caracteres'
            ]);
            return HttpHelper::sendJsonResponse(["errores" => $errors], 400);
        }
    
        try {
            $searchParam = "%$searchTerm%";
            $startParam = "$searchTerm%";
    
            $stmt = $this->db->prepare("
                SELECT id, username, email, nombre, apellido, `key`
                FROM users 
                WHERE username LIKE :search_username 
                OR email LIKE :search_email
                LIMIT 10
            ");
    
            // Bind de parámetros con nombres únicos
            $stmt->bindParam(':search_username', $searchParam, PDO::PARAM_STR);
            $stmt->bindParam(':search_email', $searchParam, PDO::PARAM_STR);
    
            $stmt->execute();
    
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            if (empty($users)) {
                return HttpHelper::sendJsonResponse(
                    ["mensaje" => "No se encontraron usuarios"], 
                    404
                );
            }
    
            return HttpHelper::sendJsonResponse([
                "mensaje" => "Usuarios encontrados",
                "total" => count($users),
                "usuarios" => $users
            ]);
        } catch (PDOException $e) {
            error_log("Error en searchUsers: " . $e->getMessage());
            return HttpHelper::sendJsonResponse(
                ["error" => "Error en la búsqueda: " . $e->getMessage()], 
                500
            );
        }
    }
    public function login($username) {
        try {
            v::stringType()->length(2, 100)->setName('Usuario')->assert($username);
        } catch (NestedValidationException $e) {
            $errors = $e->getMessages([
                'alnum' => '{{name}} debe contener solo letras y números',
                'noWhitespace' => '{{name}} no debe contener espacios',
                'length' => '{{name}} debe tener entre {{minValue}} y {{maxValue}} caracteres'
            ]);
            return HttpHelper::sendJsonResponse(["errores" => $errors], 400);
        }

        try {
            $stmt = $this->db->prepare("
                SELECT username 
                FROM users 
                WHERE username = :username
                OR email = :email
            ");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $username, PDO::PARAM_STR);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return HttpHelper::sendJsonResponse(
                    ["error" => "Usuario no registrado"], 
                    403
                );
            }

            $token = Auth::generateToken(["username" => $user["username"]]);
            return HttpHelper::sendJsonResponse([
                "mensaje" => "Inicio de sesión exitoso",
                "token" => $token
            ]);
        } catch (PDOException $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al iniciar sesión: " . $e->getMessage()], 
                500
            );
        }
    }

    public function checkUser() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return HttpHelper::sendJsonResponse(
                ["error" => "Token de autorización no proporcionado o formato inválido"], 
                401
            );
        }
        
        $token = $matches[1];
        
        try {
            $decoded = Auth::decodeToken($token);
            $username = $decoded['username'] ?? null;
            
            if (!$username) {
                return HttpHelper::sendJsonResponse(
                    ["error" => "Token inválido: usuario no especificado"], 
                    401
                );
            }
            
            $stmt = $this->db->prepare("
                SELECT username 
                FROM users 
                WHERE username = :username
            ");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return HttpHelper::sendJsonResponse(
                    ["error" => "Usuario no registrado en el sistema"], 
                    403
                );
            }
            
            return HttpHelper::sendJsonResponse([
                "mensaje" => "Token válido",
                "usuario" => $user['username']
            ]);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al verificar el token: " . $e->getMessage()], 
                401
            );
        }
    }
}