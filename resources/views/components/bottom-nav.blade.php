<nav {{ $attributes->merge(['class' => 'bottom-nav']) }} aria-label="メインナビゲーション">
    <a href="{{ route('home') }}" class="bottom-nav__item {{ request()->routeIs('home') ? 'is-active' : '' }}">
        <x-icon name="home" />
        <span>ホーム</span>
    </a>
    <a href="{{ route('words.index') }}" class="bottom-nav__item {{ request()->routeIs('words.index', 'words.edit') ? 'is-active' : '' }}">
        <x-icon name="list" />
        <span>単語帳</span>
    </a>
    <a href="{{ route('study.settings') }}" class="bottom-nav__item {{ request()->routeIs('study.*') ? 'is-active' : '' }}">
        <x-icon name="cards" />
        <span>学習</span>
    </a>
    <a href="{{ route('words.create') }}" class="bottom-nav__item {{ request()->routeIs('words.create') ? 'is-active' : '' }}">
        <x-icon name="plus" />
        <span>追加</span>
    </a>
</nav>
