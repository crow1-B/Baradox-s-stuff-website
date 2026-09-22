<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Photo;
use App\Models\Album;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class PhotosController extends Controller
{
    private const DISK = 'ssd_photos';

    private const MAX_BULK = 300;

    private const FILE_RULES = ['file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'];

    private function getOrCreateFavoriteAlbum()
    {
        return Album::firstOrCreate(
            ['user_id' => Auth::user()->id, 'is_system' => true],
            ['name' => 'Favorite']
        );
    }

    private function albumSummaries($albums)
    {
        $ids = $albums->pluck('id');

        if ($ids->isEmpty()) {
            return [collect(), collect()];
        }

        $counts = DB::table('album_photo')
            ->whereIn('album_id', $ids)
            ->groupBy('album_id')
            ->selectRaw('album_id, COUNT(*) as total')
            ->pluck('total', 'album_id');

        $ranked = DB::table('album_photo')
            ->join('photos', 'photos.id', '=', 'album_photo.photo_id')
            ->whereIn('album_photo.album_id', $ids)
            ->where('photos.protection', 'none')
            ->select('album_photo.album_id', 'photos.id as photo_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY album_photo.album_id ORDER BY photos.created_at DESC, photos.id DESC) AS rn');

        $covers = DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('rn', 1)
            ->pluck('photo_id', 'album_id');

        return [$counts, $covers];
    }

    private function photoPayload($photos, int $favoriteAlbumId)
    {
        return $photos->map(fn (Photo $photo) => [
            'id' => $photo->id,
            'protection' => $photo->protection,
            'favorite' => $photo->albums->contains('id', $favoriteAlbumId),
            'added' => $photo->created_at->format('j M Y'),
            'albums' => $photo->albums->where('is_system', false)->pluck('name')->values(),
            'urls' => [
                'view' => route('photo.view', $photo->id),
                'thumbnail' => route('photo.thumbnail', $photo->id),
            ],
        ])->values();
    }

    public function showPhotosPage()
    {
        $userId = Auth::user()->id;

        $photos = Photo::with('albums:id,name,is_system')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $favoriteAlbumId = $this->getOrCreateFavoriteAlbum()->id;

        $albums = Album::where('user_id', $userId)
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        [$counts, $covers] = $this->albumSummaries($albums);

        return view('photos', [
            'photos' => $photos,
            'albums' => $albums,
            'albumCounts' => $counts,
            'albumCovers' => $covers,
            'favoriteAlbumId' => $favoriteAlbumId,
            'photoData' => $this->photoPayload($photos, $favoriteAlbumId),
        ]);
    }

    public function showAlbum(Album $album)
    {
        if ($album->user_id !== Auth::user()->id) {
            abort(403);
        }

        $photos = $album->photos()
            ->with('albums:id,name,is_system')
            ->orderBy('photos.created_at', 'desc')
            ->orderBy('photos.id', 'desc')
            ->get();

        $favoriteAlbumId = $this->getOrCreateFavoriteAlbum()->id;

        $albums = Album::where('user_id', Auth::user()->id)
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        return view('album', [
            'album' => $album,
            'photos' => $photos,
            'albums' => $albums,
            'favoriteAlbumId' => $favoriteAlbumId,
            'photoData' => $this->photoPayload($photos, $favoriteAlbumId),
        ]);
    }

    public function storePhotos(Request $request)
    {
        $userId = Auth::user()->id;

        $request->validate([
            'photos' => 'required|array',
            'album_mode' => 'nullable|in:none,existing,new',
            'album_name' => 'nullable|string',
            'new_album_name' => 'nullable|string|max:120',
        ]);

        $mode = $request->input('album_mode', $request->filled('album_name') ? 'existing' : 'none');
        $album = null;

        if ($mode === 'new') {
            $name = trim((string) $request->input('new_album_name'));

            if ($name === '') {
                return $this->uploadError($request, 'Name the new album.');
            }

            $album = Album::firstOrCreate(
                ['user_id' => $userId, 'name' => $name],
                ['is_system' => false]
            );
        } elseif ($mode === 'existing' || $request->filled('album_name')) {
            $album = Album::where('user_id', $userId)
                ->where('name', $request->input('album_name'))
                ->first();

            if (! $album) {
                return $this->uploadError($request, 'That album does not exist.', 'album_name');
            }
        }

        $stored = 0;
        $rejected = [];

        foreach ($request->file('photos') as $file) {
            $check = Validator::make(['file' => $file], ['file' => self::FILE_RULES]);

            if ($check->fails()) {
                $rejected[] = [
                    'name' => $file->getClientOriginalName(),
                    'reason' => $this->rejectionReason($file),
                ];
                continue;
            }

            $path = $file->store('photos', self::DISK);

            $photo = Photo::create([
                'user_id' => $userId,
                'path' => $path,
                'protection' => 'none',
                'key_hash' => null,
            ]);

            if ($album) {
                $photo->albums()->attach($album->id);
            }

            $stored++;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $stored > 0,
                'stored' => $stored,
                'rejected' => $rejected,
                'error' => $rejected[0]['reason'] ?? null,
            ], $stored > 0 ? 200 : 422);
        }

        if ($stored === 0) {
            return back()->withErrors(['photos' => $this->rejectionSummary($rejected)]);
        }

        $message = $stored . ' ' . str($stored === 1 ? 'photo' : 'photos') . ' uploaded.';

        if ($rejected) {
            return back()->with('success', $message)->withErrors(['photos' => $this->rejectionSummary($rejected)]);
        }

        return back()->with('success', $message);
    }

    private function rejectionReason($file): string
    {
        if (! $file->isValid()) {
            return 'Upload failed — the file did not arrive intact.';
        }

        if ($file->getSize() > 20480 * 1024) {
            return 'Too large — ' . round($file->getSize() / 1048576, 1) . ' MB, the limit is 20 MB.';
        }

        return 'Not an image the site accepts (jpg, jpeg, png, webp or gif).';
    }

    private function rejectionSummary(array $rejected): string
    {
        return collect($rejected)->map(fn ($r) => $r['name'] . ' — ' . $r['reason'])->implode(' ');
    }

    private function uploadError(Request $request, string $message, string $field = 'photos')
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'stored' => 0, 'rejected' => [], 'error' => $message], 422);
        }

        return back()->withErrors([$field => $message]);
    }

    public function destroyPhoto(Photo $photo)
    {
        if ($photo->user_id !== Auth::user()->id) {
            abort(403);
        }

        $this->forget($photo);
        $photo->delete();

        return back()->with('success', 'Photo deleted.');
    }

    private function forget(Photo $photo): void
    {
        Storage::disk(self::DISK)->delete($photo->path);
        Storage::disk(self::DISK)->delete('thumbnails/' . basename($photo->path));
    }

    private function bulkIds(Request $request): array|string
    {
        $raw = $request->input('ids', []);

        if (! is_array($raw) || $raw === []) {
            return 'Nothing was selected.';
        }

        if (count($raw) > self::MAX_BULK) {
            return 'That is more than ' . self::MAX_BULK . ' photos at once.';
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $raw))));

        return $ids === [] ? 'Nothing was selected.' : $ids;
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $this->bulkIds($request);

        if (is_string($ids)) {
            return back()->withErrors(['ids' => $ids]);
        }

        $photos = Photo::where('user_id', Auth::user()->id)->whereIn('id', $ids)->get();

        if ($photos->isEmpty()) {
            return back()->withErrors(['ids' => 'Those photos are already gone.']);
        }

        foreach ($photos as $photo) {
            $this->forget($photo);
        }

        Photo::where('user_id', Auth::user()->id)->whereIn('id', $photos->pluck('id'))->delete();

        $count = $photos->count();

        return back()->with('success', $count . ' ' . ($count === 1 ? 'photo' : 'photos') . ' deleted.');
    }

    public function bulkAlbum(Request $request)
    {
        $ids = $this->bulkIds($request);

        if (is_string($ids)) {
            return back()->withErrors(['ids' => $ids]);
        }

        $mode = $request->input('mode');

        if (! in_array($mode, ['add', 'remove'], true)) {
            return back()->withErrors(['mode' => 'That is not something you can do to an album.']);
        }

        $album = Album::where('user_id', Auth::user()->id)
            ->where('id', (int) $request->input('album_id'))
            ->first();

        if (! $album) {
            return back()->withErrors(['album_id' => 'That album does not exist.']);
        }

        $found = Photo::where('user_id', Auth::user()->id)->whereIn('id', $ids)->pluck('id');

        if ($found->isEmpty()) {
            return back()->withErrors(['ids' => 'Those photos are already gone.']);
        }

        if ($mode === 'add') {
            DB::table('album_photo')->insertOrIgnore(
                $found->map(fn ($id) => ['album_id' => $album->id, 'photo_id' => $id])->all()
            );
        } else {
            DB::table('album_photo')->where('album_id', $album->id)->whereIn('photo_id', $found)->delete();
        }

        $count = $found->count();
        $noun = $count . ' ' . ($count === 1 ? 'photo' : 'photos');

        return back()->with('success', $mode === 'add'
            ? $noun . ' added to ' . $album->name . '.'
            : $noun . ' removed from ' . $album->name . '.');
    }

    public function toggleFavorite(Photo $photo)
    {
        if ($photo->user_id !== Auth::user()->id) {
            abort(403);
        }

        $favoriteAlbum = $this->getOrCreateFavoriteAlbum();

        if ($photo->albums()->where('albums.id', $favoriteAlbum->id)->exists()) {
            $photo->albums()->detach($favoriteAlbum->id);
        } else {
            $photo->albums()->attach($favoriteAlbum->id);
        }

        return back();
    }

    public function lockPhoto(Request $request, Photo $photo)
    {
        if ($photo->user_id !== Auth::user()->id) {
            abort(403);
        }

        $request->validate([
            'key' => 'required|string|min:8',
            'mode' => 'required|in:gated,encrypted',
        ]);

        $key = $request->input('key');
        $mode = $request->input('mode');

        Storage::disk(self::DISK)->delete('thumbnails/' . basename($photo->path));

        if ($mode === 'encrypted') {
            $fullPath = Storage::disk(self::DISK)->path($photo->path);
            $contents = file_get_contents($fullPath);
            $encrypted = openssl_encrypt($contents, 'aes-256-cbc', $key, 0, substr(hash('sha256', $key), 0, 16));
            Storage::disk(self::DISK)->put($photo->path, $encrypted);
        }

        $photo->update([
            'protection' => $mode,
            'key_hash' => Hash::make($key),
        ]);

        return back()->with('success', 'Photo locked.');
    }

    public function unlockPhoto(Request $request, Photo $photo)
    {
        if ($photo->user_id !== Auth::user()->id) {
            abort(403);
        }

        $request->validate(['key' => 'required|string']);
        $key = $request->input('key');

        if (!Hash::check($key, $photo->key_hash)) {
            return back()->withErrors(['key' => 'Incorrect key.']);
        }

        if ($photo->protection === 'encrypted') {
            $fullPath = Storage::disk(self::DISK)->path($photo->path);
            $contents = file_get_contents($fullPath);
            $decrypted = openssl_decrypt($contents, 'aes-256-cbc', $key, 0, substr(hash('sha256', $key), 0, 16));
            Storage::disk(self::DISK)->put($photo->path, $decrypted);
        }

        Storage::disk(self::DISK)->delete('thumbnails/' . basename($photo->path));

        $photo->update(['protection' => 'none', 'key_hash' => null]);

        return back()->with('success', 'Photo unlocked.');
    }

    public function viewPhoto(Request $request, Photo $photo)
    {
        if ($photo->user_id !== Auth::user()->id) {
            abort(403);
        }

        if ($photo->protection === 'none') {
            return response()->file(Storage::disk(self::DISK)->path($photo->path));
        }

        $key = $request->query('key');

        if (!$key || !Hash::check($key, $photo->key_hash)) {
            return response('Key required or incorrect.', 403);
        }

        $fullPath = Storage::disk(self::DISK)->path($photo->path);

        if ($photo->protection === 'gated') {
            return response()->file($fullPath, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        }

        $contents = file_get_contents($fullPath);
        $decrypted = openssl_decrypt($contents, 'aes-256-cbc', $key, 0, substr(hash('sha256', $key), 0, 16));
        return response($decrypted)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function viewThumbnail(Photo $photo)
    {
        if ($photo->user_id !== Auth::user()->id) {
            abort(403);
        }

        if ($photo->protection === 'encrypted') {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
                <rect x="3" y="11" width="18" height="10" rx="2" fill="#333" stroke="#888"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="#888"/>
                <circle cx="12" cy="16" r="1.5" fill="#888"/>
            </svg>';

            return response($svg)->header('Content-Type', 'image/svg+xml');
        }

        $thumbPath = 'thumbnails/' . basename($photo->path);

        if (!Storage::disk(self::DISK)->exists($thumbPath)) {
            $manager = ImageManager::usingDriver(Driver::class);
            $fullPath = Storage::disk(self::DISK)->path($photo->path);
            $image = $manager->decodePath($fullPath);
            $image->scale(width: 300);

            if ($photo->protection === 'gated') {
                $width = $image->width();
                $height = $image->height();
                $image->scale(width: 12);
                $image->scale(width: $width, height: $height);
            }

            Storage::disk(self::DISK)->put($thumbPath, (string) $image->encode());
        }

        return response()->file(Storage::disk(self::DISK)->path($thumbPath));
    }

    public function storeAlbum(Request $request)
    {
        $request->validate(['name' => 'required|string|max:120']);

        $name = trim($request->input('name'));

        if (Album::where('user_id', Auth::user()->id)->where('name', $name)->exists()) {
            return back()->withErrors(['name' => 'There is already an album called that.']);
        }

        Album::create([
            'user_id' => Auth::user()->id,
            'name' => $name,
            'is_system' => false,
        ]);

        return back()->with('success', 'Album created.');
    }

    public function updateAlbum(Request $request, Album $album)
    {
        if ($album->user_id !== Auth::user()->id) {
            abort(403);
        }
        if ($album->is_system) {
            abort(403, 'Cannot rename the Favorite album.');
        }

        $request->validate(['name' => 'required|string|max:120']);

        $name = trim($request->input('name'));

        if (Album::where('user_id', Auth::user()->id)->where('name', $name)->where('id', '!=', $album->id)->exists()) {
            return back()->withErrors(['name' => 'There is already an album called that.']);
        }

        $album->update(['name' => $name]);

        return back()->with('success', 'Album renamed.');
    }

    public function destroyAlbum(Album $album)
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
