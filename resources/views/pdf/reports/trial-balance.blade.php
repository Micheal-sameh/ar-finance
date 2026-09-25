@extends('pdf.layout')

@section('content')
    <table class="doc-header">
        <tr>
            <td>
                <h1>{{ $tenant?->name }}</h1>
                <div class="muted">Trial Balance</div>
            </td>
            <td>
                <div class="doc-title">TRIAL BALANCE</div>
                <div class="doc-number">
                    @if ($from || $to)
                        {{ $from ?? 'Inception' }} &ndash; {{ $to ?? 'Today' }}
                    @else
                        All time
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
        <tr>
            <th>Code</th>
            <th>Account</th>
            <th>Type</th>
            <th class="text-end">Debit</th>
            <th class="text-end">Credit</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($report['rows'] as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ ucfirst($row['type']) }}</td>
                <td class="text-end">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
                <td class="text-end">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand">
            <td style="width: 60%;">Total</td>
            <td class="text-end">{{ number_format($report['total_debit'], 2) }}</td>
            <td class="text-end">{{ number_format($report['total_credit'], 2) }}</td>
        </tr>
    </table>

    <div class="footer-note">
        {{ $report['is_balanced'] ? 'Balanced' : 'Out of balance' }} &middot; Generated {{ now()->format('Y-m-d H:i') }}
    </div>
@endsection
