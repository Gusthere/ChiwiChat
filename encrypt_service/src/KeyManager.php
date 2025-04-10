<?php
namespace ChiwiCrypt;
use ChiwiCrypt\utils\Validate;
class KeyManager
{
  private $keysDir;
  private $encryptionKey;
  private $passphraseVault;

  public function __construct($keysDir, $encryptionKey = null)
  {
    $this->keysDir = rtrim($keysDir, '/') . '/';
    if (!file_exists($this->keysDir)) {
      mkdir($this->keysDir, 0700, true);
    }
    $this->encryptionKey = $encryptionKey ?: $this->generateEncryptionKey();
    $this->passphraseVault = $this->initVault();
  }
  private function initVault()
  {
    $vaultFile = "{$this->keysDir}vault.dat";
    if (!file_exists($vaultFile)) {
      file_put_contents($vaultFile, json_encode(['vault' => [], 'iv' => base64_encode(random_bytes(16))]));
      chmod($vaultFile, 0600);
    }
    $data = file_get_contents($vaultFile);
    if (empty($data)) {
      return ['vault' => [], 'iv' => random_bytes(16)];
    }
    $decoded = json_decode($data, true);
    if (!$decoded || !isset($decoded['iv'])) {
      // Si hay error en el JSON o falta el IV, crear nuevo vault
      $decoded = ['vault' => [], 'iv' => random_bytes(16)];
      file_put_contents($vaultFile, json_encode($decoded));
      chmod($vaultFile, 0600);
    } else {
      // Asegurar que el IV está en formato binario
      $decoded['iv'] = base64_decode($decoded['iv']);
    }

    return $decoded;
  }

  private function saveVault(): void
  {
    $vaultFile = "{$this->keysDir}vault.dat";
    // Preparar datos para guardar (codificar IV en base64)
    $saveData = $this->passphraseVault;
    $saveData['iv'] = base64_encode($saveData['iv']);
    // Guardar con bloqueo de archivo
    $fp = fopen($vaultFile, 'w');
    if ($fp && flock($fp, LOCK_EX)) {
      fwrite($fp, json_encode($saveData, JSON_PRETTY_PRINT));
      flock($fp, LOCK_UN);
      fclose($fp);
      chmod($vaultFile, 0600);
    } else {
      throw new \RuntimeException("No se pudo bloquear el archivo vault para escritura");
    }
    return;
  }

  public function storePassphrase(array $data): void
  {
    $keys = ['userId', 'passphrase'];
    $validArray = Validate::validateArray($data, $keys);
    if (!empty($validArray)) {
      throw new \InvalidArgumentException("Faltan valores del passphrase: " . implode(", ", $validArray));
    }
    // Asegurar que el IV está disponible
    if (!isset($this->passphraseVault['iv'])) {
      $this->passphraseVault['iv'] = random_bytes(16);
    }
    $encrypted = openssl_encrypt(
      $data['passphrase'],
      'aes-256-cbc',
      $this->encryptionKey,
      OPENSSL_RAW_DATA,
      $this->passphraseVault['iv']
    );
    if (empty($encrypted)) {
      throw new \Exception("Error cifrando passphrase: " . openssl_error_string());
    }
    $this->passphraseVault['vault'][$data['userId']] = base64_encode($encrypted);
    $this->saveVault();
    return;
  }
  public function getPassphrase($userId)
  {
    if (!isset($this->passphraseVault['vault'][$userId])) {
      throw new \UnexpectedValueException("Error al obtener la passphrase del usuario", 1);
    }
    return openssl_decrypt(
      base64_decode($this->passphraseVault['vault'][$userId]),
      'aes-256-cbc',
      $this->encryptionKey,
      OPENSSL_RAW_DATA,
      $this->passphraseVault['iv']
    );
  }
  public function storeKeys($userId, $publicKey, $privateKey): void
  {
    if ($this->keyExists($userId)) {
      throw new \InvalidArgumentException("Ya existe la clave de este usuario");
    }
    // Almacenar clave pública
    file_put_contents("{$this->keysDir}{$userId}_public.pem", $publicKey);
    chmod("{$this->keysDir}{$userId}_public.pem", 0644);
    // Cifrar y almacenar clave privada
    $encryptedPrivate = $this->encryptPrivateKey($privateKey);
    file_put_contents("{$this->keysDir}{$userId}_private.enc", $encryptedPrivate);
    chmod("{$this->keysDir}{$userId}_private.enc", 0600);
    return;
  }

  public function getPublicKey(string $userId)
  {
    $file = "{$this->keysDir}{$userId}_public.pem";
    if (!file_exists($file)) {
      throw new \UnexpectedValueException("No existe llave pública para este usuario");

    }
    return openssl_pkey_get_public(file_get_contents($file)) ?? null;
  }

  public function getPrivateKey(string $userId)
  {
    $file = "{$this->keysDir}{$userId}_private.enc";
    if (!file_exists($file)) {
      throw new \RuntimeException("No se encontró la clave privada del usuario");
    }
    $passphrase = $this->getPassphrase($userId);
    if (empty($passphrase)) {
      throw new \UnexpectedValueException("No se encontró la passphrase del usuario");
    }
    $encrypted = file_get_contents($file);
    $privateKeyPem = $this->decryptPrivateKey($encrypted);
    if ($passphrase) {
      $privateKey = openssl_pkey_get_private($privateKeyPem, $passphrase);
      if (empty($privateKey)) {
        throw new \Exception("Error al descifrar clave privada con passphrase: " . openssl_error_string());
      }
      return $privateKey;
    }
    return openssl_pkey_get_private($privateKeyPem);
  }

  private function encryptPrivateKey($privateKey): string
  {
    // Cifrado adicional con clave maestra
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt(
      $privateKey,
      'aes-256-cbc',
      $this->encryptionKey,
      OPENSSL_RAW_DATA,
      $iv
    );
    return base64_encode($iv . $encrypted);
  }

  private function decryptPrivateKey($encrypted, $passphrase = null)
  {
    if (empty($encrypted)) {
      throw new \InvalidArgumentException("No se recibio la llave encriptada");
    }
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt(
      $encrypted,
      'aes-256-cbc',
      $this->encryptionKey,
      OPENSSL_RAW_DATA,
      $iv
    );
  }

  private function generateEncryptionKey()
  {
    return hash('sha256', uniqid('', true) . bin2hex(random_bytes(16)));
  }

  private function keyExists($userId)
  {
    $files = scandir($this->keysDir);
    return in_array($userId . "_public.pem", $files);
  }
}