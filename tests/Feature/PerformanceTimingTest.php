<?php

use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function assertPerformanceTimingHeader(string $header): void
{
    expect($header)
        ->toContain('app_total;dur=')
        ->and($header)->toContain('db_queries;dur=')
        ->and($header)->toContain('db_query_count;desc=')
        ->and($header)->not->toMatch('/password|APP_KEY|secret|api_key|cookie/i');
}

it('adds Server-Timing on words when perf=1', function () {
    Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $response = $this->get('/words?perf=1');

    $response->assertSuccessful();
    $response->assertHeader('Server-Timing');

    $timing = (string) $response->headers->get('Server-Timing');
    assertPerformanceTimingHeader($timing);
    expect($timing)->toMatch('/db_query_count;desc="[1-9]/');
});

it('adds Server-Timing on home when perf=1', function () {
    Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $response = $this->get('/?perf=1');

    $response->assertSuccessful();
    $response->assertHeader('Server-Timing');

    assertPerformanceTimingHeader((string) $response->headers->get('Server-Timing'));
});

it('adds Server-Timing on study settings when perf=1', function () {
    $response = $this->get('/study?perf=1');

    $response->assertSuccessful();
    $response->assertHeader('Server-Timing');

    assertPerformanceTimingHeader((string) $response->headers->get('Server-Timing'));
});

it('does not add Server-Timing on dictionary when perf=1 without forcing db access', function () {
    $response = $this->get('/dictionary?perf=1');

    $response->assertSuccessful();
    $response->assertHeader('Server-Timing');

    $timing = (string) $response->headers->get('Server-Timing');
    assertPerformanceTimingHeader($timing);
    expect($timing)->toContain('db_query_count;desc="0"')
        ->and($timing)->not->toContain('db_ready');
});

it('does not add Server-Timing without perf query parameter', function () {
    Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $response = $this->get('/words');

    $response->assertSuccessful();
    expect($response->headers->has('Server-Timing'))->toBeFalse();
});

it('does not add Server-Timing when perf is not exactly 1', function () {
    $response = $this->get('/words?perf=true');

    $response->assertSuccessful();
    expect($response->headers->has('Server-Timing'))->toBeFalse();
});
