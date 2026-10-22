<?php

namespace App\Console\Commands;

use App\Support\Backups\BackupSigner;
use Illuminate\Console\Command;
use RuntimeException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class BackupKeygen extends Command
{
    protected $signature = 'backup:keygen';

    protected $description = 'Create the Ed25519 key pair that signs backups. Never overwrites an existing key.';

    public function handle(): int
    {
        $private = (string) config('backups.signing_key');
        $public = (string) config('backups.verify_key');

        try {
            BackupSigner::generate($private, $public);
        } catch (RuntimeException $e) {
            error($e->getMessage());

            return self::FAILURE;
        }

        info("Signing key: $private");
        info("Verification key: $public (sha256 ".BackupSigner::fingerprint($public).')');
        $this->line('Keep an offline copy of the verification key next to your age identity: a fresh machine needs it to trust old backups.');

        return self::SUCCESS;
    }
}
