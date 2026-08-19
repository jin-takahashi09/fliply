<?php

use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the study settings with word counts', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご']);
    Word::create(['english' => 'resilient', 'japanese' => '回復力のある', 'is_hard' => true]);

    $this->get(route('study.settings'))
        ->assertSuccessful()
        ->assertSee('すべての単語')
        ->assertSee('難しい単語だけ')
        ->assertSee('2語のカード')
        ->assertSee('1語を集中して復習');
});

it('shows every word in an all-word study session', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご']);
    Word::create(['english' => 'resilient', 'japanese' => '回復力のある', 'is_hard' => true]);

    $this->get(route('study.session', ['scope' => 'all']))
        ->assertSuccessful()
        ->assertSee('apple')
        ->assertSee('resilient');
});

it('uses only hard words in a hard-word study session', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご']);
    Word::create(['english' => 'resilient', 'japanese' => '回復力のある', 'is_hard' => true]);

    $this->get(route('study.session', ['scope' => 'hard']))
        ->assertSuccessful()
        ->assertSee('resilient')
        ->assertDontSee('apple');
});