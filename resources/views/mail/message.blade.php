<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f2;font-family:Arial,Helvetica,sans-serif;color:#1f2a1d;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;">
        <tr>
            <td style="padding:20px 24px;border-bottom:3px solid #2f6b3a;">
                <strong style="font-size:16px;color:#2f6b3a;">{{ $group?->name ?? config('app.name') }}</strong>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;font-size:15px;line-height:1.6;">
                {!! nl2br(e($body)) !!}
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;">
                You are receiving this because you are a member of {{ $group?->name ?? config('app.name') }}.
                Reminder messages can be turned off from your profile; receipts and approvals cannot.
            </td>
        </tr>
    </table>
</body>
</html>
