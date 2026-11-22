<?php

namespace App\Services;

class CurrencyAmountInWords
{
    private const ONES = [
        'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen',
    ];

    private const TENS = [
        2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
        6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety',
    ];

    public static function inr(float $amount): string
    {
        $paise = (int) round(($amount - floor($amount)) * 100);
        $rupees = (int) floor($amount);
        if ($paise === 100) {
            $rupees++;
            $paise = 0;
        }

        $words = self::integer($rupees) . ' rupees';
        if ($paise > 0) {
            $words .= ' and ' . self::integer($paise) . ' paise';
        }

        return ucfirst($words) . ' only';
    }

    private static function integer(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }
        if ($number < 100) {
            $tens = self::TENS[intdiv($number, 10)];
            $ones = $number % 10;
            return $tens . ($ones ? ' ' . self::ONES[$ones] : '');
        }
        if ($number < 1000) {
            $remainder = $number % 100;
            return self::ONES[intdiv($number, 100)] . ' hundred' . ($remainder ? ' ' . self::integer($remainder) : '');
        }

        foreach ([10000000 => 'crore', 100000 => 'lakh', 1000 => 'thousand'] as $scale => $unit) {
            if ($number >= $scale) {
                $remainder = $number % $scale;
                return self::integer(intdiv($number, $scale)) . ' ' . $unit . ($remainder ? ' ' . self::integer($remainder) : '');
            }
        }

        return (string) $number;
    }
}
