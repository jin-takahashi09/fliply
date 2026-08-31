<?php

use App\Models\Word;
use App\Services\DeepLClient;
use App\Services\DictionaryMeaningsService;
use App\Services\WiktionaryClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->user = actingAsUser();
});

function cacheTestCanvasWikitext(): string
{
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|type of coarse cloth}}
* Japanese: {{t+|ja|帆布|tr=hanpu}}, {{t+|ja|キャンバス|tr=kyanbasu}}, {{t+|ja|ズック|tr=zukku}}
{{trans-bottom}}
{{trans-top|piece of canvas cloth on which one may paint}}
* Japanese: {{t+|ja|画布|tr=gafu}}, {{t+|ja|キャンバス|tr=kyanbasu}}, {{t+|ja|カンバス|tr=kanbasu}}
{{trans-bottom}}
WIKITEXT;
}

function cacheTestWiktionaryResponse(string $wikitext, string $title = 'test'): array
{
    return [
        'parse' => [
            'title' => $title,
            'pageid' => 1,
            'wikitext' => ['*' => $wikitext],
        ],
    ];
}

function cacheTestWiktionaryNotFound(): array
{
    return [
        'error' => [
            'code' => 'missingtitle',
            'info' => "The page you specified doesn't exist.",
        ],
    ];
}

function cacheTestDeeplResponse(string $translation): array
{
    return [
        'translations' => [
            ['detected_source_language' => 'EN', 'text' => $translation],
        ],
    ];
}

function fakeCanvasWiktionary(): void
{
    Http::fake([
        'en.wiktionary.org/*' => Http::response(cacheTestWiktionaryResponse(cacheTestCanvasWikitext(), 'canvas'), 200),
        'ja.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
    ]);
}

it('calls Wiktionary on first canvas request when cache is empty', function () {
    fakeCanvasWiktionary();

    $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'en.wiktionary.org'));
});

it('returns cached canvas meanings on second request without calling Wiktionary again', function () {
    fakeCanvasWiktionary();

    $first = $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();
    $second = $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();

    expect($second->json('candidates'))->toEqual($first->json('candidates'));

    // First request: en + ja in parallel; second hits cache only.
    Http::assertSentCount(2);
});

it('uses the same cache key for Canvas canvas and CANVAS', function () {
    fakeCanvasWiktionary();

    $this->getJson('/dictionary/meanings?word=Canvas')->assertSuccessful();
    $this->getJson('/dictionary/meanings?word=CANVAS')->assertSuccessful();

    Http::assertSentCount(2);
    expect(DictionaryMeaningsService::cacheKeyFor('Canvas'))
        ->toBe('dictionary:meanings:v5:canvas');
});

it('caches DeepL fallback results and skips both APIs on second request', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
        'ja.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
        'api-free.deepl.com/*' => Http::response(cacheTestDeeplResponse('アップルソース'), 200),
    ]);

    $first = $this->getJson('/dictionary/meanings?word=applesauce')->assertSuccessful();
    $second = $this->getJson('/dictionary/meanings?word=applesauce')->assertSuccessful();

    expect($first->json('candidates.0.japanese'))->toBe('アップルソース');
    expect($second->json('candidates.0.japanese'))->toBe('アップルソース');

    // First request: en + ja (parallel) + DeepL; second hits cache.
    Http::assertSentCount(3);
});

it('does not cache temporary API failures and retries on the next request', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response('error', 500),
        'ja.wiktionary.org/*' => Http::response('error', 500),
        'api-free.deepl.com/*' => Http::response('error', 500),
    ]);

    $first = $this->getJson('/dictionary/meanings?word=applesauce')->assertSuccessful();
    $second = $this->getJson('/dictionary/meanings?word=applesauce')->assertSuccessful();

    expect($first->json('candidates.0.japanese'))->toBe('applesauce');
    expect($second->json('candidates.0.japanese'))->toBe('applesauce');
    expect(Cache::has(DictionaryMeaningsService::cacheKeyFor('applesauce')))->toBeFalse();

    // Each request: (en+ja) + 1 retry (en+ja) + DeepL.
    Http::assertSentCount(10);
});

it('reflects current registration status even when meanings are cached', function () {
    fakeCanvasWiktionary();

    $before = $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();
    expect($before->json('candidates.0.registered'))->toBeFalse();

    Word::factory()->for($this->user)->create([
        'english' => 'canvas',
        'japanese' => '帆布、ズック',
        'is_hard' => false,
    ]);

    $after = $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();
    expect($after->json('candidates.0.registered'))->toBeTrue();
    expect($after->json('candidates.0.word_id'))->not->toBeNull();

    Http::assertSentCount(2);
});

it('stores only meaning candidates in cache without registration fields', function () {
    fakeCanvasWiktionary();

    $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();

    $cached = Cache::get(DictionaryMeaningsService::cacheKeyFor('canvas'));

    expect($cached)->toBeArray();
    expect($cached['candidates'][0])->toEqual([
        'topic' => 'type of coarse cloth',
        'japanese' => '帆布、ズック',
    ]);
    expect($cached['candidates'][0])->not->toHaveKey('registered');
    expect($cached['candidates'][0])->not->toHaveKey('word_id');
});

it('caches canvas meaning separation after duplicate cleanup', function () {
    fakeCanvasWiktionary();

    $response = $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toHaveCount(2)
        ->and($japaneseValues)->toContain('帆布、ズック')
        ->and($japaneseValues)->toContain('画布、キャンバス');

    $cached = Cache::get(DictionaryMeaningsService::cacheKeyFor('canvas'));
    expect(collect($cached['candidates'])->pluck('japanese')->all())->toEqual($japaneseValues);
});

it('can be cleared with php artisan cache:clear', function () {
    fakeCanvasWiktionary();

    $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();
    expect(Cache::has(DictionaryMeaningsService::cacheKeyFor('canvas')))->toBeTrue();

    Artisan::call('cache:clear');

    expect(Cache::has(DictionaryMeaningsService::cacheKeyFor('canvas')))->toBeFalse();
});

it('uses WiktionaryClient mock only once across repeated canvas lookups', function () {
    $this->mock(WiktionaryClient::class, function ($mock) {
        $mock->shouldReceive('meanings')
            ->once()
            ->with('canvas')
            ->andReturn([
                'groups' => [
                    [
                        'topic' => 'type of coarse cloth',
                        'candidates' => ['帆布', 'ズック'],
                        'part_of_speech' => 'Noun',
                        'etymology_id' => 0,
                        'source_order' => 0,
                        'labels' => [],
                        'is_derived' => false,
                        'is_special' => false,
                    ],
                    [
                        'topic' => 'piece of canvas cloth on which one may paint',
                        'candidates' => ['画布', 'キャンバス'],
                        'part_of_speech' => 'Noun',
                        'etymology_id' => 0,
                        'source_order' => 1,
                        'labels' => [],
                        'is_derived' => false,
                        'is_special' => false,
                    ],
                ],
                'ok' => true,
            ]);
    });

    $this->mock(DeepLClient::class, function ($mock) {
        $mock->shouldNotReceive('translate');
    });

    $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();
    $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();
});

it('uses DeepL mock only once across repeated applesauce lookups', function () {
    $this->mock(WiktionaryClient::class, function ($mock) {
        $mock->shouldReceive('meanings')
            ->once()
            ->with('applesauce')
            ->andReturn(['groups' => [], 'ok' => true]);
    });

    $this->mock(DeepLClient::class, function ($mock) {
        $mock->shouldReceive('translate')
            ->once()
            ->with('applesauce')
            ->andReturn(['ok' => true, 'translation' => 'アップルソース']);
    });

    $first = $this->getJson('/dictionary/meanings?word=applesauce')->assertSuccessful();
    $second = $this->getJson('/dictionary/meanings?word=applesauce')->assertSuccessful();

    expect($first->json('candidates.0.japanese'))->toBe('アップルソース');
    expect($second->json('candidates.0.japanese'))->toBe('アップルソース');
});

it('caches meanings for seven days', function () {
    Cache::spy();

    fakeCanvasWiktionary();
    $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();

    Cache::shouldHaveReceived('put')
        ->once()
        ->with(
            DictionaryMeaningsService::cacheKeyFor('canvas'),
            Mockery::type('array'),
            DictionaryMeaningsService::CACHE_TTL_SECONDS
        );
});

it('uses cache version v5 and ignores stale v1 cache entries', function () {
    fakeCanvasWiktionary();

    Cache::put('dictionary:meanings:v1:canvas', [
        'english' => 'canvas',
        'candidates' => [
            ['topic' => 'stale', 'japanese' => '古いキャッシュ'],
        ],
        'message' => null,
    ], 3600);

    $response = $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->not->toContain('古いキャッシュ')
        ->and(DictionaryMeaningsService::cacheKeyFor('canvas'))
        ->toBe('dictionary:meanings:v5:canvas');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'en.wiktionary.org'));
});

it('ignores stale v2 cache with English-only wabbit meaning and refetches', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
        'ja.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
        'api-free.deepl.com/*' => Http::response(cacheTestDeeplResponse('ウサギ'), 200),
    ]);

    Cache::put('dictionary:meanings:v2:wabbit', [
        'english' => 'wabbit',
        'candidates' => [
            ['topic' => '', 'japanese' => 'wabbit (wabit)'],
        ],
        'message' => null,
    ], 3600);

    $response = $this->getJson('/dictionary/meanings?word=wabbit')->assertSuccessful();
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toContain('ウサギ')
        ->and($japaneseValues)->not->toContain('wabbit (wabit)')
        ->and($japaneseValues)->not->toContain('wabbit')
        ->and(DictionaryMeaningsService::cacheKeyFor('wabbit'))
        ->toBe('dictionary:meanings:v5:wabbit');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'deepl.com'));
});

it('ignores stale v3 cache and refetches with v5 key', function () {
    fakeCanvasWiktionary();

    Cache::put('dictionary:meanings:v3:canvas', [
        'english' => 'canvas',
        'candidates' => [
            ['topic' => 'stale', 'japanese' => '古いv3キャッシュ'],
        ],
        'message' => null,
    ], 3600);

    $response = $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->not->toContain('古いv3キャッシュ')
        ->and(DictionaryMeaningsService::cacheKeyFor('canvas'))
        ->toBe('dictionary:meanings:v5:canvas');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'en.wiktionary.org'));
});

it('ignores stale v4 cache and refetches with v5 key', function () {
    fakeCanvasWiktionary();

    Cache::put('dictionary:meanings:v4:canvas', [
        'english' => 'canvas',
        'candidates' => [
            ['topic' => 'stale', 'japanese' => '古いv4キャッシュ'],
        ],
        'message' => null,
    ], 3600);

    $response = $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->not->toContain('古いv4キャッシュ')
        ->and(DictionaryMeaningsService::cacheKeyFor('canvas'))
        ->toBe('dictionary:meanings:v5:canvas');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'en.wiktionary.org'));
});

it('uses stale v2 cache miss to store english fallback in v5 when DeepL returns English', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
        'ja.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
        'api-free.deepl.com/*' => Http::response(cacheTestDeeplResponse('wabbit'), 200),
    ]);

    Cache::put('dictionary:meanings:v2:wabbit', [
        'english' => 'wabbit',
        'candidates' => [
            ['topic' => '', 'japanese' => 'wabbit (wabit)'],
        ],
        'message' => null,
    ], 3600);

    $response = $this->getJson('/dictionary/meanings?word=wabbit')->assertSuccessful();

    expect($response->json('candidates.0.japanese'))->toBe('wabbit')
        ->and($response->json('message'))->toBeNull()
        ->and(Cache::get(DictionaryMeaningsService::cacheKeyFor('wabbit')))
        ->toBe([
            'english' => 'wabbit',
            'candidates' => [
                ['topic' => '', 'japanese' => 'wabbit'],
            ],
            'message' => null,
        ]);
});

it('uses valid english fallback v5 cache without refetching', function () {
    Cache::put(DictionaryMeaningsService::cacheKeyFor('wabbit'), [
        'english' => 'wabbit',
        'candidates' => [
            ['topic' => '', 'japanese' => 'wabbit'],
        ],
        'message' => null,
    ], 3600);

    Http::fake();

    $response = $this->getJson('/dictionary/meanings?word=wabbit')->assertSuccessful();

    expect($response->json('candidates.0.japanese'))->toBe('wabbit');

    Http::assertNothingSent();
});

it('uses valid Japanese v5 cache without refetching', function () {
    Cache::put(DictionaryMeaningsService::cacheKeyFor('canvas'), [
        'english' => 'canvas',
        'candidates' => [
            ['topic' => 'type of coarse cloth', 'japanese' => '帆布、ズック'],
        ],
        'message' => null,
    ], 3600);

    Http::fake();

    $response = $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();

    expect(collect($response->json('candidates'))->pluck('japanese')->all())
        ->toBe(['帆布、ズック']);

    Http::assertNothingSent();
});

function recordedUrlCount(string $needle): int
{
    return collect(Http::recorded())
        ->filter(fn (array $pair) => str_contains($pair[0]->url(), $needle))
        ->count();
}

it('retries Wiktionary once after 429 and uses the successful retry', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::sequence()
            ->push('Too Many Requests', 429)
            ->push(cacheTestWiktionaryResponse(cacheTestCanvasWikitext(), 'canvas'), 200),
        'ja.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
        'api-free.deepl.com/*' => Http::response(cacheTestDeeplResponse('SHOULD_NOT_BE_USED'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=canvas')->assertSuccessful();
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toContain('帆布、ズック')
        ->and(recordedUrlCount('en.wiktionary.org'))->toBe(2)
        ->and(recordedUrlCount('deepl.com'))->toBe(0);

    expect(Cache::has(DictionaryMeaningsService::cacheKeyFor('canvas')))->toBeTrue();
});

it('falls back to DeepL after one failed Wiktionary retry', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response('Too Many Requests', 429),
        'ja.wiktionary.org/*' => Http::response('Too Many Requests', 429),
        'api-free.deepl.com/*' => Http::response(cacheTestDeeplResponse('クラウド'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=cloudword')->assertSuccessful();

    expect($response->json('candidates.0.japanese'))->toBe('クラウド')
        ->and(recordedUrlCount('en.wiktionary.org'))->toBe(2)
        ->and(recordedUrlCount('ja.wiktionary.org'))->toBe(2)
        ->and(recordedUrlCount('deepl.com'))->toBe(1);
});

it('retries Wiktionary once after a timeout then falls back to DeepL', function () {
    $wiktionaryCalls = 0;

    Http::fake(function ($request) use (&$wiktionaryCalls) {
        if (str_contains($request->url(), 'wiktionary.org')) {
            $wiktionaryCalls++;
            throw new ConnectionException('cURL error 28: Operation timed out after 4000 milliseconds');
        }

        return Http::response(cacheTestDeeplResponse('クラウド'), 200);
    });

    $response = $this->getJson('/dictionary/meanings?word=cloudword')->assertSuccessful();

    expect($response->json('candidates.0.japanese'))->toBe('クラウド')
        ->and($wiktionaryCalls)->toBe(4)
        ->and(recordedUrlCount('deepl.com'))->toBe(1);
});

it('does not retry Wiktionary more than once', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response('Too Many Requests', 429),
        'ja.wiktionary.org/*' => Http::response('Too Many Requests', 429),
        'api-free.deepl.com/*' => Http::response(cacheTestDeeplResponse('キャンディ'), 200),
    ]);

    $this->getJson('/dictionary/meanings?word=candyword')->assertSuccessful();

    expect(recordedUrlCount('en.wiktionary.org'))->toBe(2)
        ->and(recordedUrlCount('ja.wiktionary.org'))->toBe(2);
});

it('does not retry permanent HTTP 4xx client errors', function (int $status) {
    Http::fake([
        'en.wiktionary.org/*' => Http::response('Client Error', $status),
        'ja.wiktionary.org/*' => Http::response('Client Error', $status),
        'api-free.deepl.com/*' => Http::response(cacheTestDeeplResponse('フォールバック'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=clienterrorword')->assertSuccessful();

    expect($response->json('candidates.0.japanese'))->toBe('フォールバック')
        ->and(recordedUrlCount('en.wiktionary.org'))->toBe(1)
        ->and(recordedUrlCount('ja.wiktionary.org'))->toBe(1)
        ->and(recordedUrlCount('deepl.com'))->toBe(1);
})->with([400, 401, 403, 404]);

it('still retries HTTP 5xx as a transient failure', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response('Internal Server Error', 500),
        'ja.wiktionary.org/*' => Http::response('Internal Server Error', 500),
        'api-free.deepl.com/*' => Http::response(cacheTestDeeplResponse('フォールバック'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=servererrorword')->assertSuccessful();

    expect($response->json('candidates.0.japanese'))->toBe('フォールバック')
        ->and(recordedUrlCount('en.wiktionary.org'))->toBe(2)
        ->and(recordedUrlCount('ja.wiktionary.org'))->toBe(2);
});

it('does not retry MediaWiki missingtitle responses', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
        'ja.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
        'api-free.deepl.com/*' => Http::response(cacheTestDeeplResponse('アップルソース'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=applesauce')->assertSuccessful();

    expect($response->json('candidates.0.japanese'))->toBe('アップルソース')
        ->and(recordedUrlCount('en.wiktionary.org'))->toBe(1)
        ->and(recordedUrlCount('ja.wiktionary.org'))->toBe(1)
        ->and(recordedUrlCount('deepl.com'))->toBe(1);
});

it('caches DeepL-only results after Wiktionary 429 with a short TTL', function () {
    Cache::spy();

    Http::fake([
        'en.wiktionary.org/*' => Http::response('Too Many Requests', 429),
        'ja.wiktionary.org/*' => Http::response('Too Many Requests', 429),
        'api-free.deepl.com/*' => Http::response(cacheTestDeeplResponse('クラウド'), 200),
    ]);

    $this->getJson('/dictionary/meanings?word=cloudword')->assertSuccessful();

    Cache::shouldHaveReceived('put')
        ->once()
        ->with(
            DictionaryMeaningsService::cacheKeyFor('cloudword'),
            Mockery::type('array'),
            DictionaryMeaningsService::DEEPL_FALLBACK_CACHE_TTL_SECONDS
        );
});

it('caches genuine missing-title DeepL fallback with the long TTL', function () {
    Cache::spy();

    Http::fake([
        'en.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
        'ja.wiktionary.org/*' => Http::response(cacheTestWiktionaryNotFound(), 200),
        'api-free.deepl.com/*' => Http::response(cacheTestDeeplResponse('アップルソース'), 200),
    ]);

    $this->getJson('/dictionary/meanings?word=applesauce')->assertSuccessful();

    Cache::shouldHaveReceived('put')
        ->once()
        ->with(
            DictionaryMeaningsService::cacheKeyFor('applesauce'),
            Mockery::type('array'),
            DictionaryMeaningsService::CACHE_TTL_SECONDS
        );
});
