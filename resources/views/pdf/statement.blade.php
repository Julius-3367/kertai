<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement {{ $member->member_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2a1d; }
        h1 { font-size: 16px; margin: 0; color: #2f6b3a; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { text-align: left; border-bottom: 1px solid #2f6b3a; padding: 4px; font-size: 9px; text-transform: uppercase; }
        td { padding: 4px; border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; white-space: nowrap; }
        .summary td { border: none; padding: 2px 8px 2px 0; }
        .header { border-bottom: 3px solid #2f6b3a; padding-bottom: 8px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $group->name }}</h1>
        <div class="muted">
            @if($group->registration_number) Reg. {{ $group->registration_number }} &middot; @endif
            @if($group->paybill) Paybill {{ $group->paybill }} account {{ $group->paybill_account }} @endif
        </div>
    </div>

    <strong>Member statement: {{ $member->name }} ({{ $member->member_number }})</strong>
    <div class="muted">
        Period: {{ $from?->format('j M Y') ?? 'start' }} to {{ ($to ?? now())->format('j M Y') }} &middot; printed {{ now()->format('j M Y H:i') }}
    </div>

    <table class="summary">
        <tr>
            <td>Savings</td><td class="num"><strong>{{ \App\Support\Money::format($summary['savings']) }}</strong></td>
            <td>Arrears</td><td class="num">{{ \App\Support\Money::format($summary['arrears']) }}</td>
            <td>Fines owed</td><td class="num">{{ \App\Support\Money::format($summary['fines']) }}</td>
            <td>Paid in advance</td><td class="num">{{ \App\Support\Money::format($summary['prepayment']) }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Date</th><th>Ref</th><th>Description</th><th>Fund</th>
                <th class="num">In</th><th class="num">Out</th><th class="num">Savings</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td>{{ $line['date'] }}</td>
                    <td>#{{ $line['entry_number'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td>{{ $line['fund'] }}</td>
                    <td class="num">{{ $line['in'] ? \App\Support\Money::format($line['in'], false) : '' }}</td>
                    <td class="num">{{ $line['out'] ? \App\Support\Money::format($line['out'], false) : '' }}</td>
                    <td class="num">{{ \App\Support\Money::format($line['savings_balance'], false) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No transactions in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted" style="margin-top: 16px;">
        Questions about this statement? Contact the treasurer. Amounts are in Kenya shillings.
    </p>
</body>
</html>
