<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>単語一覧 - Fliply</title>
</head>
<body>
    <h1>単語一覧</h1>

    <p><a href="{{ route('words.create') }}">単語を追加</a></p>

    @if ($words->isEmpty())
        <p>単語はまだ登録されていません。</p>
    @else
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>英単語</th>
                    <th>日本語</th>
                    <th>難しい</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($words as $word)
                    <tr>
                        <td>{{ $word->english }}</td>
                        <td>{{ $word->japanese }}</td>
                        <td>
                            @if ($word->is_hard)
                                ★
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
