<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class AiCustomerServiceSecretVault
{
    public function isReady(): bool
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            return false;
        }
        try {
            return strlen($this->configuredKey()) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function encrypt(string $secret): string
    {
        $key = $this->configuredKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($secret, $nonce, $key);
        return 'v1:' . base64_encode($nonce) . ':' . base64_encode($ciphertext);
    }

    public function decrypt(?string $encrypted): string
    {
        if ($encrypted === null || $encrypted === '') {
            return '';
        }
        $parts = explode(':', $encrypted, 3);
        if (count($parts) !== 3 || $parts[0] !== 'v1') {
            throw new RuntimeException('Bí mật đã lưu không đúng định dạng mã hóa.');
        }
        $nonce = base64_decode($parts[1], true);
        $ciphertext = base64_decode($parts[2], true);
        if (!is_string($nonce) || !is_string($ciphertext)
            || strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Bí mật đã lưu không thể giải mã.');
        }

        try {
            $plain = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->configuredKey());
        } catch (\SodiumException) {
            $plain = false;
        }
        if (!is_string($plain)) {
            throw new RuntimeException('Bí mật đã lưu không thể giải mã; hãy kiểm tra khóa mã hóa.');
        }
        return $plain;
    }

    private function configuredKey(): string
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('Máy chủ chưa bật Sodium; không thể mã hóa hoặc giải mã bí mật.');
        }
        $encoded = getenv('AI_CUSTOMER_SERVICE_ENCRYPTION_KEY');
        $key = is_string($encoded) ? base64_decode($encoded, true) : false;
        if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Chưa cấu hình AI_CUSTOMER_SERVICE_ENCRYPTION_KEY dạng base64 32 byte.');
        }
        return $key;
    }
}
