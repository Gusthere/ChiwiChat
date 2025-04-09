<?php

namespace ChiwiCrypt\Controller;

use ChiwiCrypt;
use Exception;
use Throwable;

class ApiController
{
  private $cryptoEngine;

  public function __construct()
  {
    $config = include __DIR__ . '/../../config/security.php';
    $keyManager = new ChiwiCrypt\KeyManager($config['keys_directory'], $config['master_key']);
    $this->cryptoEngine = new ChiwiCrypt\CryptoEngine($keyManager);
  }

  public function handleRequest()
  {
    $method = $_SERVER['REQUEST_METHOD'];
    $endpoint = $_GET['action'] ?? '';

    if ($endpoint == 'health') {
      http_response_code(200);
      echo "Service is healthy";
      exit();
    }
    
    try {
      switch ($method) {
        case 'POST':
          $data = json_decode(file_get_contents('php://input'), true);
          switch ($endpoint) {
            case 'generate-keys':
              if (!isset($data['user_id'])) {
                throw new Exception("El campo 'user_id' es obligatorio");
              }
              $response = $this->generateKeys(
                $data['user_id'],
              );
              break;

            case 'encrypt':
              $response = $this->encryptMessage(
                $data['sender_id'],
                $data['recipient_id'],
                $data['message']
              );
              break;

            case 'decrypt':
              $response = $this->decryptMessage(
                $data['messages'],
              );
              break;

            case 'sign':
              $response = $this->signMessage(
                $data['user_id'],
                $data['message'],
              );
              break;

            case 'verify':
              $response = $this->verifySignature(
                $data['sender_id'],
                $data['message'],
                $data['signature']
              );
              break;

            default:
              throw new Exception("Endpoint no válido");
          }
          break;

        case 'GET':
          switch ($endpoint) {
            case 'public-key':
              $response = $this->getPublicKey($_GET['user_id']);
              break;

            default:
              throw new Exception("Endpoint no válido");
          }
          break;

        default:
          throw new Exception("Método no soportado");
      }

      $this->sendResponse(200, $response);
    } catch (Throwable $e) {
      $this->sendResponse(400, ['error' => $e->getMessage()]);
    }
  }

  private function generateKeys($userId)
  {
    return $this->cryptoEngine->generateUserKeys($userId);
  }

  private function encryptMessage($senderId, $recipientId, $message)
  {
    return [
      'encrypted_message' => $this->cryptoEngine->encryptMessage($senderId, $recipientId, $message)
    ];
  }

  private function decryptMessage($messageArray)
  {
    return [
      'decrypted_message' => $this->cryptoEngine->decryptMessage($messageArray)
    ];
  }

  private function signMessage($userId, $message)
  {
    return [
      'signature' => $this->cryptoEngine->signMessage($userId, $message)
    ];
  }

  private function verifySignature($senderId, $message, $signature)
  {
    return [
      'is_valid' => $this->cryptoEngine->verifySignature($senderId, $message, $signature)
    ];
  }

  private function getPublicKey($userId)
  {
    $publicKey = $this->cryptoEngine->getPublicKey($userId);
    return [
      'public_key' => $publicKey ?? null
    ];
  }

  private function sendResponse($statusCode, $data)
  {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
  }
}
