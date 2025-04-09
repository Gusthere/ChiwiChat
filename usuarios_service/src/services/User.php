<?php

namespace Chiwichat\Users\Services;

use Chiwichat\Users\Utils\Database;
use Chiwichat\Users\Utils\Auth;
use Chiwichat\Users\Utils\HttpHelper;
use PDO;
use PDOException;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

class User
{
    private $db;
    private $userData;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function setUserData($userData)
    {
        $this->userData = $userData;
    }

    public function createUser($data)
    {
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

            // Verificar credenciales existentes
            $credentialErrors = $this->checkExistingCredentials($data['username'], $data['email']);
            if ($credentialErrors) {
                return HttpHelper::sendJsonResponse([
                    "errores" => $credentialErrors
                ], 409);
            }

            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, nombre, apellido) 
                VALUES (:username, :email, :nombre, :apellido)
            ");
            $stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
            $stmt->bindParam(':nombre', $data['nombre'], PDO::PARAM_STR);
            $stmt->bindParam(':apellido', $data['apellido'], PDO::PARAM_STR);
            $stmt->execute();

            $id = $this->db->lastInsertId();
            $this->db->commit();

            return HttpHelper::sendJsonResponse([
                "mensaje" => "Usuario creado correctamente",
                "userId" => $id
            ], 201); //Creado correctamente
        } catch (PDOException $e) {
            $this->db->rollBack();
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al crear el usuario: " . $e->getMessage()],
                500
            );
        }
    }

    private function checkExistingCredentials($username, $email)
    {
        $stmt = $this->db->prepare("
            SELECT 
                username,
                email
            FROM users 
            WHERE username = :username OR email = :email
        ");

        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $errors = [];

        foreach ($results as $row) {
            if ($row['username'] === $username) {
                $errors['Usuario'] = 'Este Usuario ya está registrado';
            }
            if ($row['email'] === $email) {
                $errors['Correo electrónico'] = 'Este Correo electrónico ya está registrado';
            }
        }

        return empty($errors) ? null : $errors;
    }
    public function searchUsers($searchTerm)
    {
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
                SELECT id, username, email, nombre, apellido
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
            ], empty($users) ? 204 : 200);
        } catch (PDOException $e) {
            error_log("Error en searchUsers: " . $e->getMessage());
            return HttpHelper::sendJsonResponse(
                ["error" => "Ha ocurrido un error en la búsqueda"],
                500
            );
        }
    }

    public function login($username)
    {
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
                SELECT username, id
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

            $token = Auth::generateToken(["username" => $user["username"], "id" => $user["id"]]);
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

    // public function checkUser()
    // {
    //     try {
    //         $stmt = $this->db->prepare("
    //             SELECT username 
    //             FROM users 
    //             WHERE username = :username
    //             LIMIT 1
    //         ");
    //         $stmt->bindParam(':username', $this->userData['username'], PDO::PARAM_STR);
    //         $stmt->execute();

    //         $user = $stmt->fetch(PDO::FETCH_ASSOC);

    //         if (!$user) {
    //             throw new \Exception("Credenciales inválidas");
    //         }

    //         return HttpHelper::sendJsonResponse([
    //             "mensaje" => "Token válido",
    //             "usuario" => $user['username']
    //         ]);
    //     } catch (\Exception $e) {
    //         return HttpHelper::sendJsonResponse(
    //             ["error" => "Error al verificar el token: " . $e->getMessage()],
    //             401
    //         );
    //     }
    // }

    public function Me()
    {
        try {
            // Verificar que la clave del token coincida con la de la base de datos
            $stmt = $this->db->prepare("
                SELECT *
                FROM users 
                WHERE username = :username
                LIMIT 1
            ");
            $stmt->bindParam(':username', $this->userData['username'], PDO::PARAM_STR);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new \Exception("Credenciales inválidas");
            }

            return HttpHelper::sendJsonResponse([
                "mensaje" => "Datos del usuario",
                "usuario" => $user
            ]);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => $e->getMessage()],
                401
            );
        }
    }

    // public function getUserPublicKey($data)
    // {
    //     try {
    //         // Validar que se proporcionó un ID de usuario
    //         v::arrayType()
    //             ->key('username', v::stringType()->notEmpty())
    //             ->assert($data);
    //     } catch (NestedValidationException $e) {
    //         return HttpHelper::sendJsonResponse(["errores" => $e->getMessages()], 400);
    //     }

    //     $userId = $data['username'];
    //     $thirdPartyApiUrl = 'https://api.terceros.com/public-keys/' . urlencode($userId);

    //     try {
    //         // Configurar la petición cURL
    //         $ch = curl_init($thirdPartyApiUrl);
    //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //         curl_setopt($ch, CURLOPT_HTTPHEADER, [
    //             'Content-Type: application/json'
    //         ]);

    //         // Ejecutar la petición
    //         $response = curl_exec($ch);
    //         $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //         curl_close($ch);

    //         // Verificar respuesta
    //         if ($httpCode !== 200) {
    //             return HttpHelper::sendJsonResponse(
    //                 ["error" => "No se pudo obtener la clave pública del usuario"],
    //                 502
    //             );
    //         }

    //         $decodedResponse = json_decode($response, true);

    //         if (json_last_error() !== JSON_ERROR_NONE) {
    //             return HttpHelper::sendJsonResponse(
    //                 ["error" => "Respuesta inválida de la API de claves"],
    //                 502
    //             );
    //         }

    //         if (!isset($decodedResponse['public_key'])) {
    //             return HttpHelper::sendJsonResponse(
    //                 ["error" => "La API no devolvió una clave pública válida"],
    //                 502
    //             );
    //         }

    //         return HttpHelper::sendJsonResponse([
    //             "mensaje" => "Clave pública obtenida correctamente",
    //             "public_key" => $decodedResponse['public_key'],
    //             "username" => $userId
    //         ]);
    //     } catch (\Exception $e) {
    //         return HttpHelper::sendJsonResponse(
    //             ["error" => "Error al comunicarse con la API de claves: " . $e->getMessage()],
    //             500
    //         );
    //     }
    // }
}
