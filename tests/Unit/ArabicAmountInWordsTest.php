<?php

namespace Tests\Unit;

use App\Support\ArabicAmountInWords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArabicAmountInWordsTest extends TestCase
{
    /**
     * @return array<string, array{0: float, 1: string, 2: string}>
     */
    public static function amounts(): array
    {
        return [
            'one' => [1, 'EGP', 'فقط جنيه مصري واحد لا غير'],
            'two' => [2, 'EGP', 'فقط جنيهان مصريان لا غير'],
            'three to ten take the plural' => [5, 'EGP', 'فقط خمسة جنيهات مصرية لا غير'],
            'eleven and up take the accusative singular' => [11, 'EGP', 'فقط أحد عشر جنيهاً مصرياً لا غير'],
            'a round hundred takes the plain singular' => [100, 'EGP', 'فقط مائة جنيه مصري لا غير'],
            'a hundred and one' => [101, 'EGP', 'فقط مائة وواحد جنيهاً مصرياً لا غير'],
            'thousands and piasters' => [1250.5, 'EGP', 'فقط ألف ومائتان وخمسون جنيهاً مصرياً وخمسون قرشاً لا غير'],
            'a round two thousand in dollars' => [2000, 'USD', 'فقط ألفان دولار أمريكي لا غير'],
            'a hundred and three thousand' => [103000, 'EGP', 'فقط مائة وثلاثة آلاف جنيه مصري لا غير'],
            'a million euros' => [1000000, 'EUR', 'فقط مليون يورو لا غير'],
            'only piasters' => [0.5, 'EGP', 'فقط خمسون قرشاً لا غير'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_it_writes_the_amount_in_arabic_words(float $amount, string $currency, string $expected): void
    {
        $this->assertSame($expected, ArabicAmountInWords::convert($amount, $currency));
    }

    public function test_an_unknown_currency_falls_back_to_its_code(): void
    {
        $this->assertSame('فقط خمسة وعشرون JPY لا غير', ArabicAmountInWords::convert(25, 'jpy'));
        $this->assertSame('JPY', ArabicAmountInWords::currencyName('jpy'));
        $this->assertSame('جنيه مصري', ArabicAmountInWords::currencyName('EGP'));
    }
}
