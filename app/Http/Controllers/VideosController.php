<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Video;
use App\Models\VideoAlbum;
use App\Support\VideoProbe;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class VideosController extends Controller
{
    private const DISK = 'ssd_photos';

    private const FOLDER = 'videos';

    private const POSTERS = 'video-posters';

    private const EXTENSIONS = ['mp4', 'mkv', 'webm', 'mov', 'm4v', 'avi'];

    private function getOrCreateFavoriteAlbum()
    {
        return VideoAlbum::firstOrCreate(
            ['user_id' => Auth::user()->id, 'is_system' => true],
            ['name' => 'Favorite']
        );
    }


    private function generateSlug(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'video';
        }

        return $base . '-' . Str::lower(Str::random(5));
    }

    private function posterPath(Video $video): string
    {
        return self::POSTERS . '/' . $video->id . '.jpg';
    }

    private function relativePath(Video $video): string
    {
        return self::FOLDER . '/' . $video->filename;
    }

    private function fullPath(Video $video): string
    {
        return Storage::disk(self::DISK)->path($this->relativePath($video));
    }

    private function forget(Video $video): void
    {
        Storage::disk(self::DISK)->delete($this->posterPath($video));
    }

    private function folderLabel(): string
    {
        return 'E:\\photos\\Videos  (in the container: /photos-ssd/' . self::FOLDER . ')';
    }

    private function filesOnDrive(): ?array
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->directoryExists(self::FOLDER)) {
            return null;
        }

        $names = [];

        foreach ($disk->files(self::FOLDER) as $path) {
            $names[mb_strtolower(basename($path))] = basename($path);
        }

        return $names;
    }

    private function unregisteredFiles(?array $onDrive): array
    {
        if ($onDrive === null) {
            return [];
        }

        $taken = Video::where('user_id', Auth::user()->id)
            ->pluck('filename')
            ->map(fn ($name) => mb_strtolower($name))
            ->flip();

        $free = [];

        foreach ($onDrive as $lower => $name) {
            if ($taken->has($lower)) {
                continue;
            }

            $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($extension, self::EXTENSIONS, true)) {
                continue;
            }

            $free[] = [
                'name' => $name,
                'size' => $this->humanSize(Storage::disk(self::DISK)->size(self::FOLDER . '/' . $name)),
            ];
        }

        usort($free, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return $free;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576) . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
    }

    public static function durationLabel(?int $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : sprintf('%d:%02d', $minutes, $rest);
    }

    private function albumSummaries($albums)
    {
        $ids = $albums->pluck('id');

        if ($ids->isEmpty()) {
            return [collect(), collect()];
        }

        $counts = DB::table('video_album')
            ->whereIn('video_album_id', $ids)
            ->groupBy('video_album_id')
            ->selectRaw('video_album_id, COUNT(*) as total')
            ->pluck('total', 'video_album_id');

        $ranked = DB::table('video_album')
            ->join('videos', 'videos.id', '=', 'video_album.video_id')
            ->whereIn('video_album.video_album_id', $ids)
            ->where('videos.protection', 'none')
            ->select('video_album.video_album_id', 'videos.id as video_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY video_album.video_album_id ORDER BY videos.created_at DESC, videos.id DESC) AS rn');

        $covers = DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('rn', 1)
            ->pluck('video_id', 'video_album_id');

        return [$counts, $covers];
    }

    private function videoPayload($videos, int $favoriteAlbumId, ?array $onDrive)
    {
        return $videos->map(function (Video $video) use ($favoriteAlbumId, $onDrive) {
            return [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'slug' => $video->slug,
                'protection' => $video->protection,
                'favorite' => $video->albums->contains('id', $favoriteAlbumId),
                'duration' => $video->duration,
                'durationLabel' => self::durationLabel($video->duration),
                'missing' => $this->isMissing($video, $onDrive),
                'filename' => $video->filename,
                'added' => $video->created_at->format('j M Y'),
                'albums' => $video->albums->where('is_system', false)->pluck('name')->values(),
                'urls' => [
                    'stream' => route('video.stream', $video->id),
                    'poster' => route('video.poster', $video->id),
                    'watch' => url('/videos/watch/' . $video->slug),
                ],
            ];
        })->values();
    }

    private function missingIds($videos, ?array $onDrive)
    {
        return $videos->filter(fn (Video $video) => $this->isMissing($video, $onDrive))
            ->pluck('id')
            ->values();
    }

    private function isMissing(Video $video, ?array $onDrive): bool
    {
        if ($onDrive === null) {
            return false;
        }

        return ! array_key_exists(mb_strtolower($video->filename), $onDrive);
    }

    public function showVideosPage(Request $request)
    {
        $userId = Auth::user()->id;

        $videos = Video::with('albums:id,name,is_system')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $favoriteAlbumId = $this->getOrCreateFavoriteAlbum()->id;

        $albums = VideoAlbum::where('user_id', $userId)
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        [$counts, $covers] = $this->albumSummaries($albums);

        $onDrive = $this->filesOnDrive();

        $highlightId = null;

        if ($request->filled('highlight')) {
            $highlightId = Video::where('slug', $request->query('highlight'))
                ->where('user_id', $userId)
                ->value('id');
        }

        return view('videos', [
            'videos' => $videos,
            'albums' => $albums,
            'albumCounts' => $counts,
            'albumCovers' => $covers,
            'favoriteAlbumId' => $favoriteAlbumId,
            'videoData' => $this->videoPayload($videos, $favoriteAlbumId, $onDrive),
            'missingIds' => $this->missingIds($videos, $onDrive),
            'available' => $this->unregisteredFiles($onDrive),
            'driveReady' => $onDrive !== null,
            'folderLabel' => $this->folderLabel(),
            'highlightId' => $highlightId,
        ]);
    }

    public function showAlbum(VideoAlbum $album)
    {
        if ($album->user_id !== Auth::user()->id) {
            abort(403);
        }

        $videos = $album->videos()
            ->with('albums:id,name,is_system')
            ->orderBy('videos.created_at', 'desc')
            ->orderBy('videos.id', 'desc')
            ->get();

        $favoriteAlbumId = $this->getOrCreateFavoriteAlbum()->id;

        $albums = VideoAlbum::where('user_id', Auth::user()->id)
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        $onDrive = $this->filesOnDrive();

        return view('video-album', [
            'album' => $album,
            'videos' => $videos,
            'albums' => $albums,
            'favoriteAlbumId' => $favoriteAlbumId,
            'videoData' => $this->videoPayload($videos, $favoriteAlbumId, $onDrive),
            'missingIds' => $this->missingIds($videos, $onDrive),
            'available' => $this->unregisteredFiles($onDrive),
            'driveReady' => $onDrive !== null,
            'folderLabel' => $this->folderLabel(),
            'highlightId' => null,
        ]);
    }

    public function watchBySlug(string $slug)
    {
        $video = Video::where('slug', $slug)
            ->where('user_id', Auth::user()->id)
            ->firstOrFail();

        return redirect('/videos?highlight=' . $video->slug);
    }

    public function storeVideo(Request $request)
    {
        $userId = Auth::user()->id;

        $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'filename' => 'required|string|max:255',
            'album_id' => 'nullable|integer',
        ]);

        $filename = trim($request->input('filename'));

        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            return back()->withErrors([
                'filename' => 'Give the file name on its own, with no folders in it — everything is read from ' . $this->folderLabel() . '.',
            ])->withInput();
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->directoryExists(self::FOLDER)) {
            return back()->withErrors([
                'filename' => 'The drive is not reachable right now, so nothing could be checked. Expected folder: ' . $this->folderLabel() . '.',
            ])->withInput();
        }

        if (! $disk->exists(self::FOLDER . '/' . $filename)) {
            return back()->withErrors([
                'filename' => '“' . $filename . '” is not in ' . $this->folderLabel() . '. Copy it there first, then register it — the name has to match exactly, extension included.',
            ])->withInput();
        }

        $album = null;

        if ($request->filled('album_id')) {
            $album = VideoAlbum::where('user_id', $userId)
                ->where('id', (int) $request->input('album_id'))
                ->first();

            if (! $album) {
                return back()->withErrors(['album_id' => 'That album does not exist.'])->withInput();
            }
        }

        $fullPath = $disk->path(self::FOLDER . '/' . $filename);
        $duration = VideoProbe::duration($fullPath);

        $video = Video::create([
            'user_id' => $userId,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'filename' => $filename,
            'duration' => $duration,
            'slug' => $this->generateSlug($request->input('title')),
            'protection' => 'none',
            'key_hash' => null,
        ]);

        VideoProbe::poster($fullPath, $disk->path($this->posterPath($video)), $duration);

        if ($album) {
            $video->albums()->attach($album->id);
        }

        return back()->with('success', '“' . $video->title . '” registered.');
    }

    public function destroyVideo(Video $video)
    {
        if ($video->user_id !== Auth::user()->id) {
            abort(403);
        }

        $this->forget($video);
        $video->delete();

        return back()->with('success', 'Video entry removed. The file is still on the drive.');
    }

    public function toggleFavorite(Video $video)
    {
        if ($video->user_id !== Auth::user()->id) {
            abort(403);
        }

        $favoriteAlbum = $this->getOrCreateFavoriteAlbum();

        if ($video->albums()->where('video_albums.id', $favoriteAlbum->id)->exists()) {
            $video->albums()->detach($favoriteAlbum->id);
        } else {
            $video->albums()->attach($favoriteAlbum->id);
        }

        return back();
    }

    public function lockVideo(Request $request, Video $video)
    {
        if ($video->user_id !== Auth::user()->id) {
            abort(403);
        }

        $request->validate(['key' => 'required|string|min:8']);
        $this->forget($video);

        $video->update([
            'protection' => 'gated',
            'key_hash' => Hash::make($request->input('key')),
        ]);

        return back()->with('success', 'Video gated.');
    }

    public function unlockVideo(Request $request, Video $video)
    {
        if ($video->user_id !== Auth::user()->id) {
            abort(403);
        }

        $request->validate(['key' => 'required|string']);

        if (! Hash::check($request->input('key'), $video->key_hash)) {
            return back()->withErrors(['key' => 'Incorrect key.']);
        }

        $this->forget($video);

        $video->update(['protection' => 'none', 'key_hash' => null]);

        return back()->with('success', 'Video unlocked.');
    }

    public function streamVideo(Request $request, Video $video)
    {
        if ($video->user_id !== Auth::user()->id) {
            abort(403);
        }

        if ($video->protection === 'gated') {
            $key = $request->query('key');

            if (! $key || ! Hash::check($key, $video->key_hash)) {
                return response('Key required or incorrect.', 403);
            }
        }

        if (! Storage::disk(self::DISK)->exists($this->relativePath($video))) {
            return response('That file is not on the drive.', 404);
        }

        $headers = $video->protection === 'gated'
            ? ['Cache-Control' => 'no-store, no-cache, must-revalidate']
            : [];

        return response()->file($this->fullPath($video), $headers);
    }

    public function viewPoster(Video $video)
    {
        if ($video->user_id !== Auth::user()->id) {
            abort(403);
        }

        $disk = Storage::disk(self::DISK);
        $posterPath = $this->posterPath($video);

        if (! $disk->exists($posterPath)) {
            if (! $disk->exists($this->relativePath($video))) {
                return response('That file is not on the drive.', 404);
            }

            $fullPath = $this->fullPath($video);
            if ($video->duration === null) {
                $duration = VideoProbe::duration($fullPath);

                if ($duration !== null) {
                    $video->update(['duration' => $duration]);
                }
            }

            if (! VideoProbe::poster($fullPath, $disk->path($posterPath), $video->duration)) {
                return response('No frame could be read from that file.', 404);
            }

            if ($video->protection === 'gated') {
                $manager = ImageManager::usingDriver(Driver::class);
                $image = $manager->decodePath($disk->path($posterPath));
                $width = $image->width();
                $height = $image->height();
                $image->scale(width: 12);
                $image->scale(width: $width, height: $height);
                $disk->put($posterPath, (string) $image->encode());
            }
        }

        return response()->file($disk->path($posterPath));
    }

    public function storeAlbum(Request $request)
    {
        $request->validate(['name' => 'required|string|max:120']);

        $name = trim($request->input('name'));

        if (VideoAlbum::where('user_id', Auth::user()->id)->where('name', $name)->exists()) {
            return back()->withErrors(['name' => 'There is already an album called that.']);
        }

        VideoAlbum::create([
            'user_id' => Auth::user()->id,
            'name' => $name,
            'is_system' => false,
        ]);

        return back()->with('success', 'Album created.');
    }

    public function updateAlbum(Request $request, VideoAlbum $album)
    {
        if ($album->user_id !== Auth::user()->id) {
            abort(403);
        }
        if ($album->is_system) {
            abort(403, 'Cannot rename the Favorite album.');
        }

        $request->validate(['name' => 'required|string|max:120']);

        $name = trim($request->input('name'));

        if (VideoAlbum::where('user_id', Auth::user()->id)->where('name', $name)->where('id', '!=', $album->id)->exists()) {
            return back()->withErrors(['name' => 'There is already an album called that.']);
        }

        $album->update(['name' => $name]);

        return back()->with('success', 'Album renamed.');
    }

    public function destroyAlbum(VideoAlbum $album)
    {
        if ($album->user_id !== Auth::user()->id) {
            abort(403);
        }
        if ($album->is_system) {
            abort(403, 'Cannot delete the Favorite album.');
        }

        $album->delete();

        return back()->with('success', 'Album deleted.');
    }
}
