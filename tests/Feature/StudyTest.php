<?php

use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = actingAsUser();
});

it('shows the study settings with word counts', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);
    Word::factory()->for($this->user)->create(['english' => 'resilient', 'japanese' => '回復力のある', 'is_hard' => true]);

    $this->get(route('study.settings'))
        ->assertSuccessful()
        ->assertSee('すべての単語')
        ->assertSee('難しい単語だけ')
        ->assertSee('2語のカード')
        ->assertSee('1語を集中して復習');
});

it('shows every word in an all-word study session', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);
    Word::factory()->for($this->user)->create(['english' => 'resilient', 'japanese' => '回復力のある', 'is_hard' => true]);

    $this->get(route('study.session', ['scope' => 'all']))
        ->assertSuccessful()
        ->assertSee('apple')
        ->assertSee('resilient');
});

it('uses only hard words in a hard-word study session', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);
    Word::factory()->for($this->user)->create(['english' => 'resilient', 'japanese' => '回復力のある', 'is_hard' => true]);

    $this->get(route('study.session', ['scope' => 'hard']))
        ->assertSuccessful()
        ->assertSee('resilient')
        ->assertDontSee('apple');
});

it('renders flashcard study controls and completion placeholders', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);

    $this->get(route('study.session'))
        ->assertSuccessful()
        ->assertSee('data-study-stack', false)
        ->assertSee('data-stack-layer', false)
        ->assertSee('data-answer="incorrect"', false)
        ->assertSee('data-answer="correct"', false)
        ->assertSee('data-study-actions', false)
        ->assertSee('aria-hidden="true"', false)
        ->assertSee('不正解')
        ->assertSee('正解')
        ->assertSee('data-incorrect-list', false)
        ->assertSee('data-perfect-message', false)
        ->assertSee('今回間違えた単語');
});

it('embeds study words without changing is_hard in the database', function () {
    $word = Word::factory()->for($this->user)->create([
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => false,
    ]);

    $this->get(route('study.session'))
        ->assertSuccessful()
        ->assertSee('"english":"apple"', false)
        ->assertSee('"japanese":"りんご"', false);

    expect($word->fresh()->is_hard)->toBeFalse();
});

it('starts with english as the first question when direction is en-ja', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);

    $this->get(route('study.session', ['direction' => 'en-ja']))
        ->assertSuccessful()
        ->assertSee('data-direction="en-ja"', false)
        ->assertSee('data-first-question="apple"', false);
});

it('starts with japanese as the first question when direction is ja-en', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);

    $this->get(route('study.session', ['direction' => 'ja-en']))
        ->assertSuccessful()
        ->assertSee('data-direction="ja-en"', false)
        ->assertSee('data-first-question="りんご"', false);
});

it('supports japanese to english study direction in the session payload', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);

    $this->get(route('study.session', ['direction' => 'ja-en']))
        ->assertSuccessful()
        ->assertSee('data-direction="ja-en"', false)
        ->assertSee('りんご')
        ->assertSee('apple');
});
