<?php
class CryptoService {
    private const PREFIX = 'ENCv1:';

    public static function encrypt($plainText) {
        $plainText = (string) $plainText;
        if ($plainText === '') {
            return '';
        }

        $key = self::resolveKey();
        if ($key === '') {
            return $plainText;
        }

        if (strpos($plainText, self::PREFIX) === 0) {
            return $plainText;
        }

        $iv = random_bytes(16);
        $cipherRaw = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipherRaw === false) {
            return $plainText;
        }

        $hmac = hash_hmac('sha256', $iv . $cipherRaw, $key, true);
        return self::PREFIX . base64_encode($iv . $hmac . $cipherRaw);
    }

    public static function decrypt($cipherText) {
        $cipherText = (string) $cipherText;
        if ($cipherText === '') {
            return '';
        }

        if (strpos($cipherText, self::PREFIX) !== 0) {
            return $cipherText;
        }

        $encoded = substr($cipherText, strlen(self::PREFIX));
        $blob = base64_decode($encoded, true);
        if ($blob === false || strlen($blob) < 49) {
            return '';
        }

        $key = self::resolveKey();
        if ($key === '') {
            return '';
        }

        $iv = substr($blob, 0, 16);
        $hmac = substr($blob, 16, 32);
        $cipherRaw = substr($blob, 48);

        $calcHmac = hash_hmac('sha256', $iv . $cipherRaw, $key, true);
        if (!hash_equals($hmac, $calcHmac)) {
            return '';
        }

        $plainText = openssl_decrypt($cipherRaw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $plainText === false ? '' : (string) $plainText;
    }

    private static function resolveKey() {
        $raw = (string) (defined('APP_ENCRYPTION_KEY') ? APP_ENCRYPTION_KEY : '');
        if ($raw === '') {
            return '';
        }

        return hash('sha256', $raw, true);
    }
}
