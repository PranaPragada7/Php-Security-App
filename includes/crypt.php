<?php
declare(strict_types=1);
/**
 * AES Encryption Class
 * Secure Web Application - AES-256 Encryption/Decryption
 */

require_once __DIR__ . '/../config/bootstrap.php';

class AES {
    private $key;
    private $method;
    
    public function __construct() {
        $key = hex2bin(AES_KEY);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('AES_KEY must decode to exactly 32 bytes.');
        }

        $this->key = $key;
        $this->method = AES_METHOD;
    }
    
    /**
     * Encrypt data using AES-256-CBC
     * @param string $data Data to encrypt
     * @return string Encrypted data (IV + encrypted data, base64 encoded)
     */
    public function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        $iv_length = openssl_cipher_iv_length($this->method);
        $iv = random_bytes($iv_length);
        
        $encrypted = openssl_encrypt($data, $this->method, $this->key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            throw new Exception("Encryption failed");
        }
        
        // Prepend IV to encrypted data and encode
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data using AES-256-CBC
     * @param string $encrypted_data Encrypted data (base64 encoded)
     * @return string Decrypted data
     */
    public function decrypt($encrypted_data) {
        if (empty($encrypted_data)) {
            return '';
        }
        
        $data = base64_decode($encrypted_data, true);
        if ($data === false) {
            throw new Exception("Invalid encrypted data format");
        }
        
        $iv_length = openssl_cipher_iv_length($this->method);
        if (strlen($data) <= $iv_length) {
            throw new Exception('Encrypted data is truncated.');
        }
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        $decrypted = openssl_decrypt($encrypted, $this->method, $this->key, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
            throw new Exception("Decryption failed");
        }
        
        return $decrypted;
    }
    
    /**
     * Static method for quick encryption
     */
    public static function _encrypt($data) {
        $aes = new self();
        return $aes->encrypt($data);
    }
    
    /**
     * Static method for quick decryption
     */
    public static function _decrypt($encrypted_data) {
        $aes = new self();
        return $aes->decrypt($encrypted_data);
    }
}

