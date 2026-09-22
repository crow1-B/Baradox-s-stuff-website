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

class DiaryController extends Controller
{
    private const DISK = 'ssd_photos';
    private const ALGORITHMS = ['aes', 'vigenere', 'chacha20'];


    private const ASCII_FLOOR = 32;
    private const ASCII_RANGE = 95;

    private function diaryPageData(): array
    {
        $entries = DiaryEntry::where('user_id', Auth::user()->id)
            ->with(['people', 'photo'])
            ->orderByRaw('COALESCE(event_date, created_at) DESC')
            ->get();

        $photos = Photo::where('user_id', Auth::user()->id)
            ->where('protection', 'none')
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'Diary' => 'Here To Make A Memory, Or Remember One ?',
            'entries' => $entries,
            'photos' => $photos,
            'emptyMessage' => $entries->isEmpty() ? 'no memories here yet !' : null,
        ];
    }

    public function showDiaryPage()
    {
        return view('diary', $this->diaryPageData());
    }

    public function storeEntry(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'event_date' => 'nullable|date',
            'people' => 'nullable|string',
            'photo_source' => 'required|in:none,existing,upload',
            'photo_id' => 'nullable|integer',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:20480',
        ]);

        $entry = DiaryEntry::create([
            'user_id' => Auth::user()->id,
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'event_date' => $request->input('event_date'),
            'key_hash' => null,
            'algorithm_hash' => null,
            'photo_id' => $this->resolvePhotoId($request),
        ]);

        $entry->people()->sync($this->resolvePeopleIds($request->input('people')));

        return back()->with('success', 'Memory saved.');
    }

    public function updateEntry(Request $request, DiaryEntry $entry)
    {
        if ($entry->user_id !== Auth::user()->id) {
            abort(403);
        }

        if ($entry->isLocked()) {
            return back()->withErrors(['title' => 'Unlock this memory before editing it.']);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'event_date' => 'nullable|date',
            'people' => 'nullable|string',
            'photo_source' => 'required|in:none,keep,existing,upload',
            'photo_id' => 'nullable|integer',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:20480',
        ]);

        $entry->update([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'event_date' => $request->input('event_date'),
            'photo_id' => $request->input('photo_source') === 'keep'
                ? $entry->photo_id
                : $this->resolvePhotoId($request),
        ]);

        $entry->people()->sync($this->resolvePeopleIds($request->input('people')));

        return back()->with('success', 'Memory updated.');
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

        return back()->with('success', 'Memory deleted.');
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
            return back()->with('generated_key', $key)->with('generated_key_entry', $entry->id);
        }

        return back()->with('success', 'Memory locked.');
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

        return back()->with('success', 'Memory unlocked.');
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

        return view('diary', $this->diaryPageData() + [
            'revealedId' => $entry->id,
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

    private function resolvePhotoId(Request $request): ?int
    {
        $source = $request->input('photo_source');

        if ($source === 'existing') {
            $photo = Photo::where('user_id', Auth::user()->id)
                ->where('id', $request->input('photo_id'))
                ->first();

            if (!$photo) {
                abort(404, 'That photo does not exist.');
            }

            return $photo->id;
        }

        if ($source === 'upload') {
            if (!$request->hasFile('photo')) {
                abort(422, 'No photo was uploaded.');
            }

            $photo = Photo::create([
                'user_id' => Auth::user()->id,
                'path' => $request->file('photo')->store('photos', self::DISK),
                'protection' => 'none',
                'key_hash' => null,
            ]);

            return $photo->id;
        }

        return null;
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
