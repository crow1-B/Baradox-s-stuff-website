<?php

namespace Tests\Feature\Backups;

use App\Models\Backup;
use App\Support\Backups\BackupSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BackupRunTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/backup-run-'.bin2hex(random_bytes(4));
        mkdir("$this->dir/out", 0700, true);
        (new Process(['age-keygen', '-o', "$this->dir/identity.txt"]))->mustRun();
        $recipient = (new Process(['age-keygen', '-y', "$this->dir/identity.txt"]))->mustRun()->getOutput();
        BackupSigner::generate("$this->dir/k.pem", "$this->dir/k.pub.pem");

        config([
            'backups.path' => "$this->dir/out",
            'backups.recipients' => [trim($recipient)],
            'backups.signing_key' => "$this->dir/k.pem",
            'backups.verify_key' => "$this->dir/k.pub.pem",
            'logging.channels.backups.path' => "$this->dir/backups.log",
        ]);
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->dir]))->run();
        parent::tearDown();
    }

    private function decrypt(Backup $backup): string
    {
        return (new Process(['bash', '-o', 'pipefail', '-c', 'age -d -i "$1" "$2" | zstd -dc', '_', "$this->dir/identity.txt", "$this->dir/out/$backup->filename"]))
            ->mustRun()->getOutput();
    }

    public function test_a_manual_backup_is_encrypted_signed_and_verifiable(): void
    {
        $this->artisan('backup:run')->assertExitCode(0);

        $backup = Backup::sole();
        $this->assertSame('succeeded', $backup->status);
        $this->assertSame('manual', $backup->trigger);
        $this->assertSame(hash_file('sha256', "$this->dir/out/$backup->filename"), $backup->sha256);
        $this->assertSame([], glob("$this->dir/out/*.partial"));

        $sql = $this->decrypt($backup);
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringNotContainsString('CREATE TABLE `backups`', $sql);
        $this->assertStringNotContainsString('CREATE TABLE `backup_restores`', $sql);

        $this->artisan('backup:verify', ['id' => $backup->id])->assertExitCode(0);
        $this->assertNull(Backup::attention());
    }

    public function test_verify_refuses_a_changed_file_or_manifest(): void
    {
        $this->artisan('backup:run')->assertExitCode(0);
        $backup = Backup::sole();
        $path = "$this->dir/out/$backup->filename";

        $data = file_get_contents($path);
        file_put_contents($path, substr($data, 0, -1).chr(ord($data[-1]) ^ 1));
        $this->artisan('backup:verify', ['id' => $backup->id])->assertExitCode(7);
        file_put_contents($path, $data);

        $manifest = file_get_contents("$path.manifest");
        file_put_contents("$path.manifest", str_replace('"manual"', '"scheduled"', $manifest));
        $this->artisan('backup:verify', ['id' => $backup->id])->assertExitCode(6);
    }

    public function test_without_a_recipient_the_backup_fails_visibly(): void
    {
        config(['backups.recipients' => []]);

        $this->artisan('backup:run')->assertExitCode(1);

        $backup = Backup::sole();
        $this->assertSame('failed', $backup->status);
        $this->assertStringContainsString('No age recipient', $backup->error);
        $this->assertStringStartsWith('The last backup failed', Backup::attention());
        $this->assertSame([], glob("$this->dir/out/*"));
    }

    public function test_a_failing_dump_is_not_masked_by_the_pipeline(): void
    {
        config(['backups.db_password' => 'wrong']);

        $this->artisan('backup:run')->assertExitCode(1);

        $this->assertStringContainsString('mysqldump exited', Backup::sole()->error);
        $this->assertSame([], glob("$this->dir/out/*"));
    }

    public function test_if_stale_skips_while_a_recent_backup_exists(): void
    {
        $this->artisan('backup:run --scheduled --if-stale')->assertExitCode(0);
        $this->artisan('backup:run --scheduled --if-stale')->assertExitCode(0);

        $this->assertSame(1, Backup::count());
        $this->assertSame('scheduled', Backup::sole()->trigger);
    }
}
