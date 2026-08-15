{{--
    A one-time code, for sign-in, 2FA or a password reset.

    The code is rendered as TEXT, never as an image and never behind a link:
    remote images are blocked by default in most clients, and a one-time code a
    recipient cannot read is a locked account.
--}}
@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px; color:#111827; font-size:16px; font-weight:600;">
        {{ $line('Hello, :name', 'ሰላም፣ :name', ['name' => $data['name']]) }}
    </p>

    <p style="margin:0 0 20px; color:#374151; font-size:14px; line-height:1.7;">
        {{ $data['message'] }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; margin:0 0 20px;">
        <tr>
            <td align="center" style="padding:22px 16px;">
                <span style="display:block; margin-bottom:6px; color:#6b7280; font-size:12px;">
                    {{ $line('Your verification code', 'የማረጋገጫ ኮድዎ') }}
                </span>
                <span style="color:#111827; font-size:30px; font-weight:700; letter-spacing:7px; font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;">
                    {{ $data['otp'] }}
                </span>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 10px; color:#374151; font-size:14px; line-height:1.7;">
        {{ $line(
            'This code expires in :minutes minutes.',
            'ይህ ኮድ በ:minutes ደቂቃዎች ውስጥ ጊዜው ያበቃል።',
            ['minutes' => $data['time']]
        ) }}
    </p>

    <p style="margin:0; color:#6b7280; font-size:13px; line-height:1.7;">
        {{ $line(
            'If you did not request this code, ignore this message — nothing has changed on your account.',
            'ይህንን ኮድ ካልጠየቁ ይህን መልእክት ችላ ይበሉ — በመለያዎ ላይ ምንም አልተቀየረም።'
        ) }}
    </p>
@endsection
