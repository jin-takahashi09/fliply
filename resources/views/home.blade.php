@extends('layouts.app')

@section('title', 'ホーム - Fliply')

@section('content')
    @php($hasWords = $totalWords > 0)

    <div class="home-dashboard">
        <section class="home-hero reveal">
            <svg
                class="home-hero__background"
                viewBox="0 0 520 520"
                preserveAspectRatio="none"
                aria-hidden="true"
            >
                <rect width="520" height="520" fill="#dfeeff" />
                <circle cx="435" cy="70" r="112" fill="#f8fbff" />

                <path
                    d="M-40 92 C70 30 175 145 290 120 C395 97 465 45 560 61 V-30 H-40Z"
                    fill="#cfe4ff"
                />

                <path
                    d="M-40 420 C90 345 210 470 335 425 C430 390 500 340 570 365 V560 H-40Z"
                    fill="#c7dfff"
                    opacity=".72"
                />

                <path
                    d="M-30 74 C65 18 135 110 235 78 C294 59 280 17 328 -25"
                    fill="none"
                    stroke="white"
                    stroke-width="2.2"
                    stroke-linecap="round"
                />

                <path
                    d="M445 -24 C472 42 493 52 550 97"
                    fill="none"
                    stroke="#f4c95d"
                    stroke-width="2.2"
                    stroke-linecap="round"
                />

                <path
                    d="M250 520 C320 450 375 438 445 475 C480 494 515 512 550 500"
                    fill="none"
                    stroke="#f4c95d"
                    stroke-width="2"
                    stroke-linecap="round"
                />

                <path
                    d="M-70 195 A185 185 0 0 1 155 405"
                    fill="none"
                    stroke="white"
                    stroke-width="1"
                    opacity=".58"
                />

                <path
                    d="M-52 218 A160 160 0 0 1 130 393"
                    fill="none"
                    stroke="#f4c95d"
                    stroke-width="1"
                    opacity=".46"
                />

                <path
                    d="M365 520 A150 150 0 0 1 550 370"
                    fill="none"
                    stroke="white"
                    stroke-width="1.5"
                    opacity=".55"
                />
            </svg>

            <div class="home-hero__inner">
                <div class="home-hero__copy">
                    <h1>
                        めくるたび、<br>
                        使える英語が<br>
                        増えていく。
                    </h1>

                    <a
                        href="{{ $hasWords ? route('study.settings') : route('words.create') }}"
                        class="home-hero__button"
                    >
                        {{ $hasWords ? 'カードをめくる' : '単語を追加する' }}
                        <x-icon :name="$hasWords ? 'arrow-right' : 'plus'" :size="20" />
                    </a>
                </div>

                <div class="home-word-stack" aria-label="最新の単語">
                    <div
                        class="home-word-card home-word-card--back"
                        aria-hidden="true"
                    ></div>

                    <div class="home-word-card home-word-card--front">
                        <span
                            class="home-word-card__bookmark"
                            aria-hidden="true"
                        ></span>

                        <small>LATEST WORD</small>

                        <strong>
                            {{ $featuredWord?->english ?? 'persist' }}
                        </strong>

                        <span
                            class="home-word-card__divider"
                            aria-hidden="true"
                        ></span>

                        <p>
                            {{ $featuredWord?->japanese ?? '粘り強く続ける' }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section
            class="home-stats reveal reveal--delay-1"
            aria-label="単語帳の情報"
        >
            <a
                href="{{ route('words.index') }}"
                class="home-stat-card"
            >
                <span
                    class="home-stat-card__icon"
                    aria-hidden="true"
                >
                    <x-icon name="book" :size="34" />
                </span>

                <span class="home-stat-card__label">
                    登録単語
                </span>

                <strong>
                    {{ $totalWords }}
                </strong>
            </a>

            <a
                href="{{ route('words.index', ['filter' => 'hard']) }}"
                class="home-stat-card home-stat-card--hard"
            >
                <span
                    class="home-stat-card__icon"
                    aria-hidden="true"
                >
                    <x-icon name="chart-up" :size="34" />
                </span>

                <span class="home-stat-card__label">
                    難しい単語
                </span>

                <strong>
                    {{ $hardWords }}
                </strong>
            </a>
        </section>

        <section class="home-recent reveal reveal--delay-2">
            <div class="home-recent__head">
                <div>
                    <span>RECENT WORDS</span>
                    <h2>最近追加した単語</h2>
                </div>

                <a href="{{ route('words.index') }}">
                    すべて見る
                </a>
            </div>

            @if ($recentWords->isNotEmpty())
                <div class="home-recent__grid">
                    @foreach ($recentWords as $word)
                        <a
                            href="{{ route('words.index') }}"
                            class="home-recent-card {{ $word->is_hard ? 'is-hard' : '' }}"
                        >
                            @if ($word->is_hard)
                                <span
                                    class="home-recent-card__star"
                                    aria-label="難しい単語"
                                >
                                    ★
                                </span>
                            @endif

                            <strong>
                                {{ $word->english }}
                            </strong>

                            <p>
                                {{ $word->japanese }}
                            </p>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="home-recent__empty">
                    <p>
                        まだ単語が登録されていません。
                    </p>

                    <a href="{{ route('words.create') }}">
                        最初の単語を追加
                    </a>
                </div>
            @endif
        </section>
    </div>
@endsection