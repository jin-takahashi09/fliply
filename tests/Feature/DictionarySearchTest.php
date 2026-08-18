<?php

use App\Services\DatamuseClient;
use Illuminate\Support\Facades\Http;

it('can search with a single letter', function () {
    Http::fake([
        'api.datamuse.com/*' => Http::response([
            ['word' => 'able'],
            ['word' => 'about'],
            ['word' => 'apple'],
        ], 200),
    ]);

    $response = $this->getJson('/dictionary/suggestions?q=a');

    $response->assertSuccessful()
        ->assertJsonPath('message', null)
        ->assertJsonPath('words', ['able', 'about', 'apple']);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.datamuse.com/words')
            && $request['sp'] === 'a*'
            && (int) $request['max'] === 200;
    });
});

it('returns words that start with the given prefix', function () {
    Http::fake([
        'api.datamuse.com/*' => Http::response([
            ['word' => 'apple'],
            ['word' => 'application'],
            ['word' => 'banana'],
        ], 200),
    ]);

    $response = $this->getJson('/dictionary/suggestions?q=ap');

    $response->assertSuccessful()
        ->assertJsonPath('words', ['apple', 'application']);
});

it('excludes multi-word phrases that contain spaces', function () {
    Http::fake([
        'api.datamuse.com/*' => Http::response([
            ['word' => 'apple'],
            ['word' => 'apple of discord'],
        ], 200),
    ]);

    $response = $this->getJson('/dictionary/suggestions?q=apple');

    $response->assertSuccessful()
        ->assertJsonPath('words', ['apple']);
});

it('removes duplicate suggestions', function () {
    Http::fake([
        'api.datamuse.com/*' => Http::response([
            ['word' => 'apple'],
            ['word' => 'apple'],
            ['word' => 'apply'],
        ], 200),
    ]);

    $response = $this->getJson('/dictionary/suggestions?q=ap');

    $response->assertSuccessful()
        ->assertJsonPath('words', ['apple', 'apply']);
});

it('does not return more than the display limit', function () {
    $payload = collect(range(1, 80))
        ->map(fn (int $number) => ['word' => 'a'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)])
        ->all();

    Http::fake([
        'api.datamuse.com/*' => Http::response($payload, 200),
    ]);

    $response = $this->getJson('/dictionary/suggestions?q=a');

    $response->assertSuccessful();

    expect($response->json('words'))->toHaveCount(DatamuseClient::DISPLAY_LIMIT);
});

it('does not call Datamuse when the query is empty', function () {
    Http::fake();

    $response = $this->getJson('/dictionary/suggestions?q=');

    $response->assertSuccessful()
        ->assertJsonPath('words', [])
        ->assertJsonPath('message', null);

    Http::assertNothingSent();
});

it('returns a user-facing message when Datamuse fails', function () {
    Http::fake([
        'api.datamuse.com/*' => Http::response('error', 500),
    ]);

    $response = $this->getJson('/dictionary/suggestions?q=ap');

    $response->assertSuccessful()
        ->assertJsonPath('words', [])
        ->assertJsonPath('message', '単語を取得できませんでした');
});
