<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>単語一覧 - Fliply</title>
</head>
<body>
    <h1>単語一覧</h1>

    <p><a href="{{ route('dictionary.index') }}">単語を追加</a></p>

    <form method="GET" action="{{ route('words.index') }}">
        @if ($filter === 'hard')
            <input type="hidden" name="filter" value="hard">
        @elseif ($filter === 'normal')
            <input type="hidden" name="filter" value="normal">
        @endif
        <input type="text" name="q" value="{{ $q }}" placeholder="英単語を検索">
        <button type="submit">検索</button>
    </form>

    <p>
        <a href="{{ route('words.index', array_filter(['q' => $q ?: null])) }}" @if ($filter === null || $filter === '') style="font-weight: bold;" @endif>すべて</a>
        |
        <a href="{{ route('words.index', array_filter(['q' => $q ?: null, 'filter' => 'hard'])) }}" @if ($filter === 'hard') style="font-weight: bold;" @endif>★ 難しい</a>
        |
        <a href="{{ route('words.index', array_filter(['q' => $q ?: null, 'filter' => 'normal'])) }}" @if ($filter === 'normal') style="font-weight: bold;" @endif>☆ 難しい以外</a>
    </p>

    @if ($words->isEmpty())
        <p>{{ ($q !== '' || in_array($filter, ['hard', 'normal'], true)) ? '該当する単語がありません' : '単語はまだ登録されていません。' }}</p>
    @else
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>英単語</th>
                    <th>日本語</th>
                    <th>難しい</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($words as $word)
                    <tr>
                        <td>{{ $word->english }}</td>
                        <td>{{ $word->japanese }}</td>
                        <td>
                            <form method="POST" action="{{ route('words.toggle-hard', $word) }}" style="display: inline;">
                                @csrf
                                @method('PATCH')
                                @if ($filter === 'hard')
                                    <input type="hidden" name="filter" value="hard">
                                @elseif ($filter === 'normal')
                                    <input type="hidden" name="filter" value="normal">
                                @endif
                                @if ($q !== '')
                                    <input type="hidden" name="q" value="{{ $q }}">
                                @endif
                                <button type="submit">{{ $word->is_hard ? '★' : '☆' }}</button>
                            </form>
                        </td>
                        <td>
                            <a href="{{ route('words.edit', $word) }}">編集</a>
                            <form method="POST" action="{{ route('words.destroy', $word) }}" style="display: inline;" onsubmit="return confirm('この単語を削除しますか？');">
                                @csrf
                                @method('DELETE')
                                <button type="submit">削除</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
