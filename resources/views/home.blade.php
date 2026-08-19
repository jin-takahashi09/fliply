@extends('layouts.app')

@section('title', 'ホーム - Fliply')

@section('content')
    <section class="home-intro reveal">
        <p class="eyebrow">{{ now()->format('Y.m.d') }} · DAILY PRACTICE</p>
        <h1>今日も、ひとつずつ<span>✦</span></h1>
        <p>昨日より少しだけ、使える言葉を増やそう。</p>
    </section>

    <section class="hero-card reveal reveal--delay-1">
        <div class="hero-card__content">
            <p class="hero-card__label">TODAY'S SESSION</p>
            <h2>5分だけ、<br><em>英語のスイッチ</em>を入れよう。</h2>
            <p>{{ $totalWords > 0 ? '登録したカードが待っています。' : '最初の単語を登録してみましょう。' }}</p>

            @if ($totalWords > 0)
                <a class="hero-card__button" href="{{ route('study.settings') }}">
                    学習をはじめる
                    <x-icon name="arrow-right" :size="17" />
                </a>
            @else
                <a class="hero-card__button" href="{{ route('words.create') }}">
                    単語を追加する
                    <x-icon name="plus" :size="17" />
                </a>
            @endif
        </div>

        <div class="floating-word" aria-hidden="true">
            <span>LATEST WORD</span>
            <strong>{{ $featuredWord?->english ?? 'resilient' }}</strong>
            <small>{{ $featuredWord?->japanese ?? 'しなやかで強い' }}</small>
        </div>
    </section>

    <section class="stat-grid reveal reveal--delay-2" aria-label="単語帳の情報">
        <a href="{{ route('words.index') }}" class="stat-card">
            <span class="stat-card__icon"><x-icon name="layers" :size="19" /></span>
            <span>
                <small>登録した単語</small>
                <strong>{{ $totalWords }}</strong>
            </span>
        </a>
        <a href="{{ route('words.index', ['filter' => 'hard']) }}" class="stat-card stat-card--hard">
            <span class="stat-card__icon"><x-icon name="star" :size="19" /></span>
            <span>
                <small>難しい単語</small>
                <strong>{{ $hardWords }}</strong>
            </span>
        </a>
    </section>

    <section class="quick-section reveal reveal--delay-3">
        <div class="section-heading">
            <div>
                <p class="eyebrow">QUICK ACTIONS</p>
                <h2>単語帳を育てよう</h2>
            </div>
        </div>

        <div class="quick-grid">
            <a href="{{ route('words.index') }}" class="quick-card">
                <span class="quick-card__icon"><x-icon name="list" /></span>
                <span><strong>一覧を見る</strong><small>登録した言葉を整理</small></span>
                <x-icon name="arrow-right" :size="17" class="quick-card__arrow" />
            </a>
            <a href="{{ route('words.create') }}" class="quick-card">
                <span class="quick-card__icon"><x-icon name="plus" /></span>
                <span><strong>単語を追加</strong><small>新しい言葉を保存</small></span>
                <x-icon name="arrow-right" :size="17" class="quick-card__arrow" />
            </a>
        </div>
    </section>
@endsection
