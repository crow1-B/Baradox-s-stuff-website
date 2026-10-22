<?php

namespace App\Support\Backups;

// Checks a backup without decrypting it: the manifest's signature against the configured
// public key first, then that the encrypted file is exactly the one the manifest describes.
class BackupVerifier
{
    /** @return array<string, mixed> the verified manifest */
    public static function verify(string $dataPath): array
    {
        $manifestPath = BackupFiles::manifestPath($dataPath);
        $signaturePath = BackupFiles::signaturePath($dataPath);

        foreach ([$dataPath, $manifestPath, $signaturePath] as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                throw new VerificationFailed("$path is missing or unreadable.", VerificationFailed::MISSING);
            }
        }

        $manifestBytes = file_get_contents($manifestPath);
        $verifyKey = (string) config('backups.verify_key');

        try {
            $valid = BackupSigner::verify($manifestBytes, file_get_contents($signaturePath), $verifyKey);
        } catch (\RuntimeException $e) {
            throw new VerificationFailed($e->getMessage(), VerificationFailed::CONFIG);
        }
        if (! $valid) {
            throw new VerificationFailed('The signature does not match the verification key (sha256 '.BackupSigner::fingerprint($verifyKey).'): a forged or modified manifest, or the wrong key.', VerificationFailed::SIGNATURE);
        }

        $manifest = json_decode($manifestBytes, true);
        if (! is_array($manifest) || ($manifest['format'] ?? null) !== 1) {
            throw new VerificationFailed('The manifest is signed but not in a format this code reads.', VerificationFailed::SIGNATURE);
        }
        if (($manifest['file'] ?? null) !== basename($dataPath)) {
            throw new VerificationFailed("The manifest describes {$manifest['file']}, not ".basename($dataPath).'.', VerificationFailed::CONTENTS);
        }

        $size = filesize($dataPath);
        if ($size !== $manifest['size']) {
            throw new VerificationFailed("The file is $size bytes; the manifest says {$manifest['size']}.", VerificationFailed::CONTENTS);
        }
        $sha256 = hash_file('sha256', $dataPath);
        if (! hash_equals((string) ($manifest['sha256'] ?? ''), $sha256)) {
            throw new VerificationFailed("The file's sha256 is $sha256; the manifest says {$manifest['sha256']}.", VerificationFailed::CONTENTS);
        }

        return $manifest;
    }
}
