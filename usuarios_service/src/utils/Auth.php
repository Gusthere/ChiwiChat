<?php

namespace Chiwichat\Users\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use DomainException;
use InvalidArgumentException;
use UnexpectedValueException;
use Exception;
use Dotenv\Dotenv;
use Chiwichat\Users\Utils\Env;


$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

class Auth
{
    public static function generateToken($data)
    {

        $payload = [
            'iat' => time(),
            'exp' => time() + (60 * 60), // 1 hora
            'data' => $data
        ];
        return JWT::encode($payload, Env::env('JWT_SECRET'), 'HS256');
    }

    public static function validateToken()
    {
        try {
            // Verificar si el encabezado Authorization existe
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';
            
            if (empty($authHeader)) {
                throw new \Exception(
                    'Encabezado de autorización faltante', 
                    400 // Bad Request
                );
            }

            // Extraer el token
            if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                throw new \Exception(
                    'Formato de autorización inválido. Se esperaba: Bearer {token}', 
                    400 // Bad Request
                );
            }

            $token = $matches[1];

            // Decodificar el token
            $decoded = JWT::decode($token, new Key(Env::env('JWT_SECRET'), 'HS256'));
            $decodedArray = json_decode(json_encode($decoded), true);
            $data = $decodedArray['data'] ?? null;

            // Verificar datos esenciales del usuario
            if (empty($data['username'])) {
                throw new \Exception(
                    'Token inválido: falta el nombre de usuario', 
                    401 // Unauthorized
                );
            }

            return $data;
            
        } catch (ExpiredException $e) {
            self::handleError(
                'El token ha expirado', 
                401, // Unauthorized
                ['token_expirado' => true, 'puede_refrescar' => true]
            );
        } catch (BeforeValidException $e) {
            self::handleError(
                'El token aún no es válido', 
                401, // Unauthorized
                ['token_no_valido_aun' => true]
            );
        } catch (SignatureInvalidException $e) {
            self::handleError(
                'Firma del token inválida', 
                401 // Unauthorized
            );
        } catch (DomainException | InvalidArgumentException $e) {
            self::handleError(
                'Formato de token inválido', 
                400 // Bad Request
            );
        } catch (UnexpectedValueException $e) {
            self::handleError(
                'Valor del token inesperado', 
                400 // Bad Request
            );
        } catch (Exception $e) {
            self::handleError(
                $e->getMessage(), 
                method_exists($e, 'getCode') && $e->getCode() !== 0 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Maneja los errores de forma consistente usando HttpHelper
     * 
     * @param string $message Mensaje de error en español
     * @param int $statusCode Código HTTP
     * @param array $additionalData Información adicional
     */
    private static function handleError(string $message, int $statusCode = 401, array $additionalData = [])
    {
        $responseData = [
            'error' => $message
        ];

        if (!empty($additionalData)) {
            $responseData = array_merge($responseData, $additionalData);
        }

        HttpHelper::sendJsonResponse($responseData, $statusCode);
        exit;
    }
}