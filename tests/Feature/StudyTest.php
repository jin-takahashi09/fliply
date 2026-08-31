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
        ->assertSee('1語を集中して復習')
        ->assertSee('学習方法')
        ->assertSee('カードをめくる')
        ->assertSee('入力して答える')
        ->assertSee('value="flip"', false)
        ->assertSee('value="input"', false)
        ->assertSee('name="method"', false)
        ->assertSee('checked', false);
});

it('defaults the study method to flip on the settings screen', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);

    $response = $this->get(route('study.settings'))->assertSuccessful();

    expect($response->getContent())
        ->toContain('name="method" value="flip" checked')
        ->not->toContain('name="method" value="input" checked');
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

it('renders input study controls when method is input', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);

    $this->get(route('study.session', ['method' => 'input']))
        ->assertSuccessful()
        ->assertSee('data-method="input"', false)
        ->assertSee('data-study-deck', false)
        ->assertSee('data-study-stack', false)
        ->assertSee('data-study-input-actions', false)
        ->assertSee('data-study-next', false)
        ->assertSee('data-answer-label="日本語で答える"', false)
        ->assertSee('入力学習')
        ->assertDontSee('data-study-actions', false)
        ->assertDontSee('data-study-input-result', false)
        ->assertDontSee('あなたの回答');
});

it('keeps flip study controls when method is flip', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);

    $this->get(route('study.session', ['method' => 'flip']))
        ->assertSuccessful()
        ->assertSee('data-method="flip"', false)
        ->assertSee('data-study-deck', false)
        ->assertSee('data-study-actions', false)
        ->assertDontSee('data-study-input-form', false);
});

it('shows english answer prompt for japanese to english input study', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);

    $this->get(route('study.session', [
        'method' => 'input',
        'direction' => 'ja-en',
    ]))
        ->assertSuccessful()
        ->assertSee('data-answer-label="英語で答える"', false)
        ->assertSee('data-first-question="りんご"', false);
});

it('shows japanese answer prompt for english to japanese input study', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);

    $this->get(route('study.session', [
        'method' => 'input',
        'direction' => 'en-ja',
    ]))
        ->assertSuccessful()
        ->assertSee('data-answer-label="日本語で答える"', false)
        ->assertSee('data-first-question="apple"', false);
});

it('embeds multiple japanese candidates in the study payload', function () {
    Word::factory()->for($this->user)->create([
        'english' => 'apple',
        'japanese' => '林檎、リンゴ、苹果',
    ]);

    $this->get(route('study.session', ['method' => 'input']))
        ->assertSuccessful()
        ->assertSee('"japanese":"林檎、リンゴ、苹果"', false);
});

it('uses the same word filtering for input study sessions', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);
    Word::factory()->for($this->user)->create(['english' => 'resilient', 'japanese' => '回復力のある', 'is_hard' => true]);

    $this->get(route('study.session', [
        'method' => 'input',
        'scope' => 'hard',
    ]))
        ->assertSuccessful()
        ->assertSee('resilient')
        ->assertDontSee('apple');
});

it('shows an empty state for input study when no words match the filter', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);

    $this->get(route('study.session', [
        'method' => 'input',
        'scope' => 'hard',
    ]))
        ->assertSuccessful()
        ->assertSee('対象の単語がありません')
        ->assertDontSee('data-study', false);
});

it('defaults invalid study methods to flip', function () {
    Word::factory()->for($this->user)->create(['english' => 'apple', 'japanese' => 'りんご']);

    $this->get(route('study.session', ['method' => 'invalid']))
        ->assertSuccessful()
        ->assertSee('data-method="flip"', false)
        ->assertSee('data-study-deck', false);
});
