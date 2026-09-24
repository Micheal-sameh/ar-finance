@extends('pdf.layout')

@section('content')
    <table class="doc-header">
        <tr>
            <td>
                <h1>{{ $invoice->tenant?->name }}</h1>
                <div class="muted">{{ $invoice->currency }}</div>
            </td>
            <td>
                <div class="doc-title">INVOICE</div>
                <div class="doc-number">{{ $invoice->invoice_number }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Bill to</div>
                <div><strong>{{ $invoice->client?->name }}</strong></div>
                @if ($invoice->client?->address)
                    <div>{{ $invoice->client->address }}</div>
                @endif
                @if ($invoice->client?->email)
                    <div>{{ $invoice->client->email }}</div>
                @endif
                @if ($invoice->client?->phone)
                    <div>{{ $invoice->client->phone }}</div>
                @endif
                @if ($invoice->client?->tax_number)
                    <div>Tax No: {{ $invoice->client->tax_number }}</div>
                @endif
            </td>
            <td class="text-end">
                <div><span class="label">Issue date</span></div>
                <div>{{ $invoice->issue_date->format('Y-m-d') }}</div>
                <div style="margin-top: 8px;"><span class="label">Due date</span></div>
                <div>{{ $invoice->due_date->format('Y-m-d') }}</div>
                <div style="margin-top: 8px;"><span class="label">Status</span></div>
                <div>{{ ucfirst($invoice->status->value) }}</div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
        <tr>
            <th>Description</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Unit price</th>
            <th class="text-end">Tax %</th>
            <th class="text-end">Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>{{ $line->description }}</td>
                <td class="text-end">{{ number_format((float) $line->quantity, 2) }}</td>
                <td class="text-end">{{ number_format((float) $line->unit_price, 2) }}</td>
                <td class="text-end">{{ number_format((float) $line->tax_rate, 2) }}%</td>
                <td class="text-end">{{ number_format($line->lineTotal(), 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="text-end" style="width: 80%;">Subtotal</td>
            <td class="text-end">{{ number_format($invoice->subtotal(), 2) }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td class="text-end">Tax</td>
            <td class="text-end">{{ number_format($invoice->totalTax(), 2) }} {{ $invoice->currency }}</td>
        </tr>
        <tr class="grand">
            <td class="text-end">Total</td>
            <td class="text-end">{{ number_format($invoice->total(), 2) }} {{ $invoice->currency }}</td>
        </tr>
    </table>

    <div class="footer-note">Generated {{ now()->format('Y-m-d H:i') }}</div>
@endsection
