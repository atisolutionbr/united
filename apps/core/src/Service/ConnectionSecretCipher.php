<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ConnectionSecretCipher
{
    private const PREFIX = 'KFLOW_GCM_V1:';

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $applicationSecret,
    ) {
    }

    public function encrypt(string $plainText): string
    {
        if ('' === $plainText) {
            return '';
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt(
            $plainText,
            'aes-256-gcm',
            hash('sha256', $this->applicationSecret, true),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if (false === $cipherText) {
            throw new \RuntimeException('Não foi possível proteger a credencial da conexão.');
        }

        return self::PREFIX.base64_encode($iv.$tag.$cipherText);
    }

    public function decrypt(string $encryptedText): string
    {
        if ('' === $encryptedText) {
            return '';
        }

        if (!str_starts_with($encryptedText, self::PREFIX)) {
            throw new \RuntimeException('Formato de credencial criptografada inválido.');
        }

        $payload = base64_decode(substr($encryptedText, strlen(self::PREFIX)), true);
        if (false === $payload || strlen($payload) < 29) {
            throw new \RuntimeException('Credencial criptografada inválida.');
        }

        $plainText = openssl_decrypt(
            substr($payload, 28),
            'aes-256-gcm',
            hash('sha256', $this->applicationSecret, true),
            OPENSSL_RAW_DATA,
            substr($payload, 0, 12),
            substr($payload, 12, 16),
        );

        if (false === $plainText) {
            throw new \RuntimeException('Não foi possível abrir a credencial da conexão.');
        }

        return $plainText;
    }
}
