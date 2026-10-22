<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Support\Backups\BackupFiles;
use App\Support\Backups\BackupSigner;
use App\Support\Backups\BackupVerifier;
use App\Support\Backups\VerificationFailed;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class BackupVerify extends Command
{
    protected $signature = 'backup:verify {id : The backup row id}';

    protected $description = 'Check a backup\'s signature and hash without decrypting it.';

    public function handle(): int
    {
        $backup = Backup::find($this->argument('id'));
        if ($backup === null) {
            error('No backup has that id.');

            return VerificationFailed::MISSING;
        }
        if ($backup->status !== 'succeeded') {
            error("Backup #{$backup->id} is {$backup->status}; there is nothing to verify.");

            return VerificationFailed::MISSING;
        }

        try {
            $manifest = BackupVerifier::verify(BackupFiles::path($backup->filename));
        } catch (VerificationFailed $e) {
            error($e->getMessage());

            return $e->getCode();
        }

        if ($manifest['sha256'] !== $backup->sha256 || $manifest['size'] !== (int) $backup->size) {
            error("The file is signed, but it is not the one row #{$backup->id} recorded (sha256 {$backup->sha256}).");

            return VerificationFailed::CONTENTS;
        }

        info("Backup #{$backup->id} verified: {$backup->filename}");
        $this->line('  signature:  valid (Ed25519 key sha256 '.BackupSigner::fingerprint((string) config('backups.verify_key')).')');
        $this->line("  file:       {$manifest['size']} bytes, sha256 {$manifest['sha256']}");
        $this->line("  created:    {$manifest['created_at']} ({$manifest['trigger']})");
        $this->line("  database:   {$manifest['database']}, migration {$manifest['migration']}");
        $this->line('  encrypted to: '.implode(', ', $manifest['recipients']));

        return self::SUCCESS;
    }
}
