<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class DeepLClient
{
    private const ENDPOINT = 'https://api-free.deepl.com/v2/translate';

    /**
     * Translate a single English word/phrase to Japanese.
     *
     * @return array{translation: string|null, ok: bool}
     */
    public function translate(string $text): array
    {
        $key = config('services.deepl.key');

        if (! is_string($key) || $key === '') {
            return ['translation' => null, 'ok' => false];
        }

        $text = trim($text);

        if ($text === '') {
            return ['translation' => null, 'ok' => false];
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Authorization' => 'DeepL-Auth-Key '.$key])
                ->post(self::ENDPOINT, [
                    'text' => [$text],
                    'source_lang' => 'EN',
                    'target_lang' => 'JA',
                ]);
        } catch (ConnectionException|RequestException) {
            return ['translation' => null, 'ok' => false];
        }

        if ($response->failed()) {
            return ['translation' => null, 'ok' => false];
        }

        $translation = $response->json('translations.0.text');

        if (! is_string($translation) || trim($translation) === '') {
            return ['translation' => null, 'ok' => false];
        }

        return ['translation' => trim($translation), 'ok' => true];
    }
}
