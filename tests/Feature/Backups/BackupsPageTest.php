<?php

namespace Tests\Feature\Backups;

use App\Http\Controllers\BackupsController;
use App\Models\Backup;
use App\Models\User;
use App\Support\Backups\BackupFiles;
use App\Support\Backups\BackupSigner;
use App\Support\Backups\WebRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BackupsPageTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private User $owner;

    private User $guest;

    private User $other;

    /** @var list<string> tickets the fake launcher was handed */
    private array $launched = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dir = sys_get_temp_dir().'/backups-page-'.bin2hex(random_bytes(4));
        mkdir("$this->dir/out", 0700, true);
        mkdir("$this->dir/media/photos", 0700, true);
        file_put_contents("$this->dir/media/.baradox-stuff-media", '');
        file_put_contents("$this->dir/media/photos/a.jpg", str_repeat('x', 1500));
        (new Process(['age-keygen', '-o', "$this->dir/identity.txt"]))->mustRun();
        $recipient = (new Process(['age-keygen', '-y', "$this->dir/identity.txt"]))->mustRun()->getOutput();
        BackupSigner::generate("$this->dir/k.pem", "$this->dir/k.pub.pem");

        $this->owner = User::create(['username' => 'bk_owner', 'password' => 'x', 'is_preview' => false]);
        $this->guest = User::create(['username' => 'bk_guest', 'password' => 'x', 'is_preview' => true]);
        $this->other = User::create(['username' => 'bk_other', 'password' => 'x', 'is_preview' => false]);

        config([
            'backups.owner' => 'bk_owner',
            'backups.path' => "$this->dir/out",
            'backups.recipients' => [trim($recipient)],
            'backups.signing_key' => "$this->dir/k.pem",
            'backups.verify_key' => "$this->dir/k.pub.pem",
            'filesystems.disks.ssd_photos.root' => "$this->dir/media",
            'logging.channels.backups.path' => "$this->dir/backups.log",
        ]);

        $test = $this;
        $this->app->instance(WebRun::class, new class($test) extends WebRun
        {
            public function __construct(private BackupsPageTest $test) {}

            public function launch(string $ticket): void
            {
                $this->test->recordLaunch($ticket);
            }
        });
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->dir]))->run();
        parent::tearDown();
    }

    public function recordLaunch(string $ticket): void
    {
        $this->launched[] = $ticket;
    }

    private function realBackup(string $trigger = 'manual'): Backup
    {
        $this->artisan('backup:run', $trigger === 'scheduled' ? ['--scheduled' => true] : [])->assertExitCode(0);

        return Backup::latest('id')->first();
    }

    private function row(array $attributes): Backup
    {
        static $n = 0;
        $n++;
        $backup = Backup::create($attributes + [
            'filename' => sprintf('baradox-db-20261020T0000%02dZ-%s-%08x.sql.zst.age', $n, $attributes['trigger'] ?? 'manual', $n),
            'trigger' => 'manual',
            'status' => 'succeeded',
            'size' => 10,
            'sha256' => str_repeat('a', 64),
        ]);
        if (isset($attributes['created_at'])) {
            $backup->forceFill(['created_at' => $attributes['created_at']])->save();
        }

        return $backup;
    }

    private function log(): string
    {
        return is_file("$this->dir/backups.log") ? file_get_contents("$this->dir/backups.log") : '';
    }

    /** Every backups route, with a real backup id in it. */
    private function routes(Backup $backup): array
    {
        $calls = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'backups')) {
                continue;
            }
            $uri = '/'.str_replace(['{backup}', '{part}'], [$backup->id, 'age'], $route->uri());
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $calls[] = [$method, $uri];
            }
        }

        return $calls;
    }

    // ------------------------------------------------------------------ who may see it

    public function test_guest_gets_a_404_on_every_backups_route(): void
    {
        $backup = $this->realBackup();
        $calls = $this->routes($backup);
        $this->assertCount(6, $calls);

        foreach ($calls as [$method, $uri]) {
            $this->actingAs($this->guest)->call($method, $uri, ['confirm' => $backup->code()])
                ->assertNotFound();
        }

        $this->assertSame(1, Backup::count());
        $this->assertFileExists("$this->dir/out/$backup->filename");
        $this->assertSame([], $this->launched);
    }

    public function test_any_other_account_gets_the_same_404(): void
    {
        $backup = $this->realBackup();

        foreach ($this->routes($backup) as [$method, $uri]) {
            $this->actingAs($this->other)->call($method, $uri, ['confirm' => $backup->code()])
                ->assertNotFound();
        }
        $this->assertSame(1, Backup::count());
        $this->assertSame([], $this->launched);

        // With no owner configured nobody sees it, not even the account that was the owner.
        config(['backups.owner' => null]);
        $this->actingAs($this->owner)->get(route('backups'))->assertNotFound();
    }

    public function test_only_the_owner_finds_it_in_the_sidebar_and_the_palette(): void
    {
        $url = route('backups');

        $this->actingAs($this->owner)->get(route('homepage'))->assertOk()->assertSee('href="'.$url.'"', false);
        $this->assertContains($url, collect($this->actingAs($this->owner)->getJson(route('palette'))->json('pages'))->pluck('url'));

        foreach ([$this->guest, $this->other] as $user) {
            $this->actingAs($user)->get(route('homepage'))->assertOk()->assertDontSee('href="'.$url.'"', false);
            $this->assertNotContains($url, collect($this->actingAs($user)->getJson(route('palette'))->json('pages'))->pluck('url'));
        }
    }

    // ------------------------------------------------------------------ the page

    public function test_the_owner_sees_every_backup_with_its_details(): void
    {
        $ok = $this->realBackup();
        $this->row(['status' => 'failed', 'size' => null, 'sha256' => null, 'error' => 'mysqldump exited 2: access denied']);
        $this->row(['trigger' => 'prerestore']);

        $this->actingAs($this->owner)->get(route('backups'))->assertOk()
            ->assertSee($ok->created_at->format('j M Y, H:i'))
            ->assertSee('mysqldump exited 2: access denied')
            ->assertSee('prerestore')
            ->assertSee('Not checked')
            ->assertSee('backup:verify '.$ok->id)
            ->assertSee('--identity=storage/app/private/restore-identity.txt', false)
            ->assertSee('cp /mnt/e/baradox-stuff.age storage/app/private/restore-identity.txt')
            ->assertSee('scripts/restore-check.sh')
            ->assertSee('not in these backups')
            ->assertSee('1 file, 1.5 KB')
            ->assertDontSee('Restore now');

        $this->assertSame(1, \App\Models\Visit::where('user_id', $this->owner->id)->where('page_name', 'Backups Page.')->count());
    }

    public function test_verify_runs_on_demand_and_its_result_is_cached(): void
    {
        $backup = $this->realBackup();

        $this->actingAs($this->owner)->postJson(route('backups.verify', $backup))
            ->assertOk()->assertJson(['ok' => true, 'label' => 'Signature valid']);
        $this->actingAs($this->owner)->get(route('backups'))->assertSee('Signature valid');

        // The cached answer stands until it is checked again; checking again sees the change.
        $path = "$this->dir/out/$backup->filename";
        file_put_contents($path, file_get_contents($path).'x');
        $this->actingAs($this->owner)->get(route('backups'))->assertSee('Signature valid');
        $this->actingAs($this->owner)->postJson(route('backups.verify', $backup))
            ->assertOk()->assertJson(['ok' => false, 'code' => 7, 'label' => 'File changed']);
        $this->actingAs($this->owner)->get(route('backups'))->assertSee('File changed');
    }

    // ------------------------------------------------------------------ download

    public function test_a_download_is_the_encrypted_file_unchanged(): void
    {
        $backup = $this->realBackup();
        $path = "$this->dir/out/$backup->filename";

        foreach (['age' => '', 'manifest' => '.manifest', 'sig' => '.sig'] as $part => $suffix) {
            $response = $this->actingAs($this->owner)->get(route('backups.download', [$backup, $part]))->assertOk()
                ->assertHeader('Content-Type', 'application/octet-stream')
                ->assertDownload($backup->filename.$suffix);
            ob_start();
            $response->baseResponse->sendContent();
            $bytes = ob_get_clean();

            $this->assertSame(file_get_contents($path.$suffix), $bytes, "$part is not the file on disk");
            $this->assertStringContainsString("bk_owner downloaded {$backup->filename}{$suffix}", $this->log());
        }

        $this->assertSame($backup->sha256, hash_file('sha256', $path));
    }

    public function test_a_failed_backup_has_nothing_to_download(): void
    {
        $failed = $this->row(['status' => 'failed', 'error' => 'boom']);

        $this->actingAs($this->owner)->get(route('backups.download', [$failed, 'age']))->assertNotFound();
    }

    // ------------------------------------------------------------------ delete

    public function test_the_newest_success_and_prerestore_backups_are_never_deleted(): void
    {
        $older = $this->realBackup();
        $prerestore = $this->row(['trigger' => 'prerestore', 'created_at' => now()->subDay()]);
        $newest = $this->realBackup();

        $this->actingAs($this->owner)->delete(route('backups.destroy', $newest), ['confirm' => $newest->code()])
            ->assertSessionHasErrors('delete');
        $this->actingAs($this->owner)->delete(route('backups.destroy', $prerestore), ['confirm' => $prerestore->code()])
            ->assertSessionHasErrors('delete');

        $this->assertSame(3, Backup::count());
        $this->assertFileExists("$this->dir/out/$newest->filename");

        // An older one goes, with its three files, and the log says so.
        $this->actingAs($this->owner)->delete(route('backups.destroy', $older), ['confirm' => $older->code()])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertNull(Backup::find($older->id));
        $this->assertSame([], glob("$this->dir/out/{$older->filename}*"));
        $this->assertStringContainsString("bk_owner deleted {$older->filename}", $this->log());

        // The page offers no delete for the two it refuses.
        $this->actingAs($this->owner)->get(route('backups'))
            ->assertDontSee('data-bk-delete-url="'.route('backups.destroy', $newest).'"', false)
            ->assertDontSee('data-bk-delete-url="'.route('backups.destroy', $prerestore).'"', false)
            ->assertSee('The newest successful backup is always kept.')
            ->assertSee('Pre-restore backups are always kept.');
    }

    public function test_delete_needs_the_typed_code(): void
    {
        $older = $this->realBackup();
        $this->realBackup();

        $this->actingAs($this->owner)->delete(route('backups.destroy', $older), ['confirm' => 'yes'])
            ->assertSessionHasErrors('delete');
        $this->actingAs($this->owner)->delete(route('backups.destroy', $older))
            ->assertSessionHasErrors('delete');

        $this->assertNotNull(Backup::find($older->id));
        $this->assertFileExists("$this->dir/out/$older->filename");
    }

    // ------------------------------------------------------------------ back up now

    public function test_back_up_now_is_refused_while_the_lock_is_held(): void
    {
        $lock = BackupFiles::lock();
        try {
            $this->actingAs($this->owner)->post(route('backups.run'))->assertSessionHasErrors('run');
        } finally {
            BackupFiles::unlock($lock);
        }
        $this->assertSame([], $this->launched);

        // The process a click starts takes the same lock; one started while it is held
        // records nothing and reports "busy" back to the page.
        $lock = BackupFiles::lock();
        try {
            $ticket = WebRun::newTicket();
            WebRun::claim($ticket);
            $this->artisan('backup:run', ['--ticket' => $ticket])->assertExitCode(1);
            $this->assertSame('busy', WebRun::state($ticket)['state']);
        } finally {
            BackupFiles::unlock($lock);
        }
        $this->assertSame(0, Backup::count());
    }

    public function test_a_second_click_while_a_run_from_the_page_is_going_is_refused(): void
    {
        $this->actingAs($this->owner)->post(route('backups.run'))->assertRedirect(route('backups'));
        $this->assertCount(1, $this->launched);

        RateLimiter::clear('backups-run:'.$this->owner->id);
        $this->actingAs($this->owner)->post(route('backups.run'))->assertSessionHasErrors('run');
        $this->assertCount(1, $this->launched);
    }

    public function test_repeated_clicks_are_throttled(): void
    {
        $this->actingAs($this->owner)->post(route('backups.run'));
        WebRun::release($this->launched[0]);

        $this->actingAs($this->owner)->post(route('backups.run'))->assertSessionHasErrors('run');
        $this->assertCount(1, $this->launched);

        $this->travel(BackupsController::RUN_DECAY_SECONDS + 1)->seconds();
        $this->actingAs($this->owner)->post(route('backups.run'))->assertRedirect(route('backups'));
        $this->assertCount(2, $this->launched);
    }

    public function test_a_run_from_the_page_is_polled_and_reported(): void
    {
        $this->actingAs($this->owner)->post(route('backups.run'))->assertRedirect(route('backups'));
        [$ticket] = $this->launched;
        $this->assertStringContainsString('web: backup requested by bk_owner', $this->log());

        $session = ['backups.ticket' => $ticket];
        $this->actingAs($this->owner)->withSession($session)->get(route('backups'))->assertSee('Backing up.');
        $this->actingAs($this->owner)->withSession($session)->getJson(route('backups.status'))->assertJson(['state' => 'starting']);

        // What the detached process does.
        $this->artisan('backup:run', ['--ticket' => $ticket])->assertExitCode(0);
        $backup = Backup::sole();
        $this->assertSame('manual', $backup->trigger);

        $this->actingAs($this->owner)->withSession($session)->getJson(route('backups.status'))
            ->assertJson(['state' => 'finished'])
            ->assertSessionHas('success', fn ($message) => str_starts_with($message, "Backup #{$backup->id} finished"));

        // The start lock went with the process, so the next click (after the throttle) starts.
        $this->travel(BackupsController::RUN_DECAY_SECONDS + 1)->seconds();
        $this->actingAs($this->owner)->post(route('backups.run'))->assertRedirect(route('backups'));
        $this->assertCount(2, $this->launched);
    }

    public function test_a_run_that_never_starts_is_reported_lost(): void
    {
        $this->actingAs($this->owner)->post(route('backups.run'));
        [$ticket] = $this->launched;

        $this->travel(WebRun::LOST_AFTER_SECONDS + 1)->seconds();
        $this->actingAs($this->owner)->withSession(['backups.ticket' => $ticket])->get(route('backups'))
            ->assertSee('never reported back');
    }

    // ------------------------------------------------------------------ the banner

    public function test_the_banner_follows_the_attention_conditions(): void
    {
        Carbon::setTestNow('2026-10-24 12:00:00');
        $page = fn (User $user) => $this->actingAs($user)->get(route('homepage'))->assertOk();

        // Nothing has ever succeeded.
        $page($this->owner)->assertSee('data-backup-alert=', false)->assertSee('No backup has succeeded yet.');

        // A success 47 hours ago is quiet, though --if-stale already counts it as stale.
        $success = $this->row(['created_at' => now()->subHours(47)]);
        $page($this->owner)->assertDontSee('data-backup-alert=', false);
        $this->assertTrue(Backup::isStale());

        // Past 48 hours it speaks.
        $success->forceFill(['created_at' => now()->subHours(49)])->save();
        $page($this->owner)->assertSee('data-backup-alert=', false)->assertSee('The last successful backup is from');

        // A fresh success clears it; a failure after it brings it back with the error.
        $this->row(['created_at' => now()->subHour()]);
        $page($this->owner)->assertDontSee('data-backup-alert=', false);
        $this->row(['status' => 'failed', 'error' => 'disk full', 'created_at' => now()->subMinutes(5)]);
        $page($this->owner)->assertSee('data-backup-alert=', false)->assertSee('The last backup failed: disk full');

        // Owner only, and not on the Backups page, which has its own summary.
        $page($this->guest)->assertDontSee('data-backup-alert=', false);
        $page($this->other)->assertDontSee('data-backup-alert=', false);
        $this->actingAs($this->owner)->get(route('backups'))->assertDontSee('data-backup-alert=', false)->assertSee('Needs attention');
    }

    public function test_the_banner_key_changes_with_the_reason(): void
    {
        $key = fn () => Str::between($this->actingAs($this->owner)->get(route('homepage'))->getContent(), 'data-backup-alert="', '"');

        $none = $key();
        $this->row(['status' => 'failed', 'error' => 'disk full']);

        $this->assertNotSame($none, $key());
    }
}
