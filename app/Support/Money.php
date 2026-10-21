<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is stored as integer cents everywhere. Forms send shillings as text
 * ("1,500" or "1500.50"); this class is the only place that converts.
 */
final class Money
{
    public static function toCents(string|int|float|null $shillings): int
    {
        if ($shillings === null || $shillings === '') {
            return 0;
        }

        $clean = str_replace([',', ' ', 'KES', 'Ksh', 'KSh'], '', (string) $shillings);

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $clean)) {
            throw new InvalidArgumentException("Not a money amount: {$shillings}");
        }

        [$whole, $fraction] = array_pad(explode('.', $clean), 2, '0');
        $negative = str_starts_with($whole, '-');
        $cents = abs((int) $whole) * 100 + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    public static function format(int $cents, bool $withCurrency = true): string
    {
        $value = number_format(abs($cents) / 100, 2);
        $sign = $cents < 0 ? '-' : '';

        return $withCurrency ? "{$sign}KES {$value}" : "{$sign}{$value}";
    }

    public static function toShillings(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Validation rule for money fields entered in shillings.
     *
     * @return array<int, string>
     */
    public static function rule(bool $required = true, int $minCents = 1): array
    {
        $min = $minCents / 100;

        return [
            $required ? 'required' : 'nullable',
            'numeric',
            "min:{$min}",
            'max:100000000',
            'decimal:0,2',
        ];
    }
}
