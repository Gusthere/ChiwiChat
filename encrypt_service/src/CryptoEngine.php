<?php

namespace ChiwiCrypt;

use Exception;
use InvalidArgumentException;
use RuntimeException;

class CryptoEngine
{
  private $keyManager;

  public function __construct(KeyManager $keyManager)
  {
    $this->keyManager = $keyManager;
  }

  /**
   * Genera un nuevo par de claves para un usuario
   */
  public function generateUserKeys($userId)
  {
    try {
      $passphrase = bin2hex(random_bytes(16));
      $config = [
        "config" => $_ENV["OPENSSL_DIR"],
        "digest_alg" => "sha512",
        "private_key_bits" => 4096,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
        "encrypt_key" => (bool) $passphrase,
        "encrypt_key_cipher" => OPENSSL_CIPHER_AES_256_CBC
      ];
      $keyPair = openssl_pkey_new($config);
      if (!$keyPair) {
        throw new Exception("No se pudo generar la clave privada: " . openssl_error_string());
      }
      // Extraer clave privada (cifrada con passphrase)
      openssl_pkey_export($keyPair, $privateKey, $passphrase, $config);

      if ($passphrase) {
        $this->keyManager->storePassphrase($userId, $passphrase);
      }
      // Extraer clave pública
      $keyDetails = openssl_pkey_get_details($keyPair);
      $publicKey = $keyDetails['key'];
      // Almacenar las claves
      $this->keyManager->storeKeys($userId, $publicKey, $privateKey);
      return [
        'user_id' => $userId,
        'public_key' => $publicKey,
        'private_key_stored' => true
      ];
    } catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

  /**
   * Cifra un mensaje para un destinatario específico
   */
  public function encryptMessage($senderId, $recipientId, $message)
  {
    $recipientPublicKey = $this->keyManager->getPublicKey($recipientId);

    if (!$recipientPublicKey) {
      throw new Exception("Clave pública del destinatario no disponible");
    }

    $chunks = str_split($message, 200); // RSA tiene límite de tamaño
    $encrypted = '';

    foreach ($chunks as $chunk) {
      openssl_public_encrypt($chunk, $encryptedChunk, $recipientPublicKey);
      $encrypted .= $encryptedChunk;
    }

    return base64_encode($encrypted);
  }

  /**
   * Descifra un mensaje recibido
   */
  public function decryptMessage(array $messageArray): array
  {
    $decryptedMessages = [];
    if (empty($messageArray)) {
      throw new InvalidArgumentException("El array de mensajes esta vacío");
    }
    foreach ($messageArray as $message) {
      if (!$message || !isset($message["userId"]) || !isset($message["encryptedMessage"]) || !isset($message["date"])) {
        throw new InvalidArgumentException("Faltan valores del mensaje");
      }
      $privateKey = $this->keyManager->getPrivateKey($message['userId']);
      if (!$privateKey) {
        throw new RuntimeException("No se pudo cargar la clave privada. Verifica la passphrase.");
      }
      $data = base64_decode($message['encryptedMessage']);
      if ($data === false) {
        throw new RuntimeException("Mensaje cifrado no es un Base64 válido");
      }
      $chunkSize = 512; // Para RSA 4096 bits
      $decrypted = '';
      $errors = [];
      foreach (str_split($data, $chunkSize) as $chunk) {
        $success = @openssl_private_decrypt($chunk, $decryptedChunk, $privateKey);
        if (!$success) {
          $errors[] = openssl_error_string();
          throw new RuntimeException("Errores durante el descifrado: " . implode(", ", array_unique($errors)));
        }
        $decrypted .= $decryptedChunk;
      }
      array_push($decryptedMessages, ['userId' => $message['userId'], 'message' => $decrypted, 'date' => $message["date"]]);
    }
    return $decryptedMessages;
  }

  /**
   * Firma un mensaje
   */
  public function signMessage($userId, $message)
  {
    $privateKey = $this->keyManager->getPrivateKey($userId);
    openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA512);
    return base64_encode($signature);
  }

  /**
   * Verifica una firma
   */
  public function verifySignature($senderId, $message, $signature)
  {
    $publicKey = $this->keyManager->getPublicKey($senderId);
    $sig = base64_decode($signature);
    return openssl_verify($message, $sig, $publicKey, OPENSSL_ALGO_SHA512) === 1;
  }

  /**
   * Retorna una clave pública de un usuario
   */
  public function getPublicKey($userId)
  {
    $publicKey = $this->keyManager->getPublicKey($userId);
    if (empty($publicKey) || !$publicKey) {
      throw new Exception("Ha ocurrido un error al obtener la llave pública");
    }
    $keyDetails = openssl_pkey_get_details($publicKey);
    return $keyDetails["key"];
  }
}
