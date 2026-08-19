@extends('layouts.app')

@section('title', '単語を編集 - Fliply')

@section('content')
    <div class="page-back reveal">
        <a href="{{ route('words.index') }}"><x-icon name="arrow-left" :size="17" /> 単語帳へ戻る</a>
    </div>

    <section class="form-heading reveal reveal--delay-1">
        <p class="eyebrow">EDIT WORD</p>
        <h1>{{ $word->english }}</h1>
        <p>単語や意味、難しさの設定を編集できます。</p>
    </section>

    <section class="form-card reveal reveal--delay-2">
        @include('words._form', ['word' => $word])
    </section>
@endsection
