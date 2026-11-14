<?php

namespace App\Support;

/**
 * Writes a money amount out in Arabic words for receipts, e.g.
 * 1250.50 EGP → "فقط ألف ومائتان وخمسون جنيهاً مصرياً وخمسون قرشاً لا غير".
 * Handles amounts up to the billions; every supported currency noun is
 * masculine, so the masculine number forms are used throughout.
 */
class ArabicAmountInWords
{
    private const ONES = [1 => 'واحد', 2 => 'اثنان', 3 => 'ثلاثة', 4 => 'أربعة', 5 => 'خمسة', 6 => 'ستة', 7 => 'سبعة', 8 => 'ثمانية', 9 => 'تسعة', 10 => 'عشرة'];

    private const TEENS = [11 => 'أحد عشر', 12 => 'اثنا عشر', 13 => 'ثلاثة عشر', 14 => 'أربعة عشر', 15 => 'خمسة عشر', 16 => 'ستة عشر', 17 => 'سبعة عشر', 18 => 'ثمانية عشر', 19 => 'تسعة عشر'];

    private const TENS = [2 => 'عشرون', 3 => 'ثلاثون', 4 => 'أربعون', 5 => 'خمسون', 6 => 'ستون', 7 => 'سبعون', 8 => 'ثمانون', 9 => 'تسعون'];

    private const HUNDREDS = [1 => 'مائة', 2 => 'مائتان', 3 => 'ثلاثمائة', 4 => 'أربعمائة', 5 => 'خمسمائة', 6 => 'ستمائة', 7 => 'سبعمائة', 8 => 'ثمانمائة', 9 => 'تسعمائة'];

    /** @var array<int, array{0: string, 1: string, 2: string}> singular, dual, plural */
    private const SCALES = [
        1 => ['ألف', 'ألفان', 'آلاف'],
        2 => ['مليون', 'مليونان', 'ملايين'],
        3 => ['مليار', 'ملياران', 'مليارات'],
    ];

    /**
     * Currency nouns for the major and minor unit: singular, dual, plural,
     * and the accusative singular used after numbers from 11 up.
     *
     * @var array<string, array{major: array<int, string>, minor: array<int, string>}>
     */
    private const CURRENCIES = [
        'EGP' => ['major' => ['جنيه مصري', 'جنيهان مصريان', 'جنيهات مصرية', 'جنيهاً مصرياً'], 'minor' => ['قرش', 'قرشان', 'قروش', 'قرشاً']],
        'USD' => ['major' => ['دولار أمريكي', 'دولاران أمريكيان', 'دولارات أمريكية', 'دولاراً أمريكياً'], 'minor' => ['سنت', 'سنتان', 'سنتات', 'سنتاً']],
        'EUR' => ['major' => ['يورو', 'يورو', 'يورو', 'يورو'], 'minor' => ['سنت', 'سنتان', 'سنتات', 'سنتاً']],
        'GBP' => ['major' => ['جنيه إسترليني', 'جنيهان إسترلينيان', 'جنيهات إسترلينية', 'جنيهاً إسترلينياً'], 'minor' => ['بنس', 'بنسان', 'بنسات', 'بنساً']],
        'SAR' => ['major' => ['ريال سعودي', 'ريالان سعوديان', 'ريالات سعودية', 'ريالاً سعودياً'], 'minor' => ['هللة', 'هللتان', 'هللات', 'هللة']],
        'AED' => ['major' => ['درهم إماراتي', 'درهمان إماراتيان', 'دراهم إماراتية', 'درهماً إماراتياً'], 'minor' => ['فلس', 'فلسان', 'فلوس', 'فلساً']],
    ];

    public static function convert(float $amount, string $currency): string
    {
        $currency = strtoupper($currency);
        $cents = (int) round(abs($amount) * 100);
        $major = intdiv($cents, 100);
        $minor = $cents % 100;

        $names = self::CURRENCIES[$currency] ?? null;
        $parts = [];

        if ($major > 0 || $minor === 0) {
            $parts[] = self::unit($major, $names['major'] ?? null, $currency);
        }

        if ($minor > 0) {
            $parts[] = self::unit($minor, $names['minor'] ?? null, null);
        }

        return 'فقط '.implode(' و', $parts).' لا غير';
    }

    /**
     * The currency's Arabic name for a receipt line, e.g. "جنيه مصري".
     */
    public static function currencyName(string $currency): string
    {
        return self::CURRENCIES[strtoupper($currency)]['major'][0] ?? strtoupper($currency);
    }

    /**
     * @param  array<int, string>|null  $forms
     */
    private static function unit(int $number, ?array $forms, ?string $fallback): string
    {
        if ($forms === null) {
            return self::integer($number).($fallback !== null ? ' '.$fallback : '');
        }

        if ($number === 1) {
            return $forms[0].' واحد';
        }

        if ($number === 2) {
            return $forms[1];
        }

        $last = $number % 100;

        if ($last >= 3 && $last <= 10) {
            return self::integer($number).' '.$forms[2];
        }

        // A round number (100, 2,000, ...) takes the plain singular.
        if ($last === 0) {
            return self::integer($number).' '.$forms[0];
        }

        return self::integer($number).' '.$forms[3];
    }

    private static function integer(int $number): string
    {
        if ($number === 0) {
            return 'صفر';
        }

        $groups = [];

        for ($scale = 0; $number > 0; $scale++) {
            $groups[$scale] = $number % 1000;
            $number = intdiv($number, 1000);
        }

        $parts = [];

        foreach (array_reverse($groups, true) as $scale => $value) {
            if ($value === 0) {
                continue;
            }

            $parts[] = $scale === 0 ? self::belowThousand($value) : self::scaled($value, $scale);
        }

        return implode(' و', $parts);
    }

    private static function scaled(int $value, int $scale): string
    {
        [$singular, $dual, $plural] = self::SCALES[$scale];

        if ($value === 1) {
            return $singular;
        }

        if ($value === 2) {
            return $dual;
        }

        $last = $value % 100;

        return self::belowThousand($value).' '.($last >= 3 && $last <= 10 ? $plural : $singular);
    }

    private static function belowThousand(int $value): string
    {
        $parts = [];

        if ($value >= 100) {
            $parts[] = self::HUNDREDS[intdiv($value, 100)];
        }

        $rest = $value % 100;

        if ($rest === 0) {
            return implode(' و', $parts);
        }

        if ($rest <= 10) {
            $parts[] = self::ONES[$rest];
        } elseif ($rest <= 19) {
            $parts[] = self::TEENS[$rest];
        } else {
            $unit = $rest % 10;
            $parts[] = ($unit > 0 ? self::ONES[$unit].' و' : '').self::TENS[intdiv($rest, 10)];
        }

        return implode(' و', $parts);
    }
}
