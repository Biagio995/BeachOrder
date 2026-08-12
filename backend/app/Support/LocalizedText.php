<?php

namespace App\Support;

use App\Services\AutoTranslator;
use Illuminate\Database\Eloquent\Model;

class LocalizedText
{
    /**
     * Resolve a localized string from a JSON map.
     *
     * Primary locales (el/en) are read from the stored map when present.
     * Secondary locales (it/de) are machine-translated preferentially from English
     * (better MT quality), then Greek. If MT fails, fall back to a stored value for
     * that locale (e.g. seeder), then English, then any other available text —
     * never leave an Italian/German UI stuck on Greek when English exists.
     *
     * @param  array<string, mixed>|null  $map
     * @return array{0: string|null, 1: array<string, mixed>|null} [resolved, updatedMapOrNull]
     */
    public static function resolve(?array $map, string $locale, bool $autoTranslate = true): array
    {
        $map = is_array($map) ? $map : [];
        $locale = strtolower(substr($locale, 0, 2));
        $supported = config('translation.supported_locales', ['it', 'en', 'el', 'de']);
        $primary = config('translation.primary_locales', ['el', 'en']);

        // Stored JSON is authoritative for admin-maintained primary locales.
        if (in_array($locale, $primary, true)) {
            $existing = trim((string) ($map[$locale] ?? ''));
            if ($existing !== '') {
                return [$existing, null];
            }
        }

        $storedTarget = trim((string) ($map[$locale] ?? ''));

        if ($autoTranslate && in_array($locale, $supported, true)) {
            foreach (self::translationSources($map, $locale) as [$sourceLocale, $source]) {
                if ($sourceLocale === $locale || $source === '') {
                    continue;
                }

                $translated = app(AutoTranslator::class)->translate($source, $sourceLocale, $locale);
                if (self::isUsableTranslation($translated, $source, $sourceLocale, $locale)) {
                    // Do not persist secondary locales — keep DB = primary (+ optional seeds).
                    return [$translated, null];
                }
            }
        }

        // Prefer curated/stored target locale (seed or previous fill) over Greek fallback.
        if ($storedTarget !== '') {
            return [$storedTarget, null];
        }

        $fallback = self::displayFallback($map, $locale);
        if ($fallback !== null) {
            return [$fallback, null];
        }

        return [null, null];
    }

    /**
     * Resolve a locale for a model attribute.
     *
     * Secondary locales are not written back to the model (dynamic only).
     */
    public static function resolveOn(Model $model, string $attribute, string $locale, bool $persist = true): ?string
    {
        $map = $model->{$attribute};
        if (! is_array($map)) {
            return is_string($map) ? $map : null;
        }

        [$value, $updated] = self::resolve($map, $locale, true);

        $primary = config('translation.primary_locales', ['el', 'en']);
        $locale = strtolower(substr($locale, 0, 2));
        if ($persist && $updated !== null && in_array($locale, $primary, true)) {
            $model->{$attribute} = $updated;
            $model->saveQuietly();
        }

        return $value;
    }

    /**
     * Keep only admin-maintained primary locales from a stored map.
     *
     * @param  array<string, mixed>|null  $map
     * @return array<string, string>
     */
    public static function primaryOnly(?array $map): array
    {
        $map = is_array($map) ? $map : [];
        $out = [];
        foreach (config('translation.primary_locales', ['el', 'en']) as $code) {
            $text = trim((string) ($map[$code] ?? ''));
            if ($text !== '') {
                $out[$code] = (string) $map[$code];
            }
        }

        return $out;
    }

    /**
     * Mutate the in-memory attribute for API JSON: primary locales + dynamically resolved current locale.
     * Does not persist secondary locales to the database.
     */
    public static function applyResolvedForResponse(Model $model, string $attribute, string $locale): ?string
    {
        $map = is_array($model->{$attribute}) ? $model->{$attribute} : [];
        $resolved = self::resolveOn($model, $attribute, $locale, false);
        $out = self::primaryOnly($map);
        $locale = strtolower(substr($locale, 0, 2));
        if ($resolved !== null && trim($resolved) !== '') {
            $out[$locale] = $resolved;
        }
        $model->setAttribute($attribute, $out);

        return $resolved;
    }

    /**
     * Warm AutoTranslator cache for every supported non-primary locale.
     * Does not write secondary translations into the JSON column.
     */
    public static function fillMissingLocales(Model $model, string $attribute): void
    {
        if (! app(AutoTranslator::class)->enabled()) {
            return;
        }

        $map = $model->{$attribute};
        if (! is_array($map)) {
            return;
        }

        $primary = config('translation.primary_locales', ['el', 'en']);

        foreach (config('translation.supported_locales', []) as $locale) {
            if (in_array($locale, $primary, true)) {
                continue;
            }

            foreach (self::translationSources($map, $locale) as [$sourceLocale, $source]) {
                if ($sourceLocale === $locale || $source === '') {
                    continue;
                }
                app(AutoTranslator::class)->translate($source, $sourceLocale, $locale);
                break;
            }
        }
    }

    /**
     * Ordered MT sources. Prefer English when targeting IT/DE/EN (better MyMemory quality).
     *
     * @param  array<string, mixed>  $map
     * @return list<array{0: string, 1: string}>
     */
    private static function translationSources(array $map, string $targetLocale): array
    {
        $order = in_array($targetLocale, ['it', 'de', 'en'], true)
            ? ['en', 'el', 'it', 'de']
            : ['el', 'en', 'it', 'de'];

        $sources = [];
        foreach ($order as $code) {
            if ($code === $targetLocale) {
                continue;
            }
            $text = trim((string) ($map[$code] ?? ''));
            if ($text !== '') {
                $sources[] = [$code, $text];
            }
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private static function displayFallback(array $map, string $locale): ?string
    {
        // Prefer Latin/English before Greek when the user asked for IT/DE.
        $order = in_array($locale, ['it', 'de'], true)
            ? ['en', 'it', 'de', 'el']
            : ['el', 'en', 'it', 'de'];

        foreach ($order as $code) {
            if ($code === $locale) {
                continue;
            }
            $text = trim((string) ($map[$code] ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        foreach ($map as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private static function isUsableTranslation(?string $translated, string $source, string $from, string $to): bool
    {
        if ($translated === null) {
            return false;
        }

        $translated = trim($translated);
        if ($translated === '') {
            return false;
        }

        // Reject "translations" that are still Greek when the target is not Greek.
        if ($to !== 'el' && self::looksGreek($translated) && $from === 'el') {
            return false;
        }

        return true;
    }

    private static function looksGreek(string $text): bool
    {
        return (bool) preg_match('/\p{Greek}/u', $text);
    }
}
