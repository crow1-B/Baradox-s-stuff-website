<?php

namespace App\Support\Links;

use App\Models\Video;


class VideoLinkHandler implements LinkHandler
{
    public function type(): string
    {
        return 'video';
    }

    public function pattern(): string
    {
        return '~(?:https?://[^\s/]+)?/videos/watch/([A-Za-z0-9_-]+)~';
    }

    public function resolve(array $keys, int $userId): array
    {
        return Video::where('user_id', $userId)
            ->whereIn('slug', $keys)
            ->get(['slug', 'title'])
            ->mapWithKeys(fn (Video $video) => [$video->slug => [
                'label' => $video->title,
                'url' => route('video.slug', $video->slug),
                'icon' => 'fa-solid fa-film',
            ]])
            ->all();
    }
}
