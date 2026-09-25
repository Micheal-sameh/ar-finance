@extends('pdf.layout')

@section('content')
    <table class="doc-header">
        <tr>
            <td>
                <h1>{{ $tenant?->name }}</h1>
                <div class="muted">Balance Sheet</div>
            </td>
            <td>
                <div class="doc-title">BALANCE SHEET</div>
                <div class="doc-number">As of {{ $report['as_of'] }}</div>
            </td>
        </tr>
    </table>

    @foreach ([['label' => 'Assets', 'rows' => $report['assets'], 'total' => $report['total_assets']], ['label' => 'Liabilities', 'rows' => $report['liabilities'], 'total' => $report['total_liabilities']], ['label' => 'Equity', 'rows' => $report['equity'], 'total' => $report['total_equity']]] as $section)
        <table class="lines" style="margin-top: 12px;">
            <thead>
            <tr>
                <th>{{ $section['label'] }}</th>
                <th class="text-end">Balance</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($section['rows'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="text-end">{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="muted">No {{ strtolower($section['label']) }} balances as of this date.</td>
                </tr>
            @endforelse
            <tr style="font-weight: bold;">
                <td>Total {{ $section['label'] }}</td>
                <td class="text-end">{{ number_format($section['total'], 2) }}</td>
            </tr>
            </tbody>
        </table>
    @endforeach

    <table class="totals">
        <tr class="grand">
            <td style="width: 60%;">Liabilities + Equity</td>
            <td class="text-end">{{ number_format($report['total_liabilities'] + $report['total_equity'], 2) }}</td>
        </tr>
    </table>

    <div class="footer-note">
        {{ $report['is_balanced'] ? 'Balanced' : 'Out of balance' }} &middot; Generated {{ now()->format('Y-m-d H:i') }}
    </div>
@endsection
