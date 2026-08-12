<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class LocalizedName
{
    /**
     * Ensure at least Greek or English is present when creating/updating a name map.
     *
     * @param  array<string, mixed>  $name
     */
    public static function assertPrimaryLocale(array $name, bool $required = true): void
    {
        if (! $required) {
            return;
        }

        $el = trim((string) ($name['el'] ?? ''));
        $en = trim((string) ($name['en'] ?? ''));

        if ($el === '' && $en === '') {
            throw ValidationException::withMessages([
                'name' => ['Provide at least a Greek (el) or English (en) name.'],
            ]);
        }
    }

    /**
     * Prefer EL/EN for slugs, then any other filled locale.
     *
     * @param  array<string, mixed>  $name
     */
    public static function slugSource(array $name): string
    {
        foreach (['el', 'en', 'it', 'de'] as $locale) {
            $value = trim((string) ($name[$locale] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
