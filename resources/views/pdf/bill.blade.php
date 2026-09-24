@extends('pdf.layout')

@section('content')
    <table class="doc-header">
        <tr>
            <td>
                <h1>{{ $bill->tenant?->name }}</h1>
            </td>
            <td>
                <div class="doc-title">BILL</div>
                <div class="doc-number">{{ $bill->bill_number }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Vendor</div>
                <div><strong>{{ $bill->vendor?->name }}</strong></div>
                @if ($bill->vendor?->email)
                    <div>{{ $bill->vendor->email }}</div>
                @endif
                @if ($bill->vendor?->tax_number)
                    <div>Tax No: {{ $bill->vendor->tax_number }}</div>
                @endif
                @if ($bill->purchaseOrder?->po_number)
                    <div>PO: {{ $bill->purchaseOrder->po_number }}</div>
                @endif
            </td>
            <td class="text-end">
                <div><span class="label">Bill date</span></div>
                <div>{{ $bill->bill_date->format('Y-m-d') }}</div>
                <div style="margin-top: 8px;"><span class="label">Due date</span></div>
                <div>{{ $bill->due_date->format('Y-m-d') }}</div>
                <div style="margin-top: 8px;"><span class="label">Status</span></div>
                <div>{{ ucfirst($bill->status->value) }}</div>
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
        @foreach ($bill->lines as $line)
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
            <td class="text-end">{{ number_format($bill->subtotal(), 2) }}</td>
        </tr>
        <tr>
            <td class="text-end">Tax</td>
            <td class="text-end">{{ number_format($bill->totalTax(), 2) }}</td>
        </tr>
        <tr class="grand">
            <td class="text-end">Total</td>
            <td class="text-end">{{ number_format($bill->total(), 2) }}</td>
        </tr>
    </table>

    <div class="footer-note">Generated {{ now()->format('Y-m-d H:i') }}</div>
@endsection
