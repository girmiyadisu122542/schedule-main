{{--
    Login credentials for a newly created account.

    `password` is a one-time plaintext value the account was created with — it
    exists nowhere else, so this mail is the only copy. That is exactly why the
    body tells the recipient to change it on first sign-in rather than treating
    it as a permanent secret.
--}}
@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px; color:#111827; font-size:16px; font-weight:600;">
        {{ $line('Welcome, :name', 'እንኳን ደህና መጡ፣ :name', ['name' => $data['full_name'] ?? $data['name']]) }}
    </p>

    <p style="margin:0 0 20px; color:#374151; font-size:14px; line-height:1.7;">
        {{ $line(
            'An account has been created for you on :app. Use the details below to sign in.',
            'በ:app ላይ መለያ ተፈጥሮልዎታል። ለመግባት ከዚህ በታች ያሉትን ዝርዝሮች ይጠቀሙ።',
            ['app' => config('app.name')]
        ) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; margin:0 0 20px;">
        <tr>
            <td style="padding:14px 16px; border-bottom:1px solid #e5e7eb;">
                <span style="display:block; color:#6b7280; font-size:12px;">{{ $line('Email', 'ኢሜይል') }}</span>
                <span style="color:#111827; font-size:14px; font-weight:600;">{{ $data['email'] }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding:14px 16px;">
                <span style="display:block; color:#6b7280; font-size:12px;">{{ $line('Temporary password', 'ጊዜያዊ የይለፍ ቃል') }}</span>
                <span style="color:#111827; font-size:16px; font-weight:700; letter-spacing:0.5px; font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;">{{ $data['password'] }}</span>
            </td>
        </tr>
    </table>

    @if (config('app.frontend_url'))
        <p style="margin:0 0 20px;">
            <a href="{{ config('app.frontend_url') }}"
               style="display:inline-block; background-color:#0b529c; color:#ffffff; text-decoration:none; padding:11px 22px; border-radius:8px; font-size:14px; font-weight:600;">
                {{ $line('Sign in', 'ይግቡ') }}
            </a>
        </p>
    @endif

    <p style="margin:0; color:#b45309; font-size:13px; line-height:1.7;">
        {{ $line(
            'Please change this password the first time you sign in.',
            'እባክዎ ለመጀመሪያ ጊዜ ሲገቡ ይህንን የይለፍ ቃል ይቀይሩ።'
        ) }}
    </p>
@endsection
