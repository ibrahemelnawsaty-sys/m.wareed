<!DOCTYPE html>
{{--
    Plain, RTL notification email (§5, §13). The subject and body are admin
    input and are printed ONLY through {{ }} (escaped) — never {!! !!}. No HTML
    the admin types can be injected; `white-space: pre-line` preserves the
    typed line breaks safely without rendering markup.
--}}
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f4f0; font-family:'Segoe UI',Tahoma,Arial,sans-serif; color:#1c1b1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f4f0; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border:1px solid #e5e3dd; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#0f1115; padding:24px 32px; text-align:right;">
                            <span style="color:#ffffff; font-size:20px; font-weight:700;">وريد</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px; text-align:right;">
                            <h1 style="margin:0 0 16px; font-size:18px; font-weight:700; color:#1c1b1a;">{{ $subjectLine }}</h1>
                            <div style="margin:0; font-size:15px; line-height:1.9; color:#3a3935; white-space:pre-line;">{{ $bodyText }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px; border-top:1px solid #e5e3dd; text-align:right;">
                            <p style="margin:0; font-size:12px; color:#8a8780;">هذه رسالة من فريق منصة وريد. لا حاجة للرد على هذا البريد.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
