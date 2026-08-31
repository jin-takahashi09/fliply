@extends('layouts.app', ['hideNav' => true])

@section('title', 'パスワード再設定 - Fliply')

@section('content')
    <section class="auth-screen">
        <div class="auth-card">
            <p class="auth-brand brand">
                <span class="brand__mark" aria-hidden="true">
                    <x-icon name="fliply-mark" :size="24" />
                </span>
                <span class="brand__name">Fliply</span>
            </p>

            @if (session('password_reset_sent'))
                <header class="auth-heading">
                    <h1>メールをご確認ください。</h1>
                </header>

                <p class="auth-sent-message">再設定用のリンクを送信しました。</p>

                @php($sentEmail = session('password_reset_email'))

                @if ($sentEmail)
                    <p class="auth-sent-email">{{ $sentEmail }}</p>
                @endif

                @if ($sentEmail && \App\Support\GmailAddress::isGmailAddress($sentEmail))
                    <a
                        href="https://mail.google.com/mail/"
                        class="primary-button auth-submit auth-gmail-button"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Gmailを開く ↗
                    </a>
                @endif

                <p class="auth-switch">
                    <a href="{{ route('login') }}">ログインに戻る →</a>
                </p>
            @else
                <header class="auth-heading">
                    <p class="eyebrow">RESET</p>
                    <h1>パスワードを再設定。</h1>
                </header>

                <form method="POST" action="{{ route('password.email') }}" class="word-form auth-form">
                    @csrf

                    <div class="field-group">
                        <label for="email">
                            <span>メールアドレス</span>
                            <small>EMAIL</small>
                        </label>
                        <div class="field-control {{ $errors->has('email') ? 'has-error' : '' }}">
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="you@example.com"
                                autocomplete="email"
                                required
                                autofocus
                            >
                        </div>
                        @error('email')
                            <p class="field-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="primary-button auth-submit">
                        再設定メールを送信
                    </button>
                </form>

                <p class="auth-switch">
                    <a href="{{ route('login') }}">ログインへ戻る →</a>
                </p>
            @endif
        </div>
    </section>
@endsection
