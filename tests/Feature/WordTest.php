<?php

use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can save a word with english and japanese', function () {
    $word = Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    expect($word->english)->toBe('apple')
        ->and($word->japanese)->toBe('りんご');

    $this->assertDatabaseHas('words', [
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);
});

it('defaults is_hard to false when not specified', function () {
    $word = Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    expect($word->is_hard)->toBeFalse()
        ->and($word->is_hard)->toBeBool();

    $this->assertDatabaseHas('words', [
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => false,
    ]);
});

it('casts is_hard to true when specified as true', function () {
    $word = Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => true,
    ]);

    expect($word->is_hard)->toBeTrue()
        ->and($word->is_hard)->toBeBool();

    $this->assertDatabaseHas('words', [
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => true,
    ]);
});
