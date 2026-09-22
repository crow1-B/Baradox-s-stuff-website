<?php

namespace App\Support\Links;


interface LinkHandler
{
    public function type(): string;

    public function pattern(): string;

    /**
     * Resolve every key found across ALL the text being rendered.
     *
     * @param  array<int, string>  $keys
     * @return array<string, array{label: string, url: string, icon: string}>
     */
    public function resolve(array $keys, int $userId): array;
}
