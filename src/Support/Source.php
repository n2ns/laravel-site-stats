<?php

namespace N2ns\Stats\Support;

/** Traffic source of a visit, decided at its entry pageview. */
final readonly class Source
{
    public const TYPES = ['utm', 'search', 'social', 'ai', 'referral', 'direct'];

    public function __construct(
        public string $type,          // one of TYPES
        public ?string $source,       // referrer host (no www.) or utm_source; null for direct
        public ?string $medium = null,
        public ?string $campaign = null,
        public ?string $content = null,
        public ?string $term = null,
    ) {}

    public static function direct(): self
    {
        return new self('direct', null);
    }

    /** sha256(type|source|medium|campaign|content|term) */
    public function key(): string
    {
        return hash('sha256', implode('|', [$this->type, $this->source ?? '', $this->medium ?? '', $this->campaign ?? '', $this->content ?? '', $this->term ?? '']));
    }
}
