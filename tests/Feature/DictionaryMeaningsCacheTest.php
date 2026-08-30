<?php

use App\Models\Word;
use App\Services\DeepLClient;
use App\Services\DictionaryMeaningsService;
use App\Services\WiktionaryClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->toBe('dictionary:meanings:v3:canvas');
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

    // Each attempt: en + ja + DeepL.
    Http::assertSentCount(6);
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

it('uses cache version v3 and ignores stale v1 cache entries', function () {
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
        ->toBe('dictionary:meanings:v3:canvas');

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
        ->toBe('dictionary:meanings:v3:wabbit');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'deepl.com'));
});

it('uses stale v2 cache miss to store english fallback in v3 when DeepL returns English', function () {
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

it('uses valid english fallback v3 cache without refetching', function () {
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

it('uses valid Japanese v3 cache without refetching', function () {
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
