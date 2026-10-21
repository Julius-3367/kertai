<?php

namespace App\Support;

final class Phone
{
    /**
     * Normalise a Kenyan mobile number to 2547XXXXXXXX / 2541XXXXXXXX.
     * Returns null when the input is not a recognisable mobile number.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (preg_match('/^0([17]\d{8})$/', $digits, $m)) {
            return '254'.$m[1];
        }

        if (preg_match('/^([17]\d{8})$/', $digits, $m)) {
            return '254'.$m[1];
        }

        if (preg_match('/^254([17]\d{8})$/', $digits)) {
            return $digits;
        }

        return null;
    }

    public static function display(?string $phone): string
    {
        $normalized = self::normalize($phone);

        return $normalized ? '0'.substr($normalized, 3) : (string) $phone;
    }

    /**
     * Find mobile numbers inside free text such as a bank narrative.
     *
     * @return list<string>
     */
    public static function extractAll(string $text): array
    {
        preg_match_all('/(?:\+?254|0)[17]\d{8}/', $text, $matches);

        return array_values(array_unique(array_filter(array_map(self::normalize(...), $matches[0]))));
    }
}
