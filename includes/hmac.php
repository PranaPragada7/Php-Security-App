<?php
declare(strict_types=1);
// HMAC operations - SHA-256 HMAC generation and verification for data integrity

require_once __DIR__ . '/../config/bootstrap.php';

class HMAC {
    private static function encodeFields(array $fields): string {
        return implode('', array_map(
            static fn ($field): string => strlen((string) $field) . ':' . (string) $field,
            $fields
        ));
    }

    public static function generate($data) {
        if (!defined('HMAC_SECRET_KEY')) {
            throw new Exception("HMAC_SECRET_KEY is not defined");
        }
        return hash_hmac('sha256', (string) $data, HMAC_SECRET_KEY);
    }

    public static function verify($data, $hmac) {
        $expected = self::generate($data);
        return hash_equals($expected, $hmac);
    }

    public static function generateForJob($job_name, $opn_number) {
        $data = self::encodeFields([$job_name, $opn_number]);
        return self::generate($data);
    }
    public static function verifyJob($job_name, $opn_number, $hmac) {
        $data = self::encodeFields([$job_name, $opn_number]);
        return self::verify($data, $hmac);
    }

    /**
     * Generate HMAC for user data (username, email, name)
     * @param string $username
     * @param string $email
     * @param string $name
     * @return string HMAC value
     */
    public static function generateForUser($username, $email, $name) {
        $data = self::encodeFields([$username, $email, $name]);
        return self::generate($data);
    }

    /**
     * Verify HMAC for user data
     * @param string $username
     * @param string $email
     * @param string $name
     * @param string $hmac
     * @return bool True if HMAC is valid
     */
    public static function verifyUser($username, $email, $name, $hmac) {
        $data = self::encodeFields([$username, $email, $name]);
        return self::verify($data, $hmac);
    }
}
