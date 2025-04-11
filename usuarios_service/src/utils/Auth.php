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
    public static function generateToken($data, $exp = 3600, $key = null)
    {
        // Definir la clave por defecto según el tipo de token
        $key = $key ?? 'JWT_SECRET';
        
        $payload = [
            'iat' => time(),
            'exp' => time() + $exp,
            'data' => $data
        ];
        return JWT::encode($payload, Env::env($key), 'HS256');
    }

    public static function validateToken($key = null)
    {
        try {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';
            
            if (empty($authHeader)) {
                throw new Exception("No authorization token provided");
            }
            
            $token = str_replace('Bearer ', '', $authHeader);
            $key = $key ?? 'JWT_SECRET';
            
            $decoded = JWT::decode($token, new Key(Env::env($key), 'HS256'));

            $decodedArray = json_decode(json_encode($decoded), true);
            $data = $decodedArray['data'] ?? null;

            // Verificar datos esenciales del usuario
            if (empty($data['username'])) {
                throw new \Exception(
                    'Token inválido: falta el nombre de usuario', 
                    401 // Unauthorized
                );
            }

            $data['token'] = $token;

            return $data;
            
        } catch (ExpiredException $e) {
            $refresh = ($key == 'JWT_SECRET');
            self::handleError(
                'El token ha expirado', 
                401, // Unauthorized
                ['puedeRefrescar' => $refresh]
            );
        } catch (BeforeValidException $e) {
            self::handleError(
                'El token aún no es válido', 
                401, // Unauthorized
                ['tokenNoValidoAun' => true]
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