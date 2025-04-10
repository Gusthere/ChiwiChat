<?php

namespace ChiwiCrypt\Controller;

use ChiwiCrypt\utils\RequestHelper;
use ChiwiCrypt\utils\Validate;

class ApiController extends RequestHelper
{
  private $cryptoEngine;

  public function __construct()
  {
    $config = include __DIR__ . '/../../config/security.php';
    $keyManager = new \ChiwiCrypt\KeyManager($config['keys_directory'], $config['master_key']);
    $this->cryptoEngine = new \ChiwiCrypt\CryptoEngine($keyManager);
  }

  public function handleRequest()
  {
    $method = $_SERVER['REQUEST_METHOD'];
    $endpoint = $_GET['action'] ?? '';
    try {
      switch ($method) {
        case 'POST':
          $data = $this->getPostData();
          switch ($endpoint) {
            case 'generate-keys':
              $response = $this->generateKeys(
                $data['userId'] ?? null,
              );
              break;

            case 'encrypt':
              $response = $this->encryptMessage(
                [
                  'senderId' => $data['senderId'] ?? null,
                  'recipientId' => $data['recipientId'] ?? null,
                  'message' => $data['message'] ?? null
                ]
              );
              break;

            case 'decrypt':
              $response = $this->decryptMessage(
                $data['messages'] ?? null,
              );
              break;

            case 'sign':
              $response = $this->signMessage(
                $data['userId'],
                $data['message'],
              );
              break;

            case 'verify':
              $response = $this->verifySignature(
                $data['senderId'],
                $data['message'],
                $data['signature']
              );
              break;

            default:
              throw new \InvalidArgumentException("Endpoint no válido");
          }
          break;

        case 'GET':
          switch ($endpoint) {
            case 'public-key':
              $response = $this->getPublicKey($_GET['userId'] ?? null);
              break;

            default:
              throw new \InvalidArgumentException("Endpoint no válido");
          }
          break;

        default:
          throw new \Exception("Método no soportado");
      }

      $this->sendResponse(200, $response);
    } catch (\TypeError $e) {
      $cleanMessage = Validate::parseTypeErrorMessage($e->getMessage());
      $this->sendResponse(400, ['error' => $cleanMessage]);
    } catch (\Throwable $e) {
      $this->sendResponse(400, ['error' => $e->getMessage()]);
    }
  }

  private function generateKeys(string $userId): array
  {
    return $this->cryptoEngine->generateUserKeys($userId);
  }

  private function encryptMessage(array $data): array
  {
    return [
      'encryptedMessage' => $this->cryptoEngine->encryptMessage($data)
    ];
  }

  private function decryptMessage(array $messageArray): array
  {
    return [
      'decryptedMessage' => $this->cryptoEngine->decryptMessage($messageArray)
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
      'isValid' => $this->cryptoEngine->verifySignature($senderId, $message, $signature)
    ];
  }

  private function getPublicKey(string $userId): array
  {
    $publicKey = $this->cryptoEngine->getPublicKey($userId);
    return [
      'publicKey' => $publicKey ?? null
    ];
  }

}
