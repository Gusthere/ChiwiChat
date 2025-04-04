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
            // Configurar mensajes personalizados para cada regla
            v::arrayType()
                ->key('username', v::alnum()->noWhitespace()->length(3, 30)
                    ->setName('Usuario')
                    ->setTemplate('{{name}} debe contener solo letras y números (3-30 caracteres)'))
                ->key('email', v::email()
                    ->setName('Correo electrónico')
                    ->setTemplate('{{name}} debe tener un formato válido'))
                ->key('nombre', v::stringType()->length(2, 20)
                    ->setName('Nombre')
                    ->setTemplate('{{name}} debe tener entre 2 y 20 caracteres'))
                ->key('apellido', v::stringType()->length(2, 20)
                    ->setName('Apellido')
                    ->setTemplate('{{name}} debe tener entre 2 y 20 caracteres'))
                ->assert($data);
    
        } catch (NestedValidationException $e) {
            $errors = $e->getMessages();
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
            v::stringType()->length(2, 100)
                ->setName('Término de búsqueda')
                ->setTemplate('El término de búsqueda debe tener entre 2 y 100 caracteres')
                ->assert($searchTerm);
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["errores" => $e->getMessages()], 400);
        }
    
        try {
            $searchParam = "%$searchTerm%";
    
            $stmt = $this->db->prepare("
                SELECT id, username, email, nombre, apellido, `key`
                FROM users 
                WHERE username LIKE :username 
                OR email LIKE :email
                LIMIT 10
            ");
    
            $stmt->bindParam(':username', $searchParam, PDO::PARAM_STR);
            $stmt->bindParam(':email', $searchParam, PDO::PARAM_STR);
            $stmt->execute();
    
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            return HttpHelper::sendJsonResponse([
                "mensaje" => empty($users) ? "No se encontraron usuarios" : "Usuarios encontrados",
                "total" => count($users),
                "usuarios" => $users
            ], empty($users) ? 404 : 200);
        } catch (PDOException $e) {
            error_log("Error en searchUsers: " . $e->getMessage());
            return HttpHelper::sendJsonResponse(
                ["error" => "Ha ocurrido un error en la búsqueda"], 
                500
            );
        }
    }
    
    public function login($username) {
        try {
            v::stringType()->length(2, 100)
                ->setName('Usuario')
                ->setTemplate('El nombre de usuario o correo debe tener entre 2 y 100 caracteres')
                ->assert($username);
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["errores" => $e->getMessages()], 400);
        }

        try {
            $stmt = $this->db->prepare("
                SELECT username 
                FROM users 
                WHERE username = :username OR email = :email
                LIMIT 1
            ");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $username, PDO::PARAM_STR);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return HttpHelper::sendJsonResponse(
                    ["error" => "Usuario no registrado"], 
                    404
                );
            }

            $token = Auth::generateToken(["username" => $user["username"]]);
            return HttpHelper::sendJsonResponse([
                "mensaje" => "Inicio de sesión exitoso",
                "token" => $token,
                "usuario" => $user["username"]
            ]);
        } catch (PDOException $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al iniciar sesión: " . $e->getMessage()], 
                500
            );
        }
    }

    public function checkUser() {
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
                SELECT username 
                FROM users 
                WHERE username = :username
                LIMIT 1
            ");
            $stmt->bindParam(':username', $decoded['username'], PDO::PARAM_STR);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new \Exception("Credenciales inválidas");
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