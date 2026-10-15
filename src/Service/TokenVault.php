<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TokenVault
{
    public function __construct(#[Autowire('%env(YOUTUBE_TOKEN_KEY)%')] private string $key)
    {
    }

    public function isReady(): bool
    {
        $key = base64_decode($this->key, true);

        return false !== $key && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen($key);
    }

    private function key(): string
    {
        $key = base64_decode($this->key, true);
        if (false === $key || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($key)) {
            throw new PublicationException('La connexion YouTube est indisponible.');
        }

        return $key;
    }

    public function encrypt(#[\SensitiveParameter] string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key()));
    }

    public function decrypt(#[\SensitiveParameter] string $encrypted): string
    {
        $bytes = base64_decode($encrypted, true);
        if (false === $bytes || strlen($bytes) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new PublicationException('Reconnecte ton compte YouTube.');
        }
        $plain = sodium_crypto_secretbox_open(substr($bytes, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($bytes, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key());
        if (false === $plain) {
            throw new PublicationException('Reconnecte ton compte YouTube.');
        }

        return $plain;
    }
}
