<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<style>
    body { font-family: dejavusans; font-size: 14px; color: #1a1a1a; direction: rtl; text-align: right; }
    .frame { border: 2px solid #1e3a5f; padding: 22px 26px; }
    .header { width: 100%; border-bottom: 1.5px solid #1e3a5f; padding-bottom: 12px; margin-bottom: 18px; }
    .header td { vertical-align: middle; }
    .logo { width: 90px; }
    .church-name { font-size: 20px; font-weight: bold; color: #1e3a5f; }
    .title { font-size: 24px; font-weight: bold; text-align: center; margin: 6px 0 18px; color: #1e3a5f; }
    .meta { width: 100%; margin-bottom: 14px; }
    .meta td { width: 50%; font-size: 13px; color: #444; }
    table.rows { width: 100%; border-collapse: collapse; }
    table.rows td { padding: 11px 6px; border-bottom: 0.5px solid #d6d6d6; vertical-align: top; }
    table.rows td.label { width: 30%; color: #666; font-size: 13px; }
    table.rows td.value { font-size: 15px; font-weight: bold; }
    .amount-box { margin: 18px 0 8px; padding: 12px 14px; background: #f1f5fa; border: 1px solid #c9d6e6; text-align: center; }
    .amount-box .number { font-size: 26px; font-weight: bold; color: #1e3a5f; }
    .amount-box .words { font-size: 14px; margin-top: 6px; }
    .signatures { width: 100%; margin-top: 46px; }
    .signatures td { width: 50%; text-align: center; font-size: 13px; color: #444; }
    .sign-line { border-top: 1px solid #444; width: 70%; margin: 0 auto 4px; }
    .note { margin-top: 22px; text-align: center; font-size: 12px; color: #666; }
</style>
</head>
<body>
<div class="frame">
    <table class="header">
        <tr>
            <td style="width: 100px;">
                @if (is_file($logoPath))
                    <img src="{{ $logoPath }}" class="logo" alt="">
                @endif
            </td>
            <td>
                <div class="church-name">{{ $tenantName }}</div>
            </td>
        </tr>
    </table>

    <div class="title">إيصال تبرع</div>

    <table class="meta">
        <tr>
            <td>رقم الإيصال: <strong>{{ $receiptNumber }}</strong></td>
            <td>التاريخ: <strong>{{ $date }}</strong></td>
        </tr>
    </table>

    <table class="rows">
        <tr>
            <td class="label">استلمنا من السيد / السيدة</td>
            <td class="value">{{ $donorName }}</td>
        </tr>
        <tr>
            <td class="label">لصالح</td>
            <td class="value">{{ $centerName }}</td>
        </tr>
        <tr>
            <td class="label">العملة</td>
            <td class="value">{{ $currencyName }} ({{ $currency }})</td>
        </tr>
        <tr>
            <td class="label">طريقة الدفع</td>
            <td class="value">{{ $paymentMethod }}@if ($paymentReference) &nbsp;&ndash;&nbsp; رقم المرجع: {{ $paymentReference }}@endif</td>
        </tr>
    </table>

    <div class="amount-box">
        <div class="number" dir="ltr">{{ $amountNumber }} {{ $currency }}</div>
        <div class="words">{{ $amountWords }}</div>
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="sign-line"></div>
                المستلم: {{ $receivedBy }}
            </td>
            <td>
                <div class="sign-line"></div>
                التوقيع / الختم
            </td>
        </tr>
    </table>

    <div class="note">شكراً لمحبتكم وعطائكم &mdash; الرب يعوض تعب محبتكم</div>
</div>
</body>
</html>
