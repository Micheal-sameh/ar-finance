<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<style>
    body { font-family: dejavusans; font-size: 13px; color: #1a1a1a; direction: rtl; text-align: right; }
    .frame { border: 2.5px solid #000000; padding: 6px; }
    .inner { border: 0.75px solid #777777; padding: 20px 24px; }

    table.header { width: 100%; table-layout: fixed; border-collapse: collapse; border-bottom: 2px solid #000000; }
    table.header td { vertical-align: middle; padding-bottom: 12px; }
    .logo { width: 95px; }
    .church-name { font-size: 19px; font-weight: bold; color: #000000; text-align: center; }

    .title { font-size: 26px; font-weight: bold; text-align: center; color: #000000; margin: 18px 0 4px; }
    .title-rule { width: 90px; border-top: 2px solid #000000; margin: 0 auto 18px; }

    table.meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.meta td { width: 50%; padding: 6px 2px; border-bottom: 1px solid #000000; font-size: 13px; color: #333; }
    table.meta td strong { color: #000000; font-size: 15px; }

    table.rows { width: 100%; border-collapse: collapse; }
    table.rows td { padding: 11px 10px; border-bottom: 0.5px solid #dcdcdc; vertical-align: middle; }
    table.rows td.label { width: 32%; color: #666; font-size: 12.5px; }
    table.rows td.value { font-size: 15px; font-weight: bold; color: #111; }

    .amount-box { margin: 22px 0 6px; padding: 14px 16px; background: #ffffff; border: 2px solid #000000; text-align: center; }
    .amount-box .caption { font-size: 12px; color: #444444; }
    .amount-box .number { font-size: 28px; font-weight: bold; color: #000000; padding: 4px 0; }
    .amount-box .words { font-size: 14px; color: #000000; }

    table.signatures { width: 100%; margin-top: 16px; border-collapse: collapse; }
    table.signatures td { text-align: center; font-size: 12.5px; color: #444; }
    table.signatures td.line { height: 90px; border-bottom: 1px solid #444; }
    table.signatures td.gap { width: 8%; }
    table.signatures td.cell { width: 42%; padding-top: 5px; }

    .remote { margin-top: 18px; padding: 9px 12px; border: 1px solid #000000; font-size: 12px; }
    .remote .head { font-weight: bold; font-size: 12.5px; margin-bottom: 3px; }
    .note { margin-top: 26px; padding-top: 10px; border-top: 0.5px solid #999999; text-align: center; font-size: 12px; color: #666; }
</style>
</head>
<body>
<div class="frame">
<div class="inner">
    <table class="header">
        <tr>
            <td style="width: 25%;"></td>
            <td style="width: 50%; text-align: center;">
                <div class="church-name">{{ $tenantName }}</div>
            </td>
            <td style="width: 25%; text-align: left;">
                @if (is_file($churchLogoPath))
                    <img src="{{ $churchLogoPath }}" class="logo" alt="">
                @endif
            </td>
        </tr>
    </table>

    <div class="title">إيصال تبرع</div>
    <div class="title-rule"></div>

    <table class="meta">
        <tr>
            <td>رقم الإيصال: <strong dir="ltr">{{ $receiptNumber }}</strong></td>
            <td style="text-align: left;">التاريخ: <strong dir="ltr">{{ $date }}</strong></td>
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
        <div class="caption">المبلغ المستلم</div>
        <div class="number" dir="ltr">{{ $amountNumber }} {{ $currency }}</div>
        <div class="words">{{ $amountWords }}</div>
    </div>

    <table class="signatures">
        <tr>
            <td class="line" style="width: 42%;">&nbsp;</td>
            <td class="gap">&nbsp;</td>
            <td class="line" style="width: 42%;">&nbsp;</td>
        </tr>
        <tr>
            <td class="cell">المستلم: {{ $receivedBy }}</td>
            <td class="gap"></td>
            <td class="cell">التوقيع / الختم</td>
        </tr>
    </table>

    @if (filled($instructions))
        <div class="remote">
            <div class="head">للتبرع عن بعد</div>
            {!! nl2br(e($instructions)) !!}
            <div style="margin-top: 5px; font-weight: bold;">للحصول على ايصال التبرع عبر الواتساب رجاء كتابة رقم الهاتف في سبب التحويل</div>
        </div>
    @endif

    <div class="note">شكراً لمحبتكم وعطائكم &mdash; الرب يعوض تعب محبتكم</div>
</div>
</div>
</body>
</html>
