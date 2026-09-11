<?php

namespace App\Enums;

enum DepreciationMethod: string
{
    case StraightLine = 'straight_line';

    /**
     * Straight-line is the only method implemented so far (the spec's
     * minimum bar) — add cases here (declining balance, etc) and branch
     * in this method rather than scattering method checks elsewhere.
     */
    public function monthlyAmount(float $cost, float $salvageValue, int $usefulLifeYears): float
    {
        if ($usefulLifeYears <= 0) {
            return 0.0;
        }

        return match ($this) {
            self::StraightLine => round(($cost - $salvageValue) / ($usefulLifeYears * 12), 2),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::StraightLine => 'Straight-line',
        };
    }
}
