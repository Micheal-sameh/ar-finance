@extends('pdf.layout')

@section('content')
    @php
        $isGrouped = ! empty($report['group_by']);
        $hasCompare = ! $isGrouped && ! empty($report['compare_from']);
    @endphp

    <table class="doc-header">
        <tr>
            <td>
                <h1>{{ $tenant?->name }}</h1>
                <div class="muted">Profit &amp; Loss</div>
            </td>
            <td>
                <div class="doc-title">PROFIT &amp; LOSS</div>
                <div class="doc-number">{{ $report['from'] }} &ndash; {{ $report['to'] }}</div>
                @if ($hasCompare)
                    <div class="doc-number">vs {{ $report['compare_from'] }} &ndash; {{ $report['compare_to'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if ($isGrouped)
        <table class="lines">
            <thead>
            <tr>
                <th colspan="2">Revenue</th>
                @foreach ($report['columns'] as $column)
                    <th class="text-end">{{ $column['label'] }}</th>
                @endforeach
                <th class="text-end">Total</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($report['revenue'] as $row)
                <tr>
                    <td colspan="2">{{ $row['name'] }}</td>
                    @foreach ($report['columns'] as $column)
                        <td class="text-end">{{ number_format($row['amounts'][$column['key']], 2) }}</td>
                    @endforeach
                    <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['columns']) + 3 }}" class="muted">No revenue posted in this period.</td>
                </tr>
            @endforelse
            <tr style="font-weight: bold;">
                <td colspan="2">Total Revenue</td>
                @foreach ($report['columns'] as $column)
                    <td class="text-end">{{ number_format($report['total_revenue']['amounts'][$column['key']], 2) }}</td>
                @endforeach
                <td class="text-end">{{ number_format($report['total_revenue']['total'], 2) }}</td>
            </tr>
            </tbody>
        </table>

        <table class="lines" style="margin-top: 16px;">
            <thead>
            <tr>
                <th colspan="2">Expenses</th>
                @foreach ($report['columns'] as $column)
                    <th class="text-end">{{ $column['label'] }}</th>
                @endforeach
                <th class="text-end">Total</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($report['expenses'] as $row)
                <tr>
                    <td colspan="2">{{ $row['name'] }}</td>
                    @foreach ($report['columns'] as $column)
                        <td class="text-end">{{ number_format($row['amounts'][$column['key']], 2) }}</td>
                    @endforeach
                    <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['columns']) + 3 }}" class="muted">No expenses posted in this period.</td>
                </tr>
            @endforelse
            <tr style="font-weight: bold;">
                <td colspan="2">Total Expenses</td>
                @foreach ($report['columns'] as $column)
                    <td class="text-end">{{ number_format($report['total_expenses']['amounts'][$column['key']], 2) }}</td>
                @endforeach
                <td class="text-end">{{ number_format($report['total_expenses']['total'], 2) }}</td>
            </tr>
            </tbody>
        </table>

        <table class="totals">
            <tr class="grand">
                <td style="width: 60%;">Net Profit</td>
                @foreach ($report['columns'] as $column)
                    <td class="text-end">{{ number_format($report['net_profit']['amounts'][$column['key']], 2) }}</td>
                @endforeach
                <td class="text-end">{{ number_format($report['net_profit']['total'], 2) }}</td>
            </tr>
        </table>
    @else
        <table class="lines">
            <thead>
            <tr>
                <th colspan="2">Revenue</th>
                <th class="text-end">Amount</th>
                @if ($hasCompare)
                    <th class="text-end">Prior period</th>
                @endif
            </tr>
            </thead>
            <tbody>
            @forelse ($report['revenue'] as $row)
                <tr>
                    <td colspan="2">{{ $row['name'] }}</td>
                    <td class="text-end">{{ number_format($row['current'], 2) }}</td>
                    @if ($hasCompare)
                        <td class="text-end">{{ number_format($row['prior'], 2) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $hasCompare ? 4 : 3 }}" class="muted">No revenue posted in this period.</td>
                </tr>
            @endforelse
            <tr style="font-weight: bold;">
                <td colspan="2">Total Revenue</td>
                <td class="text-end">{{ number_format($report['total_revenue']['current'], 2) }}</td>
                @if ($hasCompare)
                    <td class="text-end">{{ number_format($report['total_revenue']['prior'], 2) }}</td>
                @endif
            </tr>
            </tbody>
        </table>

        <table class="lines" style="margin-top: 16px;">
            <thead>
            <tr>
                <th colspan="2">Expenses</th>
                <th class="text-end">Amount</th>
                @if ($hasCompare)
                    <th class="text-end">Prior period</th>
                @endif
            </tr>
            </thead>
            <tbody>
            @forelse ($report['expenses'] as $row)
                <tr>
                    <td colspan="2">{{ $row['name'] }}</td>
                    <td class="text-end">{{ number_format($row['current'], 2) }}</td>
                    @if ($hasCompare)
                        <td class="text-end">{{ number_format($row['prior'], 2) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $hasCompare ? 4 : 3 }}" class="muted">No expenses posted in this period.</td>
                </tr>
            @endforelse
            <tr style="font-weight: bold;">
                <td colspan="2">Total Expenses</td>
                <td class="text-end">{{ number_format($report['total_expenses']['current'], 2) }}</td>
                @if ($hasCompare)
                    <td class="text-end">{{ number_format($report['total_expenses']['prior'], 2) }}</td>
                @endif
            </tr>
            </tbody>
        </table>

        <table class="totals">
            <tr class="grand">
                <td style="width: 60%;">Net Profit</td>
                <td class="text-end">{{ number_format($report['net_profit']['current'], 2) }}</td>
                @if ($hasCompare)
                    <td class="text-end">{{ number_format($report['net_profit']['prior'], 2) }}</td>
                @endif
            </tr>
        </table>
    @endif

    <div class="footer-note">Generated {{ now()->format('Y-m-d H:i') }}</div>
@endsection
