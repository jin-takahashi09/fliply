@extends('layouts.app')

@section('title', '単語を追加 - Fliply')

@section('content')
    <div class="page-back reveal">
        <a href="{{ route('words.index') }}"><x-icon name="arrow-left" :size="17" /> 単語帳へ戻る</a>
    </div>

    <section class="form-heading reveal reveal--delay-1">
        <p class="eyebrow">NEW WORD</p>
        <h1>言葉を、ひとつ追加。</h1>
        <p>覚えたい英単語と、自分にわかりやすい意味を登録します。</p>
    </section>

    <section class="form-card reveal reveal--delay-2">
        @include('words._form')
    </section>

    <aside class="form-tip reveal reveal--delay-3">
        <span><x-icon name="sparkle" :size="17" /></span>
        <p><strong>意味は自由に編集できます。</strong><br>自分が思い出しやすい言葉にすると、復習しやすくなります。</p>
    </aside>
@endsection
