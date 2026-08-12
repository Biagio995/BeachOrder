<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoTranslator
{
    public function enabled(): bool
    {
        $driver = config('translation.driver');

        return (bool) config('translation.enabled')
            && $driver
            && $driver !== 'null';
    }

    /**
     * Translate $text from $from to $to. Returns null on failure / disabled.
     */
    public function translate(string $text, string $from, string $to): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if ($from === $to) {
            return $text;
        }
        if (! $this->enabled()) {
            return null;
        }

        $cacheKey = 'bo_tr:'.sha1((string) config('translation.driver')."|{$from}|{$to}|{$text}");
        $ttl = (int) config('translation.cache_ttl', 2592000);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $translated = match (config('translation.driver')) {
            'mymemory' => $this->viaMyMemory($text, $from, $to),
            default => null,
        };

        if (is_string($translated) && $translated !== '') {
            Cache::put($cacheKey, $translated, $ttl);
        }

        return $translated;
    }

    private function viaMyMemory(string $text, string $from, string $to): ?string
    {
        try {
            $query = [
                'q' => $text,
                'langpair' => "{$from}|{$to}",
            ];
            $email = config('translation.mymemory.email');
            if ($email) {
                $query['de'] = $email;
            }

            $response = Http::timeout((int) config('translation.mymemory.timeout', 8))
                ->acceptJson()
                ->get((string) config('translation.mymemory.endpoint'), $query);

            if (! $response->successful()) {
                Log::warning('AutoTranslator MyMemory HTTP error', [
                    'status' => $response->status(),
                    'from' => $from,
                    'to' => $to,
                ]);

                return null;
            }

            $translated = trim((string) data_get($response->json(), 'responseData.translatedText', ''));
            if ($translated === '' || str_contains(mb_strtolower($translated), 'query length limit')) {
                return null;
            }

            if (str_starts_with(mb_strtoupper($translated), 'INVALID')) {
                return null;
            }

            $decoded = html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // MyMemory sometimes returns the Greek source unchanged for el→it/de.
            if ($to !== 'el' && $from === 'el' && (bool) preg_match('/\p{Greek}/u', $decoded)) {
                return null;
            }

            return $decoded;
        } catch (\Throwable $e) {
            Log::warning('AutoTranslator MyMemory failed: '.$e->getMessage());

            return null;
        }
    }
}
