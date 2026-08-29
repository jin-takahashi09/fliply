@extends('layouts.app')

@section('title', '単語帳 - Fliply')

@section('content')
<div class="words-page" data-words-filter="{{ $filter ?? '' }}">
    <div class="words-page__fixed">
        <section class="collection-head reveal">
            <div class="collection-head__row">
                <div>
                    <h1>単語帳</h1>
                </div>
                <div class="collection-count">
                    <strong data-words-count>{{ $words->count() }}</strong>
                    <small>WORDS</small>
                </div>
            </div>
        </section>

        <section class="collection-tools reveal reveal--delay-1">
            <p class="collection-tools__title">
                <a href="{{ route('dictionary.index') }}">単語を追加</a>
            </p>

            <form method="GET" action="{{ route('words.index') }}" class="search-form" role="search">
                @if ($filter === 'hard')
                <input type="hidden" name="filter" value="hard">
                @elseif ($filter === 'normal')
                <input type="hidden" name="filter" value="normal">
                @endif
                <x-icon name="search" :size="19" class="search-form__icon" />
                <input type="search" name="q" value="{{ $q }}" placeholder="英単語を検索" aria-label="英単語を検索" data-search-input>
            </form>

            <div class="filter-row">
                <a href="{{ route('words.index', array_filter(['q' => $q ?: null])) }}" class="filter-chip {{ ($filter !== 'hard' && $filter !== 'normal') ? 'is-active' : '' }}">すべて</a>
                <a href="{{ route('words.index', array_filter(['q' => $q ?: null, 'filter' => 'hard'])) }}" class="filter-chip {{ $filter === 'hard' ? 'is-active' : '' }}">
                    <x-icon name="star" :size="13" /> 難しい
                </a>
                <a href="{{ route('words.index', array_filter(['q' => $q ?: null, 'filter' => 'normal'])) }}" class="filter-chip {{ $filter === 'normal' ? 'is-active' : '' }}">
                    <x-icon name="star" :size="13" /> 難しい以外
                </a>
                @if ($q !== '')
                <a href="{{ route('words.index', array_filter(['filter' => in_array($filter, ['hard', 'normal'], true) ? $filter : null])) }}" class="filter-chip filter-chip--clear">
                    <x-icon name="close" :size="13" /> 検索解除
                </a>
                @endif
            </div>
        </section>
    </div>

    <div class="words-page__scroll">
        @if ($words->isEmpty())
        <section class="empty-state reveal reveal--delay-2">
            <span class="empty-state__icon"><x-icon name="book" :size="30" /></span>
            <h2>{{ ($q !== '' || in_array($filter, ['hard', 'normal'], true)) ? '該当する単語がありません' : '単語はまだ登録されていません。' }}</h2>
            <p>{{ ($q !== '' || in_array($filter, ['hard', 'normal'], true)) ? '検索条件を変えて、もう一度試してみてください。' : '覚えたい単語を追加すると、ここに並びます。' }}</p>
            @if ($q === '' && ! in_array($filter, ['hard', 'normal'], true))
            <a href="{{ route('dictionary.index') }}" class="primary-button"><x-icon name="plus" :size="17" /> 最初の単語を追加</a>
            @endif
        </section>
        @else
        <section class="word-list" aria-label="登録単語">
            @foreach ($words as $word)
            <article class="word-row {{ $word->is_hard ? 'is-hard' : '' }} reveal" style="--delay: {{ min($loop->index * 35, 280) }}ms">
                <span class="word-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <div class="word-copy">
                    <h2>{{ $word->english }}</h2>
                    <p>{{ $word->japanese }}</p>
                </div>
                <div class="word-actions">
                    <form
                        method="POST"
                        action="{{ route('words.toggle-hard', $word) }}"
                        data-toggle-hard-form>
                        @csrf
                        @method('PATCH')
                        @if ($filter === 'hard')<input type="hidden" name="filter" value="hard">@endif
                        @if ($filter === 'normal')<input type="hidden" name="filter" value="normal">@endif
                        @if ($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif
                        <button type="submit" class="icon-button star-button {{ $word->is_hard ? 'is-active' : '' }}" aria-label="{{ $word->is_hard ? '難しいから外す' : '難しい単語にする' }}">
                            <x-icon name="star" :size="18" />
                        </button>
                    </form>
                    <form method="POST" action="{{ route('words.destroy', $word) }}" data-confirm="「{{ $word->english }}」を削除しますか？">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="icon-button delete-button" aria-label="{{ $word->english }}を削除">
                            <x-icon name="trash" :size="18" />
                        </button>
                    </form>
                </div>
            </article>
            @endforeach
        </section>
        @endif
    </div>
</div>
@endsection
