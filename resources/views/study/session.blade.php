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
        @endphp

        <section class="study-session" data-study data-direction="{{ $direction }}">
            <div class="progress-track" aria-hidden="true">
                <span data-progress-bar data-progress-width="{{ 100 / $words->count() }}"></span>
            </div>

            <div class="study-context">
                <span data-card-number>CARD 01</span>
                <p>{{ $direction === 'en-ja' ? '英単語の意味を思い出そう' : '日本語に合う英単語を思い出そう' }}</p>
            </div>

            <div class="study-book-wrap" data-card-wrap>
                <div class="study-book" data-card role="button" tabindex="0" aria-label="カードをめくって答えを見る">
                    <article class="study-card study-card__answer">
                        <span class="study-card__label">ANSWER</span>
                        <strong data-answer-text>{{ $direction === 'en-ja' ? $words->first()->japanese : $words->first()->english }}</strong>
                        <small>答え</small>
                    </article>

                    <article class="study-card study-card__question">
                        <span class="study-card__label">QUESTION</span>
                        <strong data-question-text>{{ $direction === 'en-ja' ? $words->first()->english : $words->first()->japanese }}</strong>
                        <span class="turn-hint"><span class="turn-hint__gesture">↗</span> タップしてめくる</span>
                    </article>
                </div>
            </div>

            <div class="study-actions" data-study-actions aria-hidden="true">
                <button type="button" class="answer-button answer-button--hard" data-answer="hard">
                    <x-icon name="star" :size="19" />
                    <span><strong>難しかった</strong><small>今回の記録に追加</small></span>
                </button>
                <button type="button" class="answer-button answer-button--known" data-answer="known">
                    <x-icon name="check" :size="19" />
                    <span><strong>わかった</strong><small>次のカードへ</small></span>
                </button>
            </div>

            <p class="study-tip" data-study-tip>カードの右側をめくるようにタップ</p>
        </section>

        <section class="complete-screen" data-complete hidden>
            <div class="complete-mark"><x-icon name="sparkle" :size="32" /></div>
            <p class="eyebrow">SESSION COMPLETE</p>
            <h1>今日の学習、おつかれさま。</h1>
            <p>最後までカードをめくりました。少しずつ、使える言葉になっています。</p>

            <div class="complete-stats">
                <div><strong>{{ $words->count() }}</strong><small>学習した単語</small></div>
                <div><strong data-hard-count>0</strong><small>難しかった</small></div>
            </div>

            <div class="complete-actions">
                <a href="{{ request()->fullUrl() }}" class="primary-button"><x-icon name="cards" :size="17" /> もう一度</a>
                <a href="{{ route('home') }}" class="secondary-button">ホームへ戻る</a>
            </div>
        </section>

        <script type="application/json" id="study-words">{!! json_encode($studyWords, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
@endsection
