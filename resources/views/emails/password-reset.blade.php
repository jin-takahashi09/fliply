<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>パスワード再設定のお知らせ | Fliply</title>
</head>
<body style="margin:0;padding:0;background-color:#f7fbff;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f7fbff;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background-color:#ffffff;border:1px solid #dce5ee;border-radius:16px;">
                    <tr>
                        <td style="padding:36px 32px 28px;font-family:'Hiragino Kaku Gothic ProN','Yu Gothic',Meiryo,sans-serif;color:#10284a;">
                            <p style="margin:0 0 28px;font-family:Georgia,'Times New Roman','Noto Serif JP',serif;font-size:28px;font-weight:500;letter-spacing:-0.03em;color:#10284a;line-height:1.2;">
                                Fliply
                            </p>

                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#10284a;">
                                Fliplyのパスワード再設定を受け付けました。
                            </p>

                            <p style="margin:0 0 28px;font-size:14px;line-height:1.7;color:#29425f;">
                                下のボタンから、新しいパスワードを設定できます。
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px;">
                                <tr>
                                    <td align="center" bgcolor="#3189ed" style="border-radius:12px;">
                                        <a href="{{ $url }}"
                                           style="display:inline-block;padding:14px 24px;font-family:'Hiragino Kaku Gothic ProN','Yu Gothic',Meiryo,sans-serif;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;">
                                            パスワードを再設定する
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 12px;font-size:13px;line-height:1.7;color:#607795;">
                                このリンクは{{ $expireMinutes }}分間有効です。
                            </p>

                            <p style="margin:0;font-size:13px;line-height:1.7;color:#607795;">
                                心当たりがない場合は、このメールを無視してください。パスワードは変更されません。
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
