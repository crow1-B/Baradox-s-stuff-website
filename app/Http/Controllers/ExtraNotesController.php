<?php

namespace App\Http\Controllers;

use App\Models\ExtraNote;
use App\Support\Links\LinkResolver;
use App\Support\Links\VideoLinkHandler;
use App\Support\NoteFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExtraNotesController extends Controller
{
    private const MAX_LENGTH = 20000;

    // ------------------------------------------------------------------ pages

    public function showExtraNotesPage(Request $request)
    {
        $page = $this->page($request);

        return view('extra_notes', [
            'notes' => $page['notes'],
            'formatter' => $this->formatter($page['notes']->pluck('content')),
            'hasMore' => $page['has_more'],
            'pinned' => $this->pinnedNotes(),
            'tags' => config('extra_notes.tags'),
            'maxTags' => config('extra_notes.max_tags'),
            'maxLength' => self::MAX_LENGTH,
            'query' => $this->searchTerm($request),
            'activeTags' => $this->requestedTags($request),
            'totalCount' => ExtraNote::where('user_id', Auth::user()->id)->count(),
        ]);
    }


    public function messages(Request $request)
    {
        $page = $this->page($request);

        return response()->json([
            'html' => $this->renderCards($page['notes']),
            'has_more' => $page['has_more'],
            'oldest_id' => $page['notes']->first()?->id,
            'count' => $page['notes']->count(),
            'total' => ExtraNote::where('user_id', Auth::user()->id)->count(),
            'filtering' => $this->isFiltering($request),
        ]);
    }

    // ------------------------------------------------------------------ writing

    public function storeNote(Request $request)
    {
        $content = $this->cleanContent($request->input('content'));

        if ($content === null) {
            return $this->flashStream('Nothing to save — the message was empty.', 'error');
        }

        $note = ExtraNote::create([
            'user_id' => Auth::user()->id,
            'content' => $content,
            'is_pinned' => false,
            'tags' => ExtraNote::tagsColumn((array) $request->input('tags', [])),
        ]);


        return $this->stream([
            $this->fragment('append', 'xn-stream-list', $this->renderCards(new Collection([$note]))),
            $this->clearFlash(),
        ]);
    }

    public function updateNote(Request $request, ExtraNote $note)
    {
        if ($note->user_id !== Auth::user()->id) {
            abort(403);
        }

        $content = $this->cleanContent($request->input('content'));

        if ($content === null) {
            return $this->flashStream('A message cannot be emptied — delete it instead.', 'error');
        }

        $note->update(['content' => $content]);

        return $this->replaceStream($note);
    }

    public function destroyNote(ExtraNote $note)
    {
        if ($note->user_id !== Auth::user()->id) {
            abort(403);
        }

        $note->delete();

        return $this->stream([
            $this->fragment('remove', 'xn-msg-' . $note->id),
            $this->pinnedFragment(),
            $this->flashFragment('Message deleted.', 'success'),
        ]);
    }

    // ------------------------------------------------------------------ organization

    public function togglePin(ExtraNote $note)
    {
        if ($note->user_id !== Auth::user()->id) {
            abort(403);
        }

        $pinned = ! $note->is_pinned;

        DB::table('extra_notes')
            ->where('id', $note->id)
            ->where('user_id', Auth::user()->id)
            ->update(['is_pinned' => $pinned]);

        $note->is_pinned = $pinned;

        return $this->replaceStream($note);
    }
    public function updateTags(Request $request, ExtraNote $note)
    {
        if ($note->user_id !== Auth::user()->id) {
            abort(403);
        }

        $keys = (array) $request->input('tags', []);

        if (count($keys) > config('extra_notes.max_tags')) {
            return $this->flashStream('At most ' . config('extra_notes.max_tags') . ' tags per message.', 'error');
        }

        $tags = ExtraNote::tagsColumn($keys);

        DB::table('extra_notes')
            ->where('id', $note->id)
            ->where('user_id', Auth::user()->id)
            ->update(['tags' => $tags]);

        $note->tags = $tags;

        return $this->replaceStream($note);
    }

    // ------------------------------------------------------------------ bulk

    public function bulkDestroy(Request $request)
    {
        $ids = $this->bulkIds($request);

        if (is_string($ids)) {
            return $this->flashStream($ids, 'error');
        }

        $found = ExtraNote::where('user_id', Auth::user()->id)->whereIn('id', $ids)->pluck('id');

        if ($found->isEmpty()) {
            return $this->flashStream('Those messages are already gone.', 'error');
        }

        ExtraNote::where('user_id', Auth::user()->id)->whereIn('id', $found)->delete();

        return $this->stream([
            ...$found->map(fn ($id) => $this->fragment('remove', 'xn-msg-' . $id))->all(),
            $this->pinnedFragment(),
            $this->flashFragment($this->plural($found->count(), 'message') . ' deleted.', 'success'),
        ]);
    }

    public function bulkTags(Request $request)
    {
        $ids = $this->bulkIds($request);

        if (is_string($ids)) {
            return $this->flashStream($ids, 'error');
        }

        $tag = (string) $request->input('tag');
        $mode = $request->input('mode');

        if (! array_key_exists($tag, config('extra_notes.tags')) || ! in_array($mode, ['add', 'remove'], true)) {
            return $this->flashStream('That is not a tag you can apply.', 'error');
        }

        $notes = ExtraNote::where('user_id', Auth::user()->id)->whereIn('id', $ids)->get();

        if ($notes->isEmpty()) {
            return $this->flashStream('Those messages are already gone.', 'error');
        }

        $max = config('extra_notes.max_tags');
        $changed = new Collection();
        $full = 0;

        $groups = [];

        foreach ($notes as $note) {
            $keys = $note->tagKeys();
            $has = in_array($tag, $keys, true);

            if ($mode === 'add') {
                if ($has) {
                    continue;
                }
                if (count($keys) >= $max) {
                    $full++;
                    continue;
                }
                $keys[] = $tag;
            } else {
                if (! $has) {
                    continue;
                }
                $keys = array_values(array_diff($keys, [$tag]));
            }

            $value = ExtraNote::tagsColumn($keys);
            $groups[$value ?? ''][] = $note->id;
            $note->tags = $value;
            $changed->push($note);
        }

        foreach ($groups as $value => $groupIds) {
            DB::table('extra_notes')
                ->where('user_id', Auth::user()->id)
                ->whereIn('id', $groupIds)
                ->update(['tags' => $value === '' ? null : $value]);
        }

        $label = config('extra_notes.tags')[$tag]['label'];
        $verb = $mode === 'add' ? 'added to' : 'removed from';
        $message = $changed->isEmpty()
            ? 'Nothing to change — ' . $label . ' was already ' . ($mode === 'add' ? 'on' : 'off') . ' every message you picked.'
            : $label . ' ' . $verb . ' ' . $this->plural($changed->count(), 'message') . '.';

        if ($full > 0) {
            $message .= ' ' . $this->plural($full, 'message') . ' already had ' . $max . ' tags and ' . ($full === 1 ? 'was' : 'were') . ' left alone.';
        }

        return $this->stream([
            ...$changed->map(fn (ExtraNote $note) => $this->fragment(
                'replace',
                'xn-msg-' . $note->id,
                $this->renderCards(new Collection([$note])),
            ))->all(),
            $this->pinnedFragment(),
            $this->flashFragment($message, $changed->isEmpty() ? 'error' : 'success'),
        ]);
    }

    // ------------------------------------------------------------------ querying

    /**
     * One page of the stream, oldest-first so it can be appended straight into the view.
     *
     * @return array{notes: Collection, has_more: bool}
     */
    private function page(Request $request): array
    {
        $size = config('extra_notes.page_size');
        $query = $this->filtered($request);

        if ($before = (int) $request->input('before', 0)) {
            $query->where('id', '<', $before);
        }
        $rows = $query->orderBy('id', 'desc')->limit($size + 1)->get();
        $hasMore = $rows->count() > $size;

        return [
            'notes' => $rows->take($size)->reverse()->values(),
            'has_more' => $hasMore,
        ];
    }

    private function filtered(Request $request): Builder
    {
        $query = ExtraNote::where('user_id', Auth::user()->id);

        if (($term = $this->searchTerm($request)) !== '') {
            $query->where('content', 'like', '%' . $this->escapeLike($term) . '%');
        }

        $tags = $this->requestedTags($request);

        if ($tags !== []) {
            $query->where(function (Builder $sub) use ($tags) {
                foreach ($tags as $tag) {
                    $sub->orWhereRaw('FIND_IN_SET(?, tags)', [$tag]);
                }
            });
        }

        return $query;
    }

    private function pinnedNotes(): Collection
    {
        return ExtraNote::where('user_id', Auth::user()->id)
            ->where('is_pinned', true)
            ->orderBy('id', 'desc')
            ->get();
    }

    private function searchTerm(Request $request): string
    {
        return trim((string) $request->input('q', ''));
    }

    /** @return array<int, string> */
    private function requestedTags(Request $request): array
    {
        $raw = $request->input('tags', '');
        $keys = is_array($raw) ? $raw : explode(',', (string) $raw);

        return ExtraNote::normaliseTagList($keys);
    }

    private function isFiltering(Request $request): bool
    {
        return $this->searchTerm($request) !== '' || $this->requestedTags($request) !== [];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    // ------------------------------------------------------------------ validation

    private function cleanContent(mixed $value): ?string
    {
        $content = trim((string) $value);

        if ($content === '') {
            return null;
        }

        return mb_substr($content, 0, self::MAX_LENGTH);
    }

    /**
     * Selection lives in the browser, so the array arriving here is untrusted in both shape
     * and size — it is capped and coerced before it reaches a query.
     *
     * @return array<int, int>|string  the ids, or the message explaining why not
     */
    private function bulkIds(Request $request): array|string
    {
        $raw = $request->input('ids', []);

        if (! is_array($raw) || $raw === []) {
            return 'Nothing was selected.';
        }

        if (count($raw) > config('extra_notes.max_bulk')) {
            return 'That is more than ' . config('extra_notes.max_bulk') . ' messages at once.';
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $raw))));

        return $ids === [] ? 'Nothing was selected.' : $ids;
    }

    private function plural(int $count, string $word): string
    {
        return $count . ' ' . $word . ($count === 1 ? '' : 's');
    }

    // ------------------------------------------------------------------ rendering

    /**
     * A formatter whose link resolver has already been primed over EVERY text about to be
     * rendered — one batched query per link type for the whole page, never one per message.
     *
     * @param  iterable<string>  $contents
     */
    private function formatter(iterable $contents): NoteFormatter
    {
        $resolver = (new LinkResolver())->register(new VideoLinkHandler());
        $resolver->prime($contents, Auth::user()->id);

        return new NoteFormatter($resolver);
    }

    private function renderCards(Collection $notes): string
    {
        $formatter = $this->formatter($notes->pluck('content'));

        return $notes->map(fn (ExtraNote $note) => view('partials.xn-message', [
            'note' => $note,
            'formatter' => $formatter,
            'tags' => config('extra_notes.tags'),
        ])->render())->implode('');
    }

    // ------------------------------------------------------------------ turbo streams

    private function stream(array $fragments)
    {
        return response(implode("\n", array_filter($fragments)))
            ->header('Content-Type', 'text/vnd.turbo-stream.html; charset=utf-8');
    }

    private function fragment(string $action, string $target, ?string $html = null): string
    {
        // Every action except "remove" carries its new markup inside a <template>.
        $body = $html === null ? '' : '<template>' . $html . '</template>';

        return '<turbo-stream action="' . $action . '" target="' . $target . '">' . $body . '</turbo-stream>';
    }

    
    private function replaceStream(ExtraNote $note)
    {
        return $this->stream([
            $this->fragment('replace', 'xn-msg-' . $note->id, $this->renderCards(new Collection([$note]))),
            $this->pinnedFragment(),
            $this->clearFlash(),
        ]);
    }

    private function pinnedFragment(): string
    {
        return $this->fragment('replace', 'xn-pinned', view('partials.xn-pinned', [
            'pinned' => $this->pinnedNotes(),
            'tags' => config('extra_notes.tags'),
        ])->render());
    }

    private function flashFragment(string $message, string $kind): string
    {
        return $this->fragment('update', 'xn-flash', view('partials.xn-flash', [
            'message' => $message,
            'kind' => $kind,
        ])->render());
    }

    private function flashStream(string $message, string $kind)
    {
        return $this->stream([$this->flashFragment($message, $kind)]);
    }

    private function clearFlash(): string
    {
        return $this->fragment('update', 'xn-flash', '');
    }
}
