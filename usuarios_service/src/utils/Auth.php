<?php

namespace Chiwichat\Users\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use Dotenv\Dotenv;


class Auth {
    public static function generateToken($data) {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $payload = [
            'iat' => time(),
            'exp' => time() + (60 * 60), // 1 hora
            'data' => $data
        ];
        return JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
    }

    public static function decodeToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            $decodedArray = json_decode(json_encode($decoded), true);  
            return $decodedArray['data'] ?? null;
        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
}