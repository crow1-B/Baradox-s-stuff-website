<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'content', 'is_pinned', 'tags'])]
class ExtraNote extends Model
{
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    public function updateTimestamps()
    {
        parent::updateTimestamps();

        if (! $this->exists) {
            $this->attributes[$this->getUpdatedAtColumn()] = null;
        }

        return $this;
    }

    public function isEdited(): bool
    {
        return $this->updated_at !== null;
    }

    /**
     * The stored keys, in config order, with anything not in the current vocabulary dropped.
     *
     * @return array<int, string>
     */
    public function tagKeys(): array
    {
        return self::normaliseTagList(explode(',', (string) $this->tags));
    }

    /**
     * Filters to the allowed vocabulary
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public static function normaliseTagList(array $keys): array
    {
        $allowed = array_keys(config('extra_notes.tags'));
        $wanted = array_map(fn ($key) => trim((string) $key), $keys);

        return array_values(array_filter($allowed, fn ($key) => in_array($key, $wanted, true)));
    }

    /**
     * The column value for a set of keys: normalised, capped, and null rather than '' when
     * empty so FIND_IN_SET and "has no tags" both stay unambiguous.
     *
     * @param  array<int, string>  $keys
     */
    public static function tagsColumn(array $keys): ?string
    {
        $normalised = array_slice(self::normaliseTagList($keys), 0, config('extra_notes.max_tags'));

        return $normalised === [] ? null : implode(',', $normalised);
    }
}
