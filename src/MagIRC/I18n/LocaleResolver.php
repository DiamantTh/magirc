<?php

declare(strict_types=1);

namespace MagIRC\I18n;

final class LocaleResolver
{
    /** @param list<string> $available */
    public static function resolve(
        ?string $requested,
        ?string $cookie,
        string $acceptLanguage,
        array $available,
        string $fallback
    ): string {
        if ($requested !== null && in_array($requested, $available, true)) {
            return $requested;
        }
        if ($cookie !== null && in_array($cookie, $available, true)) {
            return $cookie;
        }

        $weighted = [];
        foreach (explode(',', $acceptLanguage) as $entry) {
            [$tag, $quality] = array_pad(explode(';', trim($entry), 2), 2, '');
            $tag = str_replace('-', '_', trim($tag));
            $q = 1.0;
            if ($quality !== '' && preg_match('/^\s*q\s*=\s*(0(?:\.\d{0,3})?|1(?:\.0{0,3})?)\s*$/i', $quality, $matches)) {
                $q = (float) $matches[1];
            }
            $weighted[] = [$tag, $q];
        }
        usort($weighted, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
        foreach ($weighted as [$tag, $quality]) {
            if ($quality <= 0) {
                continue;
            }
            foreach ($available as $locale) {
                if ($locale === $tag || str_starts_with($locale, $tag . '_')) {
                    return $locale;
                }
            }
        }

        return in_array($fallback, $available, true) ? $fallback : ($available[0] ?? 'en_US');
    }

    public static function activate(string $locale, string $catalogDirectory): void
    {
        $candidates = [$locale . '.UTF-8', $locale . '.utf8', $locale];
        setlocale(LC_ALL, ...$candidates);
        putenv('LC_MESSAGES=' . $locale . '.UTF-8');
        bindtextdomain('messages', $catalogDirectory);
        bind_textdomain_codeset('messages', 'UTF-8');
        textdomain('messages');
    }
}
