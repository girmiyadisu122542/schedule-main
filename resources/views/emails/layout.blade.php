{{--
    Shared shell for every transactional email this system sends.

    Deliberately plain: table-based, inline styles, no external stylesheet and no
    images. Mail clients strip <style> blocks and block remote assets by default,
    so anything clever here degrades into a broken layout in exactly the clients
    people actually read mail in.
--}}
<!DOCTYPE html>
<html lang="{{ $language ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">

                    <tr>
                        <td style="background-color:#0b529c; padding:20px 28px;">
                            <span style="color:#ffffff; font-size:16px; font-weight:600;">
                                {{ config('app.name') }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#f9fafb; border-top:1px solid #e5e7eb; padding:16px 28px;">
                            <p style="margin:0; color:#6b7280; font-size:12px; line-height:1.6;">
                                {{ $footerNote }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
