<?php

namespace N2ns\Stats\Support;

class PathNormalizer
{
    /** Path of a URL or path without query/fragment; null if invalid (design §5.2 step 5). Max 512 chars. */
    public function normalize(string $urlOrPath): ?string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $urlOrPath) === 1) {
            $parts = parse_url($urlOrPath);
            if ($parts === false) {
                return null;
            }
            $path = $parts['path'] ?? '/';
            $path = $path === '' ? '/' : $path;
        } else {
            $path = preg_split('/[?#]/', $urlOrPath, 2)[0] ?? '';
        }

        if ($path === ''
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || strlen($path) > 512
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || ! mb_check_encoding($path, 'UTF-8')) {
            return null;
        }

        return $path;
    }

    /** External URL reduced to scheme://host/path (no credentials, query, fragment), max 2048; null if invalid. */
    public function cleanUrl(string $url): ?string
    {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $clean = $scheme.'://'.$host.$port.($parts['path'] ?? '');

        return strlen($clean) <= 2048 ? $clean : null;
    }
}
