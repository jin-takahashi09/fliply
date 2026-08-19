@props(['name', 'size' => 20])

<svg {{ $attributes->merge([
    'width' => $size,
    'height' => $size,
    'viewBox' => '0 0 24 24',
    'fill' => 'none',
    'stroke' => 'currentColor',
    'stroke-width' => '1.8',
    'stroke-linecap' => 'round',
    'stroke-linejoin' => 'round',
    'aria-hidden' => 'true',
]) }}>
    @switch($name)
        @case('layers')
            <path d="m12 2.8 9 4.8-9 4.8-9-4.8 9-4.8Z" />
            <path d="m3 12.2 9 4.8 9-4.8" />
            <path d="m3 16.6 9 4.6 9-4.6" />
            @break
        @case('home')
            <path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10Z" />
            @break
        @case('list')
            <path d="M8 6h13M8 12h13M8 18h13" />
            <path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01" />
            @break
        @case('cards')
            <rect x="3" y="5" width="14" height="16" rx="2" />
            <path d="M7 5V3.8A1.8 1.8 0 0 1 8.8 2H19a2 2 0 0 1 2 2v14.2A1.8 1.8 0 0 1 19.2 20H17" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('arrow-right')
            <path d="M5 12h14M14 7l5 5-5 5" />
            @break
        @case('arrow-left')
            <path d="M19 12H5M10 7l-5 5 5 5" />
            @break
        @case('star')
            <path d="m12 3 2.75 5.57 6.15.9-4.45 4.33 1.05 6.12L12 17.03l-5.5 2.89 1.05-6.12L3.1 9.47l6.15-.9L12 3Z" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
            @break
        @case('edit')
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4 11.5-11.5Z" />
            @break
        @case('trash')
            <path d="M4 7h16M9 7V4h6v3M6.5 7l1 14h9l1-14M10 11v6M14 11v6" />
            @break
        @case('check')
            <path d="m5 12 4 4L19 6" />
            @break
        @case('close')
            <path d="M6 6l12 12M18 6 6 18" />
            @break
        @case('shuffle')
            <path d="M16 3h5v5M4 20l17-17M21 16v5h-5M15 15l6 6M4 4l5 5" />
            @break
        @case('book')
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V4H6.5A2.5 2.5 0 0 0 4 6.5v13Z" />
            <path d="M8 8h8M8 12h6" />
            @break
        @case('sparkle')
            <path d="m12 3 1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3Z" />
            <path d="m18.5 14 .7 2.3 2.3.7-2.3.7-.7 2.3-.7-2.3-2.3-.7 2.3-.7.7-2.3Z" />
            @break
    @endswitch
</svg>
