@extends('layouts.app', ['hideNav' => true, 'compactHeader' => true])

@section('title', 'カード学習 - Fliply')

@section('compact-header')
    <a href="{{ route('study.settings') }}" class="focus-header__close" aria-label="学習を終了">
        <x-icon name="close" :size="20" />
    </a>
    <div class="focus-header__progress">
        <strong data-progress-label>{{ $words->isEmpty() ? '0 / 0' : '1 / '.$words->count() }}</strong>
        <span>{{ $scope === 'hard' ? '難しい単語' : 'カード学習' }}</span>
    </div>
    <span class="focus-header__spacer"></span>
@endsection

@section('content')
    @if ($words->isEmpty())
        <section class="empty-state empty-state--study">
            <span class="empty-state__icon"><x-icon name="star" :size="30" /></span>
            <h1>対象の単語がありません</h1>
            <p>{{ $scope === 'hard' ? '難しい単語を登録してから、もう一度試してください。' : '単語を追加してから学習を始めてください。' }}</p>
            <a href="{{ route('study.settings') }}" class="primary-button"><x-icon name="arrow-left" :size="17" /> 設定へ戻る</a>
        </section>
    @else
        @php
            $studyWords = $words->map(fn ($word) => [
                'id' => $word->id,
                'english' => $word->english,
                'japanese' => $word->japanese,
                'isHard' => $word->is_hard,
            ])->values();
            $firstWord = $words->first();
            $firstQuestion = $direction === 'en-ja'
                ? $firstWord->english
                : $firstWord->japanese;
        @endphp

        <section
            class="study-session"
            data-study
            data-direction="{{ $direction }}"
            data-first-question="{{ $firstQuestion }}"
        >
            <div class="progress-track" aria-hidden="true">
                <span data-progress-bar data-progress-width="{{ 100 / $words->count() }}"></span>
            </div>

            <div class="study-context">
                <span data-card-number>CARD 01</span>
            </div>

            <div class="study-stack" data-study-stack>
                <div class="study-stack__layer study-stack__layer--3" data-stack-layer hidden aria-hidden="true">
                    <span class="study-stack__text" data-stack-text></span>
                </div>
                <div class="study-stack__layer study-stack__layer--2" data-stack-layer hidden aria-hidden="true">
                    <span class="study-stack__text" data-stack-text></span>
                </div>
                <div class="study-stack__layer study-stack__layer--1" data-stack-layer hidden aria-hidden="true">
                    <span class="study-stack__text" data-stack-text></span>
                </div>

                <div class="study-book-wrap" data-card-wrap>
                    <div class="study-deck" data-study-deck aria-live="polite"></div>
                </div>
            </div>

            <div class="study-actions" data-study-actions aria-hidden="true">
                <button type="button" class="answer-button answer-button--incorrect" data-answer="incorrect">
                    <x-icon name="close" :size="19" />
                    <span><strong>不正解</strong></span>
                </button>
                <button type="button" class="answer-button answer-button--correct" data-answer="correct">
                    <x-icon name="check" :size="19" />
                    <span><strong>正解</strong></span>
                </button>
            </div>
        </section>

        <section class="complete-screen" data-complete hidden>
            <div class="complete-mark"><x-icon name="sparkle" :size="32" /></div>
            <p class="eyebrow">SESSION COMPLETE</p>
            <h1>学習完了</h1>
            <p class="complete-summary" data-complete-summary>{{ $words->count() }}問中 0問正解</p>

            <div class="complete-stats complete-stats--study">
                <div><strong data-correct-count>0</strong><small>正解</small></div>
                <div><strong data-incorrect-count>0</strong><small>不正解</small></div>
            </div>

            <div class="complete-screen__footer">
                <p class="complete-perfect" data-perfect-message hidden>全問正解！</p>

                <div class="complete-incorrect" data-incorrect-section hidden>
                    <h2>今回間違えた単語</h2>
                    <ul class="complete-incorrect__list" data-incorrect-list></ul>
                </div>

                <div class="complete-actions">
                    <a href="{{ request()->fullUrl() }}" class="primary-button"><x-icon name="cards" :size="17" /> もう一度</a>
                    <a href="{{ route('home') }}" class="secondary-button">ホームへ戻る</a>
                </div>
            </div>
        </section>

        <script type="application/json" id="study-words">{!! json_encode($studyWords, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
@endsection
