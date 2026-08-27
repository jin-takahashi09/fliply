<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta
        name="theme-color"
        content="{{ request()->routeIs('home') ? '#eaf4ff' : '#0d6b5c' }}">
    <title>@yield('title', 'Fliply')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="{{ request()->routeIs('home') ? 'page-home' : '' }}"></body>
<div class="app-shell {{ ($hideNav ?? false) ? 'app-shell--focus' : '' }}">
    @if ($compactHeader ?? false)
    <header class="focus-header">
        @yield('compact-header')
    </header>
    @else
    <header class="app-header">
        <a href="{{ route('home') }}" class="brand" aria-label="Fliply ホーム">
            <span class="brand__mark">
                <x-icon
                    :name="request()->routeIs('home') ? 'fliply-mark' : 'layers'"
                    :size="24" />
            </span>

            <span class="brand__name">Fliply</span>
        </a>

        @unless (request()->routeIs('home'))
        <span class="brand__line">Words that stay.</span>
        @endunless
    </header>
    @endif

    <main class="app-main {{ ($hideNav ?? false) ? 'app-main--focus' : '' }}">
        @if (session('status'))
        <div class="toast" role="status" data-auto-dismiss>
            <span class="toast__icon"><x-icon name="check" :size="15" /></span>
            {{ session('status') }}
        </div>
        @endif

        @yield('content')
    </main>

    @unless ($hideNav ?? false)
    <x-bottom-nav />
    @endunless
</div>
</body>

</html>