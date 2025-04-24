<?php

namespace Chiwichat\Users\Services;

use Chiwichat\Users\Utils\Database;
use Chiwichat\Users\Utils\Auth;
use Chiwichat\Users\Utils\HttpHelper;
use PDO;
use PDOException;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;
use Dotenv\Dotenv;
use Chiwichat\Users\Utils\Env;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

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
            return HttpHelper::sendJsonResponse(["error" => $errors], 400);
        }

        try {
            $this->db->beginTransaction();

            // Verificar credenciales existentes
            $credentialErrors = $this->checkExistingCredentials($data['username'], $data['email']);
            if ($credentialErrors) {
                return HttpHelper::sendJsonResponse([
                    "error" => $credentialErrors
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

            $url = Env::env('URL_CRYPTO') . '?action=generate-keys';
            $jsonData = [
                "userId" => $id
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);

            // Ejecutar la petición
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Verificar respuesta
            if ($httpCode !== 200) {
                $this->db->rollBack();
                $error = json_decode($response, true);
                return HttpHelper::sendJsonResponse(
                    [
                        "error" => "No se pudo obtener la clave pública del usuario",
                        "detalles" => $error
                    ],
                    502
                );
            }

            $decodedResponse = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->db->rollBack();
                return HttpHelper::sendJsonResponse(
                    [
                        "error" => "Respuesta inválida de la API de claves",
                        "detalles" => $decodedResponse
                    ],
                    502
                );
            }

            if (!isset($decodedResponse['userId'])) {
                $this->db->rollBack();
                return HttpHelper::sendJsonResponse(
                    [
                        "error" => "La API no devolvió una clave pública válida",
                        "detalles" => $decodedResponse
                    ],
                    502
                );
            }

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
            return HttpHelper::sendJsonResponse(["error" => $e->getMessages()], 400);
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
            return HttpHelper::sendJsonResponse(["error" => $e->getMessages()], 400);
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
            $refreshToken = Auth::generateToken(["username" => $user["username"], "id" => $user["id"]], 7 * 24 * 60 * 60, 'JWT_SECRET_REFRESH');

            $updateTokenQuery = $this->db->prepare("UPDATE users SET refreshToken = :refreshToken WHERE id = :id");
            $updateTokenQuery->execute(['refreshToken' => $refreshToken, 'id' => $user['id']]);

            return HttpHelper::sendJsonResponse([
                "mensaje" => "Inicio de sesión exitoso",
                "token" => $token,
                "refreshToken" => $refreshToken,
                "usuario" => $user["username"]
            ]);
        } catch (PDOException $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al iniciar sesión: " . $e->getMessage()],
                500
            );
        }
    }

    public function refreshAccessToken()
    {
        try {
            $query = $this->db->prepare("SELECT id, username, refreshToken FROM users WHERE id = :id AND username = :username AND refreshToken = :refreshToken");

            // Bind los parámetros
            $query->bindParam(':id', $this->userData['id'], PDO::PARAM_INT);
            $query->bindParam(':username', $this->userData['username'], PDO::PARAM_STR);
            $query->bindParam(':refreshToken', $this->userData['token'], PDO::PARAM_STR);

            $query->execute();
            $user = $query->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return HttpHelper::sendJsonResponse(
                    ['error' => 'Invalid refresh token'],
                    403
                );
            }

            $accessToken = Auth::generateToken(['id' => $user['id'], 'username' => $user['username']]);
            $refreshToken = Auth::generateToken(["username" => $user["username"], "id" => $user["id"]], 7 * 24 * 60 * 60, 'JWT_SECRET_REFRESH');

            $updateTokenQuery = $this->db->prepare("UPDATE users SET refreshToken = :refreshToken WHERE id = :id");
            $updateTokenQuery->execute(['refreshToken' => $refreshToken, 'id' => $user['id']]);

            return HttpHelper::sendJsonResponse(
                [
                    "token" => $accessToken,
                    "refreshToken" => $refreshToken
                ]
            );
        } catch (PDOException $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al verificar información: " . $e->getMessage()],
                500
            );
        }
    }

    public function Me()
    {
        try {
            // Verificar que la clave del token coincida con la de la base de datos
            $stmt = $this->db->prepare("
                SELECT id, username, nombre, apellido, email, createTime
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

    public function getUserPublicKey($data)
    {
        try {
            // Validar que se proporcionó un ID de usuario
            v::arrayType()
                ->key('username', v::stringType()->notEmpty())
                ->assert($data);
        } catch (NestedValidationException $e) {
            return HttpHelper::sendJsonResponse(["error" => $e->getMessages()], 400);
        }

        $stmt = $this->db->prepare("SELECT id FROM users Where username = :username");
        $stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user)
            return HttpHelper::sendJsonResponse(
                [
                    "error" => "Usuario no registrado"
                ],
                404
            );

        $userId = $user['id'];

        $thirdPartyApiUrl = Env::env('URL_CRYPTO') . '?action=public-key&userId=' . urlencode($userId);
        try {
            // Configurar la petición cURL
            $ch = curl_init($thirdPartyApiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            // Ejecutar la petición
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Verificar respuesta
            if ($httpCode !== 200) {
                $error = json_decode($response, true);
                return HttpHelper::sendJsonResponse(
                    [
                        "error" => "No se pudo obtener la clave pública del usuario",
                        "detalles" => $error
                    ],
                    502
                );
            }

            $decodedResponse = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return HttpHelper::sendJsonResponse(
                    [
                        "error" => "Respuesta inválida de la API de claves",
                        "detalles" => $decodedResponse
                    ],
                    502
                );
            }

            if (!isset($decodedResponse['publicKey'])) {
                return HttpHelper::sendJsonResponse(
                    [
                        "error" => "La API no devolvió una clave pública válida",
                        "detalles" => $decodedResponse
                    ],
                    502
                );
            }

            return HttpHelper::sendJsonResponse([
                "mensaje" => "Clave pública obtenida correctamente",
                "publicKey" => $decodedResponse['publicKey']
            ]);
        } catch (\Exception $e) {
            return HttpHelper::sendJsonResponse(
                ["error" => "Error al comunicarse con la API de claves: " . $e->getMessage()],
                500
            );
        }
    }
}
