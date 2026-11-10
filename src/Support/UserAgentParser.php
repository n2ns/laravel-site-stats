<?php

namespace N2ns\Stats\Support;

class UserAgentParser
{
    /**
     * @return array{device: 'desktop'|'mobile'|'tablet'|'unknown', browser: string, os: string}
     *                                                                                           browser e.g. Chrome, Safari, Firefox, Edge, Opera, Samsung Internet, Other; os e.g. Windows, macOS, iOS, Android, Linux, ChromeOS, Other
     */
    public function parse(?string $userAgent, ?int $screenWidth = null): array
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return ['device' => 'unknown', 'browser' => 'Other', 'os' => 'Other'];
        }

        $os = $this->os($ua);

        return ['device' => $this->device($ua, $os, $screenWidth), 'browser' => $this->browser($ua), 'os' => $os];
    }

    private function browser(string $ua): string
    {
        return match (true) {
            preg_match('#\b(Edg|Edge|EdgA|EdgiOS)/#', $ua) === 1 => 'Edge',
            preg_match('#\b(OPR|OPiOS|OPT)/|\bOpera\b#', $ua) === 1 => 'Opera',
            preg_match('#\bSamsungBrowser/#', $ua) === 1 => 'Samsung Internet',
            preg_match('#\b(Chrome|CriOS|Chromium)/#', $ua) === 1 => 'Chrome',
            preg_match('#\b(Firefox|FxiOS)/#', $ua) === 1 => 'Firefox',
            preg_match('#\bSafari/#', $ua) === 1 && preg_match('#\b(Version/|iPhone|iPad|iPod)#', $ua) === 1 => 'Safari',
            default => 'Other',
        };
    }

    private function os(string $ua): string
    {
        return match (true) {
            preg_match('#\bWindows\b#', $ua) === 1 => 'Windows',
            preg_match('#\b(iPhone|iPad|iPod)\b#', $ua) === 1 => 'iOS',
            preg_match('#\bAndroid\b#', $ua) === 1 => 'Android',
            preg_match('#\bCrOS\b#', $ua) === 1 => 'ChromeOS',
            preg_match('#\b(Macintosh|Mac OS X)\b#', $ua) === 1 => 'macOS',
            preg_match('#\bLinux\b#', $ua) === 1 => 'Linux',
            default => 'Other',
        };
    }

    /** @return 'desktop'|'mobile'|'tablet' */
    private function device(string $ua, string $os, ?int $width): string
    {
        if ($os === 'iOS' || $os === 'Android') {
            if (str_contains($ua, 'iPad') || ($os === 'Android' && ! str_contains($ua, 'Mobile'))) {
                return 'tablet';
            }

            return $width !== null && $width >= 600 && $width <= 1100 ? 'tablet' : 'mobile';
        }

        return preg_match('#\bMobi#', $ua) === 1 ? 'mobile' : 'desktop';
    }
}
