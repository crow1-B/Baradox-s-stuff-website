<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\DiaryEntry;
use App\Models\Person;
use App\Models\Photo;
use App\Models\Track;
use App\Models\TrackPlay;

class DiaryController extends Controller
{
    private const DISK = 'ssd_photos';
    private const ALGORITHMS = ['aes', 'vigenere', 'chacha20'];


    private const ASCII_FLOOR = 32;
    private const ASCII_RANGE = 95;

    private const MAX_PHOTOS = 24;

    private const ORDER = 'COALESCE(event_date, created_at)';

    // What the "Close the day" flow needs, on the index (to write) and on an entry (to edit).
    // The picker offers unprotected photos, plus whatever the entry already holds whatever
    // its protection is now: an edit must be able to keep a photo that was locked since.
    private function flowData(?DiaryEntry $editing = null): array
    {
        $userId = Auth::user()->id;
        $attached = $editing?->photos->pluck('id') ?? collect();

        return [
            'photos' => Photo::where('user_id', $userId)
                ->where(fn ($query) => $query->where('protection', 'none')->orWhereIn('id', $attached))
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get(),
            'tracks' => Track::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get(),
            'topTracks' => TrackPlay::topByDate($userId),
            'moods' => config('diary.moods'),
            'canWrite' => !Auth::user()->isPreview(),
        ];
    }

    public function showDiaryPage()
    {
        $entries = DiaryEntry::where('user_id', Auth::user()->id)
            ->with(['people', 'photos', 'track'])
            ->orderByRaw(self::ORDER . ' DESC')
            ->orderBy('id', 'desc')
            ->get();

        return view('diary', ['entries' => $entries] + $this->flowData());
    }

    private function entryPageData(DiaryEntry $entry): array
    {
        $entry->load(['people', 'photos', 'track']);
        $userId = Auth::user()->id;
        $day = $entry->day()->format('Y-m-d H:i:s');

        // Neighbours in the diary's own order (day, then id), so arrowing through matches
        // the index top to bottom.
        $older = DiaryEntry::where('user_id', $userId)
            ->where(fn ($query) => $query->whereRaw(self::ORDER . ' < ?', [$day])
                ->orWhere(fn ($same) => $same->whereRaw(self::ORDER . ' = ?', [$day])->where('id', '<', $entry->id)))
            ->orderByRaw(self::ORDER . ' DESC')->orderBy('id', 'desc')
            ->first(['id', 'title', 'key_hash', 'event_date', 'created_at']);

        $newer = DiaryEntry::where('user_id', $userId)
            ->where(fn ($query) => $query->whereRaw(self::ORDER . ' > ?', [$day])
                ->orWhere(fn ($same) => $same->whereRaw(self::ORDER . ' = ?', [$day])->where('id', '>', $entry->id)))
            ->orderByRaw(self::ORDER . ' ASC')->orderBy('id', 'asc')
            ->first(['id', 'title', 'key_hash', 'event_date', 'created_at']);

        return ['entry' => $entry, 'older' => $older, 'newer' => $newer] + $this->flowData($entry);
    }

    public function showEntry(DiaryEntry $entry)
    {
        if ($entry->user_id !== Auth::user()->id) {
            abort(403);
        }

        return view('diary-entry', $this->entryPageData($entry));
    }

    private function entryRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'event_date' => 'nullable|date',
            'mood' => ['nullable', 'string', 'in:' . implode(',', array_keys(config('diary.moods')))],
            'track_id' => 'nullable|integer',
            'people' => 'nullable|string',
            'photo_ids' => 'nullable|array|max:' . self::MAX_PHOTOS,
            'photo_ids.*' => 'integer|distinct',
            'photos' => 'nullable|array|max:' . self::MAX_PHOTOS,
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp,gif|max:20480',
        ];
    }

    // Everything the entry points at is checked before anything is written: an id that is
    // not this user's is an error, never something quietly dropped.
    private function resolveAttachments(Request $request): array|\Illuminate\Http\RedirectResponse
    {
        $userId = Auth::user()->id;

        $wanted = collect($request->input('photo_ids', []))->map(fn ($id) => (int) $id)->unique()->values();
        $found = Photo::where('user_id', $userId)->whereIn('id', $wanted)->pluck('id');

        if ($found->count() !== $wanted->count()) {
            return back()->withInput()->withErrors(['photo_ids' => 'One of those photos no longer exists.']);
        }

        if ($wanted->count() + count($request->file('photos', [])) > self::MAX_PHOTOS) {
            return back()->withInput()->withErrors(['photo_ids' => 'An entry holds at most ' . self::MAX_PHOTOS . ' photos.']);
        }

        $trackId = $request->input('track_id');

        if ($trackId !== null && !Track::where('user_id', $userId)->whereKey($trackId)->exists()) {
            return back()->withInput()->withErrors(['track_id' => 'That song no longer exists.']);
        }

        return ['photo_ids' => $wanted->all(), 'track_id' => $trackId === null ? null : (int) $trackId];
    }

    private function storeUploads(Request $request): array
    {
        $ids = [];

        foreach ($request->file('photos', []) as $file) {
            $ids[] = Photo::create([
                'user_id' => Auth::user()->id,
                'path' => $file->store('photos', self::DISK),
                'protection' => 'none',
                'key_hash' => null,
            ])->id;
        }

        return $ids;
    }

    public function storeEntry(Request $request)
    {
        $request->validate($this->entryRules());

        $attachments = $this->resolveAttachments($request);
        if ($attachments instanceof \Illuminate\Http\RedirectResponse) {
            return $attachments;
        }

        $entry = DiaryEntry::create([
            'user_id' => Auth::user()->id,
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'event_date' => $request->input('event_date'),
            'mood' => $request->input('mood'),
            'track_id' => $attachments['track_id'],
            'key_hash' => null,
            'algorithm_hash' => null,
        ]);

        $entry->photos()->sync([...$attachments['photo_ids'], ...$this->storeUploads($request)]);
        $entry->people()->sync($this->resolvePeopleIds($request->input('people')));

        // Straight into the entry it became: closing the day ends on reading it back.
        return redirect()->route('diary.entry', $entry)->with('success', 'The day is closed.')->with('dy_saved', $entry->id);
    }

    public function updateEntry(Request $request, DiaryEntry $entry)
    {
        if ($entry->user_id !== Auth::user()->id) {
            abort(403);
        }

        if ($entry->isLocked()) {
            return back()->withErrors(['title' => 'Unlock this memory before editing it.']);
        }

        $request->validate($this->entryRules());

        $attachments = $this->resolveAttachments($request);
        if ($attachments instanceof \Illuminate\Http\RedirectResponse) {
            return $attachments;
        }

        $entry->update([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'event_date' => $request->input('event_date'),
            'mood' => $request->input('mood'),
            'track_id' => $attachments['track_id'],
        ]);

        // photo_ids is the whole set the entry should end up with, kept ones included.
        $entry->photos()->sync([...$attachments['photo_ids'], ...$this->storeUploads($request)]);
        $entry->people()->sync($this->resolvePeopleIds($request->input('people')));

        return back()->with('success', 'Memory updated.')->with('dy_saved', $entry->id);
    }

    public function destroyEntry(Request $request, DiaryEntry $entry)
    {
        if ($entry->user_id !== Auth::user()->id) {
            abort(403);
        }

        if ($entry->isLocked()) {
            $request->validate([
                'key' => 'required|string',
                'algorithm' => 'required|string',
            ]);

            if (!$this->credentialsMatch($entry, $request->input('algorithm'), $request->input('key'))) {
                return $this->reopen($entry, 'delete')->withErrors(['key' => 'Incorrect key or algorithm.']);
            }
        }

        $entry->delete();

        // Not back(): the page it was deleted from no longer exists.
        return redirect()->route('diary')->with('success', 'Memory deleted.');
    }

    public function lockEntry(Request $request, DiaryEntry $entry)
    {
        if ($entry->user_id !== Auth::user()->id) {
            abort(403);
        }

        if ($entry->isLocked()) {
            return $this->reopen($entry, 'lock')->withErrors(['key' => 'This memory is already locked.']);
        }

        $request->validate([
            'algorithm' => 'required|in:aes,vigenere,chacha20',
            'key' => 'required_without:generate_key|nullable|string|min:8',
        ]);

        $algorithm = $request->input('algorithm');
        $generated = $request->boolean('generate_key');
        $key = $generated ? Str::random(32) : $request->input('key');

        $entry->update([
            'content' => $this->encryptContent($entry->content, $algorithm, $key),
            'key_hash' => Hash::make($key),
            'algorithm_hash' => Hash::make($algorithm),
        ]);

        if ($generated) {
            return back()->with('generated_key', $key)->with('generated_key_entry', $entry->id)->with('dy_sealed', $entry->id);
        }

        return back()->with('success', 'Memory sealed.')->with('dy_sealed', $entry->id);
    }

    public function unlockEntry(Request $request, DiaryEntry $entry)
    {
        if ($entry->user_id !== Auth::user()->id) {
            abort(403);
        }

        if (!$entry->isLocked()) {
            return $this->reopen($entry, 'unlock')->withErrors(['key' => 'This memory is not locked.']);
        }

        $request->validate([
            'key' => 'required|string',
            'algorithm' => 'required|string',
        ]);

        $algorithm = $request->input('algorithm');
        $key = $request->input('key');

        if (!$this->credentialsMatch($entry, $algorithm, $key)) {
            return $this->reopen($entry, 'unlock')->withErrors(['key' => 'Incorrect key or algorithm.']);
        }

        $plain = $this->decryptContent($entry->content, $algorithm, $key);

        if ($plain === null) {
            return $this->reopen($entry, 'unlock')->withErrors(['key' => 'Decryption failed — the stored content may be corrupt.']);
        }

        $entry->update([
            'content' => $plain,
            'key_hash' => null,
            'algorithm_hash' => null,
        ]);

        return back()->with('success', 'Memory unsealed.')->with('dy_unsealed', $entry->id);
    }


    public function revealEntry(Request $request, DiaryEntry $entry)
    {
        if ($entry->user_id !== Auth::user()->id) {
            abort(403);
        }

        if (!$entry->isLocked()) {
            return back()->withErrors(['key' => 'This memory is not locked.']);
        }

        $request->validate([
            'key' => 'required|string',
            'algorithm' => 'required|string',
        ]);

        $algorithm = $request->input('algorithm');
        $key = $request->input('key');

        if (!$this->credentialsMatch($entry, $algorithm, $key)) {
            return back()->withErrors(['key' => 'Incorrect key or algorithm.']);
        }

        $plain = $this->decryptContent($entry->content, $algorithm, $key);

        if ($plain === null) {
            return back()->withErrors(['key' => 'Decryption failed — the stored content may be corrupt.']);
        }

        return view('diary-entry', $this->entryPageData($entry) + [
            'revealedContent' => $plain,
        ]);
    }


    private function reopen(DiaryEntry $entry, string $dialog): \Illuminate\Http\RedirectResponse
    {
        return back()->with('dy_reopen', $dialog . ':' . $entry->id);
    }

    
    private function credentialsMatch(DiaryEntry $entry, string $algorithm, string $key): bool
    {
        return Hash::check($algorithm, $entry->algorithm_hash)
            && Hash::check($key, $entry->key_hash);
    }

    private function resolvePeopleIds(?string $people): array
    {
        return Person::idsFromList(Auth::user()->id, $people);
    }

    private function encryptContent(string $content, string $algorithm, string $key): string
    {
        if ($algorithm === 'aes') {
            return openssl_encrypt($content, 'aes-256-cbc', $key, 0, substr(hash('sha256', $key), 0, 16));
        }

        if ($algorithm === 'chacha20') {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($content, $nonce, hash('sha256', $key, true));

            return base64_encode($nonce . $cipher);
        }

        return $this->vigenere(base64_encode($content), $key, true);
    }

    private function decryptContent(string $content, string $algorithm, string $key): ?string
    {
        if ($algorithm === 'aes') {
            $plain = openssl_decrypt($content, 'aes-256-cbc', $key, 0, substr(hash('sha256', $key), 0, 16));

            return $plain === false ? null : $plain;
        }

        if ($algorithm === 'chacha20') {
            $raw = base64_decode($content, true);

            if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                return null;
            }

            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, hash('sha256', $key, true));

            return $plain === false ? null : $plain;
        }

        $plain = base64_decode($this->vigenere($content, $key, false), true);

        return $plain === false ? null : $plain;
    }

    private function vigenere(string $text, string $key, bool $encrypt): string
    {
        $keyLength = strlen($key);
        $out = '';

        for ($i = 0, $n = strlen($text); $i < $n; $i++) {
            $char = ord($text[$i]);

            if ($char < self::ASCII_FLOOR || $char > self::ASCII_FLOOR + self::ASCII_RANGE - 1) {
                $out .= $text[$i];
                continue;
            }

            $shift = (ord($key[$i % $keyLength]) - self::ASCII_FLOOR + self::ASCII_RANGE) % self::ASCII_RANGE;
            $offset = $char - self::ASCII_FLOOR;

            $shifted = $encrypt
                ? ($offset + $shift) % self::ASCII_RANGE
                : ($offset - $shift + self::ASCII_RANGE) % self::ASCII_RANGE;

            $out .= chr($shifted + self::ASCII_FLOOR);
        }

        return $out;
    }
}
