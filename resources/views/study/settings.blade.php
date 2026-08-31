@extends('layouts.app')

@section('title', '学習設定 - Fliply')

@section('content')
    <section class="study-heading reveal">
        <p class="eyebrow">STUDY SESSION</p>
        <h1>今日の学習をつくる。</h1>
    </section>

    @if ($totalWords === 0)
        <section class="empty-state reveal reveal--delay-1">
            <span class="empty-state__icon"><x-icon name="cards" :size="30" /></span>
            <h2>学習できる単語がありません</h2>
            <p>先に単語を登録すると、カード学習を始められます。</p>
            <a href="{{ route('words.create') }}" class="primary-button"><x-icon name="plus" :size="17" /> 単語を追加</a>
        </section>
    @else
        <form method="GET" action="{{ route('study.session') }}" class="study-settings">
            <fieldset class="settings-card reveal reveal--delay-1">
                <legend>出題方向</legend>
                <div class="choice-grid">
                    <label class="choice-card">
                        <input type="radio" name="direction" value="en-ja" checked>
                        <span class="choice-card__indicator"></span>
                        <span><strong>English</strong><small>英語 → 日本語</small></span>
                    </label>
                    <label class="choice-card">
                        <input type="radio" name="direction" value="ja-en">
                        <span class="choice-card__indicator"></span>
                        <span><strong>日本語</strong><small>日本語 → 英語</small></span>
                    </label>
                </div>
            </fieldset>

            <fieldset class="settings-card reveal reveal--delay-2">
                <legend>学習方法</legend>
                <div class="choice-grid">
                    <label class="choice-card">
                        <input type="radio" name="method" value="flip" checked>
                        <span class="choice-card__indicator"></span>
                        <span><strong>カードをめくる</strong><small>答えを考えてカードをめくる</small></span>
                    </label>
                    <label class="choice-card">
                        <input type="radio" name="method" value="input">
                        <span class="choice-card__indicator"></span>
                        <span><strong>入力して答える</strong><small>答えを入力して正誤判定</small></span>
                    </label>
                </div>
            </fieldset>

            <fieldset class="settings-card reveal reveal--delay-3">
                <legend>出題する単語</legend>
                <div class="choice-stack">
                    <label class="scope-card">
                        <input type="radio" name="scope" value="all" checked>
                        <span class="scope-card__icon"><x-icon name="layers" :size="19" /></span>
                        <span><strong>すべての単語</strong><small>{{ $totalWords }}語のカード</small></span>
                        <span class="choice-card__indicator"></span>
                    </label>
                    <label class="scope-card {{ $hardWords === 0 ? 'is-disabled' : '' }}">
                        <input type="radio" name="scope" value="hard" @disabled($hardWords === 0)>
                        <span class="scope-card__icon scope-card__icon--hard"><x-icon name="star" :size="19" /></span>
                        <span><strong>難しい単語だけ</strong><small>{{ $hardWords }}語を集中して復習</small></span>
                        <span class="choice-card__indicator"></span>
                    </label>
                </div>
            </fieldset>

            <button type="submit" class="start-button reveal reveal--delay-4">
                <span>学習をスタート</span>
                <span class="start-button__arrow"><x-icon name="arrow-right" :size="19" /></span>
            </button>
        </form>
    @endif
@endsection
