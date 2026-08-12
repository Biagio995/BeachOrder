<?php

namespace App\Models\Concerns;

use App\Support\LocalizedText;

trait HasTranslatedName
{
    public function translatedName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        return LocalizedText::resolveOn($this, 'name', $locale, false) ?? '';
    }
}
