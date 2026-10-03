@extends('pdf.layout')

@section('content')
    @php
        $isGrouped = ! empty($report['group_by']);
    @endphp

    <table class="doc-header">
        <tr>
            <td>
                <h1>{{ $tenant?->name }}</h1>
                <div class="muted">Balance Sheet</div>
            </td>
            <td>
                <div class="doc-title">BALANCE SHEET</div>
                @if ($isGrouped)
                    <div class="doc-number">{{ $report['from'] }} &ndash; {{ $report['to'] }}</div>
                @else
                    <div class="doc-number">As of {{ $report['as_of'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if ($isGrouped)
        @foreach ([['label' => 'Assets', 'rows' => $report['assets'], 'totals' => $report['total_assets']], ['label' => 'Liabilities', 'rows' => $report['liabilities'], 'totals' => $report['total_liabilities']], ['label' => 'Equity', 'rows' => $report['equity'], 'totals' => $report['total_equity']]] as $section)
            <table class="lines" style="margin-top: 12px;">
                <thead>
                <tr>
                    <th colspan="2">{{ $section['label'] }}</th>
                    @foreach ($report['columns'] as $column)
                        <th class="text-end">{{ $column['label'] }}</th>
                    @endforeach
                    <th class="text-end">Total</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        <td colspan="2">{{ $row['name'] }}</td>
                        @foreach ($report['columns'] as $column)
                            <td class="text-end">{{ number_format($row['amounts'][$column['key']], 2) }}</td>
                        @endforeach
                        <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($report['columns']) + 3 }}" class="muted">No {{ strtolower($section['label']) }} balances in this range.</td>
                    </tr>
                @endforelse
                <tr style="font-weight: bold;">
                    <td colspan="2">Total {{ $section['label'] }}</td>
                    @foreach ($report['columns'] as $column)
                        <td class="text-end">{{ number_format($section['totals']['amounts'][$column['key']], 2) }}</td>
                    @endforeach
                    <td class="text-end">{{ number_format($section['totals']['total'], 2) }}</td>
                </tr>
                </tbody>
            </table>
        @endforeach

        <table class="totals">
            <tr class="grand">
                <td style="width: 40%;">Liabilities + Equity</td>
                @foreach ($report['columns'] as $column)
                    <td class="text-end">{{ number_format($report['liabilities_plus_equity']['amounts'][$column['key']], 2) }}</td>
                @endforeach
                <td class="text-end">{{ number_format($report['liabilities_plus_equity']['total'], 2) }}</td>
            </tr>
        </table>
    @else
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
    @endif

    <div class="footer-note">
        {{ $report['is_balanced'] ? 'Balanced' : 'Out of balance' }} &middot; Generated {{ now()->format('Y-m-d H:i') }}
    </div>
@endsection
