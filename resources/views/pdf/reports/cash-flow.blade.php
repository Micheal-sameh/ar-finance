@extends('pdf.layout')

@section('content')
    @php
        $operatingRows = collect([['name' => 'Net Income', 'amount' => $report['operating']['net_income']]])
            ->merge($report['operating']['adjustments'])
            ->merge($report['operating']['working_capital']);
    @endphp

    <table class="doc-header">
        <tr>
            <td>
                <h1>{{ $tenant?->name }}</h1>
                <div class="muted">Cash Flow Statement</div>
            </td>
            <td>
                <div class="doc-title">CASH FLOW STATEMENT</div>
                <div class="doc-number">{{ $report['from'] }} &ndash; {{ $report['to'] }}</div>
            </td>
        </tr>
    </table>

    @foreach ([
        ['label' => 'Operating Activities', 'rows' => $operatingRows, 'total' => $report['operating']['total'], 'totalLabel' => 'Net Cash from Operating Activities'],
        ['label' => 'Investing Activities', 'rows' => $report['investing']['rows'], 'total' => $report['investing']['total'], 'totalLabel' => 'Net Cash from Investing Activities'],
        ['label' => 'Financing Activities', 'rows' => $report['financing']['rows'], 'total' => $report['financing']['total'], 'totalLabel' => 'Net Cash from Financing Activities'],
    ] as $section)
        <table class="lines" style="margin-top: 12px;">
            <thead>
            <tr>
                <th>{{ $section['label'] }}</th>
                <th class="text-end">Amount</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($section['rows'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="muted">No activity in this period.</td>
                </tr>
            @endforelse
            <tr style="font-weight: bold;">
                <td>{{ $section['totalLabel'] }}</td>
                <td class="text-end">{{ number_format($section['total'], 2) }}</td>
            </tr>
            </tbody>
        </table>
    @endforeach

    <table class="totals">
        <tr class="grand">
            <td style="width: 60%;">Net Change in Cash</td>
            <td class="text-end">{{ number_format($report['net_change_in_cash'], 2) }}</td>
        </tr>
        <tr>
            <td>Cash at Beginning of Period</td>
            <td class="text-end">{{ number_format($report['beginning_cash'], 2) }}</td>
        </tr>
        <tr>
            <td>Cash at End of Period</td>
            <td class="text-end">{{ number_format($report['ending_cash'], 2) }}</td>
        </tr>
    </table>

    <div class="footer-note">
        {{ $report['is_reconciled'] ? 'Reconciled' : 'Out of balance' }} &middot; Generated {{ now()->format('Y-m-d H:i') }}
    </div>
@endsection
