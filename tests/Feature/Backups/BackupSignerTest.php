<?php

namespace Tests\Feature\Backups;

use App\Support\Backups\BackupSigner;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BackupSignerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/backup-signer-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_a_signature_verifies_and_a_changed_message_does_not(): void
    {
        BackupSigner::generate("$this->dir/k.pem", "$this->dir/k.pub.pem");
        $signature = BackupSigner::sign('manifest', "$this->dir/k.pem");

        $this->assertTrue(BackupSigner::verify('manifest', $signature, "$this->dir/k.pub.pem"));
        $this->assertFalse(BackupSigner::verify('manifesT', $signature, "$this->dir/k.pub.pem"));
        $this->assertSame('0600', substr(sprintf('%o', fileperms("$this->dir/k.pem")), -4));
    }

    public function test_another_key_does_not_verify(): void
    {
        BackupSigner::generate("$this->dir/a.pem", "$this->dir/a.pub.pem");
        BackupSigner::generate("$this->dir/b.pem", "$this->dir/b.pub.pem");

        $this->assertFalse(BackupSigner::verify('m', BackupSigner::sign('m', "$this->dir/a.pem"), "$this->dir/b.pub.pem"));
    }

    public function test_it_never_overwrites_a_key(): void
    {
        BackupSigner::generate("$this->dir/k.pem", "$this->dir/k.pub.pem");
        $before = file_get_contents("$this->dir/k.pem");

        $this->expectException(RuntimeException::class);
        try {
            BackupSigner::generate("$this->dir/k.pem", "$this->dir/k.pub.pem");
        } finally {
            $this->assertSame($before, file_get_contents("$this->dir/k.pem"));
        }
    }

    public function test_openssl_reads_the_keys_and_verifies_the_signature(): void
    {
        BackupSigner::generate("$this->dir/k.pem", "$this->dir/k.pub.pem");
        file_put_contents("$this->dir/msg", "manifest bytes\n");
        file_put_contents("$this->dir/sig", BackupSigner::sign("manifest bytes\n", "$this->dir/k.pem"));

        $derived = new Process(['openssl', 'pkey', '-in', "$this->dir/k.pem", '-pubout']);
        $derived->mustRun();
        $this->assertSame(file_get_contents("$this->dir/k.pub.pem"), $derived->getOutput());

        $verify = new Process(['openssl', 'pkeyutl', '-verify', '-pubin', '-inkey', "$this->dir/k.pub.pem", '-rawin', '-in', "$this->dir/msg", '-sigfile', "$this->dir/sig"]);
        $verify->run();
        $this->assertSame(0, $verify->getExitCode(), $verify->getErrorOutput());
    }
}
