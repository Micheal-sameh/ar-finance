@extends('pdf.layout')

@section('content')
    <table class="doc-header">
        <tr>
            <td>
                <h1>{{ $payslip->payrollRun?->tenant?->name }}</h1>
            </td>
            <td>
                <div class="doc-title">PAYSLIP</div>
                <div class="doc-number">{{ $payslip->payrollRun?->period_start->format('Y-m-d') }} &ndash; {{ $payslip->payrollRun?->period_end->format('Y-m-d') }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Employee</div>
                <div><strong>{{ $payslip->employee?->name }}</strong></div>
                @if ($payslip->employee?->job_title)
                    <div>{{ $payslip->employee->job_title }}</div>
                @endif
                @if ($payslip->employee?->email)
                    <div>{{ $payslip->employee->email }}</div>
                @endif
            </td>
            <td class="text-end">
                <div><span class="label">Pay date</span></div>
                <div>{{ $payslip->payrollRun?->pay_date->format('Y-m-d') }}</div>
                <div style="margin-top: 8px;"><span class="label">Status</span></div>
                <div>{{ ucfirst($payslip->payrollRun?->status->value) }}</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>Gross pay</td>
            <td class="text-end">{{ number_format((float) $payslip->gross_pay, 2) }}</td>
        </tr>
        <tr>
            <td>Deductions</td>
            <td class="text-end">{{ number_format((float) $payslip->deductions, 2) }}</td>
        </tr>
    </table>

    <table class="totals">
        <tr class="grand">
            <td class="text-end" style="width: 80%;">Net pay</td>
            <td class="text-end">{{ number_format((float) $payslip->net_pay, 2) }}</td>
        </tr>
    </table>

    <div class="footer-note">Generated {{ now()->format('Y-m-d H:i') }}</div>
@endsection
