<?php

namespace App\Support\Backups;

use RuntimeException;

// Ed25519 through sodium, keys stored as PKCS#8 / SPKI PEM so `openssl pkeyutl -verify`
// can check a signature without this app. The DER prefixes are fixed for Ed25519.
class BackupSigner
{
    private const PRIVATE_PREFIX = '302e020100300506032b657004220420';

    private const PUBLIC_PREFIX = '302a300506032b6570032100';

    public static function generate(string $privatePath, string $publicPath): void
    {
        if (file_exists($privatePath) || file_exists($publicPath)) {
            throw new RuntimeException("A signing key already exists at $privatePath or $publicPath; refusing to overwrite it.");
        }

        $dir = dirname($privatePath);
        if (! is_dir($dir) && ! mkdir($dir, 0700, true)) {
            throw new RuntimeException("Could not create $dir.");
        }

        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $public = sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($seed));

        $old = umask(0077);
        try {
            self::write($privatePath, self::pem('PRIVATE KEY', hex2bin(self::PRIVATE_PREFIX).$seed));
            self::write($publicPath, self::pem('PUBLIC KEY', hex2bin(self::PUBLIC_PREFIX).$public));
        } finally {
            umask($old);
            sodium_memzero($seed);
        }
        chmod($publicPath, 0644);
    }

    public static function sign(string $message, string $privatePath): string
    {
        $seed = self::unpem($privatePath, 'PRIVATE KEY', self::PRIVATE_PREFIX);
        $secret = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($seed));
        sodium_memzero($seed);

        $signature = sodium_crypto_sign_detached($message, $secret);
        sodium_memzero($secret);

        return $signature;
    }

    public static function verify(string $message, string $signature, string $publicPath): bool
    {
        if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $message, self::publicKey($publicPath));
    }

    public static function publicKey(string $publicPath): string
    {
        return self::unpem($publicPath, 'PUBLIC KEY', self::PUBLIC_PREFIX);
    }

    public static function fingerprint(string $publicPath): string
    {
        return hash('sha256', hex2bin(self::PUBLIC_PREFIX).self::publicKey($publicPath));
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN $label-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END $label-----\n";
    }

    private static function unpem(string $path, string $label, string $prefixHex): string
    {
        if (! is_readable($path)) {
            throw new RuntimeException("The signing key file $path is missing or unreadable (php artisan backup:keygen creates the pair).");
        }

        $pem = file_get_contents($path);
        if (! preg_match("/-----BEGIN $label-----(.+?)-----END $label-----/s", $pem, $m)) {
            throw new RuntimeException("$path is not a PEM \"$label\".");
        }

        $der = base64_decode(preg_replace('/\s+/', '', $m[1]), true);
        $prefix = hex2bin($prefixHex);
        if ($der === false || strlen($der) !== strlen($prefix) + 32 || ! str_starts_with($der, $prefix)) {
            throw new RuntimeException("$path is not an Ed25519 key.");
        }

        return substr($der, strlen($prefix));
    }

    private static function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Could not write $path.");
        }
    }
}
