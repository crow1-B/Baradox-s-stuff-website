<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\ExtraNote;
use App\Models\FutureUpdate;
use App\Models\Photo;
use App\Models\Project;
use App\Models\Track;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoAlbum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

// The server refuses every write from the preview account (PreviewReadOnlyTest); this is the
// other half: the pages don't offer one. Each page is rendered with records the account owns,
// so a control that only appears next to a record would show up.
class PreviewWriteControlsTest extends TestCase
{
    use RefreshDatabase;

    // Hooks that only exist on a write control, per page.
    private const WRITE_HOOKS = [
        'music' => ['data-mu-open=', 'id="mu-add-dialog"', '/favorite"'],
        'photos' => ['data-ph-open-dialog=', 'data-ph-action=', 'data-ph-album-action=', 'data-ph-pick', 'data-ph-select-toggle', 'id="ph-upload-dialog"'],
        'album' => ['data-ph-open-dialog=', 'data-ph-action=', 'data-ph-pick', 'id="ph-upload-dialog"'],
        'videos' => ['data-vd-open-dialog=', 'data-vd-action=', 'data-vd-album-action=', 'id="vd-register-dialog"'],
        'video-album' => ['data-vd-open-dialog=', 'data-vd-action=', 'id="vd-register-dialog"'],
        'extra-notes' => ['data-xn-composer', 'data-xn-select-toggle', 'data-xn-pick', 'data-xn-act="edit"', 'data-xn-act="pin"', 'data-xn-act="delete"', 'id="xn-edit-dialog"'],
        'projects' => ['data-pj-open=', 'id="pj-project-dialog"'],
        'future-updates' => ['data-fu-add-form', 'data-fu-open=', 'data-fu-done-check', 'id="fu-edit-dialog"'],
    ];

    private array $urls = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('ssd_photos');
        Storage::fake('public');
    }

    private function userWithRecords(bool $preview): User
    {
        $user = User::create([
            'username' => 'wc_' . Str::lower(Str::random(10)),
            'password' => 'irrelevant',
            'is_preview' => $preview,
        ]);
        $id = $user->id;

        Track::create(['user_id' => $id, 'title' => 'Song', 'path' => 'tracks/x.mp3', 'favorite' => true]);
        $photo = Photo::create(['user_id' => $id, 'path' => 'photos/x.jpg', 'protection' => 'none']);
        $album = Album::create(['user_id' => $id, 'name' => 'Trip', 'is_system' => false]);
        $album->photos()->attach($photo->id);
        $video = Video::create(['user_id' => $id, 'title' => 'Clip', 'filename' => 'x.mp4', 'slug' => 'clip-' . Str::lower(Str::random(5)), 'protection' => 'none']);
        $videoAlbum = VideoAlbum::create(['user_id' => $id, 'name' => 'Harbour', 'is_system' => false]);
        $videoAlbum->videos()->attach($video->id);
        ExtraNote::create(['user_id' => $id, 'content' => 'A note', 'is_pinned' => true]);
        $project = Project::create(['user_id' => $id, 'title' => 'P', 'description' => 'D', 'status' => 'future', 'start_date' => '2026-01-01']);
        FutureUpdate::create(['user_id' => $id, 'content' => 'Next', 'project_id' => $project->id]);
        FutureUpdate::create(['user_id' => $id, 'content' => 'Shipped', 'is_done' => true]);

        $this->urls = [
            'music' => route('music'),
            'photos' => route('photos'),
            'album' => route('albums.show', $album),
            'videos' => route('videos'),
            'video-album' => route('video-albums.show', $videoAlbum),
            'extra-notes' => route('extra-notes'),
            'projects' => route('projects', ['tab' => 'future']),
            'future-updates' => route('future-updates', ['done' => 1]),
        ];

        return $user;
    }

    public function test_the_guest_is_offered_no_write_control(): void
    {
        $guest = $this->userWithRecords(preview: true);

        foreach ($this->urls as $page => $url) {
            $html = $this->actingAs($guest)->get($url)->assertOk()->getContent();

            foreach (self::WRITE_HOOKS[$page] as $hook) {
                $this->assertStringNotContainsString($hook, $html, "$page offers the guest a write control ($hook)");
            }

            // The one form the guest may submit is signing out.
            preg_match_all('/<form\b[^>]*\bmethod="POST"[^>]*>/i', $html, $forms);
            $others = array_filter($forms[0], fn ($form) => !str_contains($form, route('logout')));
            $this->assertSame([], array_values($others), "$page renders a POST form for the guest");
        }
    }

    public function test_the_guest_gets_no_write_control_in_older_messages_either(): void
    {
        $guest = $this->userWithRecords(preview: true);
        $oldest = ExtraNote::create(['user_id' => $guest->id, 'content' => 'Newest']);

        $html = $this->actingAs($guest)->getJson(route('extra-notes.messages', ['before' => $oldest->id]))->assertOk()->getContent();

        $this->assertStringContainsString('A note', $html);
        $this->assertStringNotContainsString('data-xn-act=\"edit\"', $html);
        $this->assertStringNotContainsString('data-xn-pick', $html);
    }

    // Proves the hooks above are the real ones: without this, a renamed hook would let the
    // guest test pass while the controls were still there.
    public function test_an_ordinary_account_gets_every_write_control(): void
    {
        $owner = $this->userWithRecords(preview: false);

        foreach ($this->urls as $page => $url) {
            $html = $this->actingAs($owner)->get($url)->assertOk()->getContent();

            foreach (self::WRITE_HOOKS[$page] as $hook) {
                $this->assertStringContainsString($hook, $html, "$page is missing $hook for its owner");
            }
        }
    }
}
