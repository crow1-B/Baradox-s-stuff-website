<?php

namespace App\Support\Links;


class LinkResolver
{
    /** @var array<int, LinkHandler> */
    private array $handlers = [];

    /** @var array<string, array<string, array{label: string, url: string, icon: string}>> */
    private array $chips = [];

    private bool $primed = false;

    public function register(LinkHandler $handler): static
    {
        $this->handlers[] = $handler;

        return $this;
    }

    /**
     * @param  iterable<string>  $texts
     */
    public function prime(iterable $texts, int $userId): static
    {
        $texts = is_array($texts) ? $texts : iterator_to_array($texts);

        foreach ($this->handlers as $handler) {
            $keys = [];

            foreach ($texts as $text) {
                if (preg_match_all($handler->pattern(), (string) $text, $matches)) {
                    foreach ($matches[1] as $key) {
                        $keys[$key] = true;
                    }
                }
            }

            $this->chips[$handler->type()] = $keys === []
                ? []
                : $handler->resolve(array_keys($keys), $userId);
        }

        $this->primed = true;

        return $this;
    }

    /** @return array<int, LinkHandler> */
    public function handlers(): array
    {
        return $this->handlers;
    }

    /**
     * A resolved chip, or null when the key does not resolve — a deleted video, or a slug
     * that never existed.
     *
     * @return array{label: string, url: string, icon: string}|null
     */
    public function chip(string $type, string $key): ?array
    {
        return $this->chips[$type][$key] ?? null;
    }

    public function assertPrimed(): void
    {
        if (! $this->primed) {
            throw new \LogicException('LinkResolver::prime() must run over every text before rendering any of them.');
        }
    }
}
