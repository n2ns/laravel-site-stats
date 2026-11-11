<?php

namespace N2ns\Stats\Recording;

/** A viewed page: its normalised path and what the page says about itself. */
final readonly class Page
{
    public function __construct(
        public string $path,
        public ?string $title = null,
        public ?string $type = null,
        /** Key shared by the language versions of one piece of content (e.g. "post-42"). */
        public ?string $group = null,
        /** The site's language version the page belongs to; null when the site has no locales. */
        public ?string $locale = null,
    ) {}
}
