<?php

namespace DocBot;

class NumberToWords
{
    private static array $units = [
        '', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять',
        'десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать',
        'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать',
    ];

    private static array $tens = [
        '', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят',
        'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто',
    ];

    private static array $hundreds = [
        '', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот',
        'шестьсот', 'семьсот', 'восемьсот', 'девятьсот',
    ];

    private static array $unitsFem = [
        '', 'одна', 'две',
    ];

    /**
     * Конвертирует число (до 999 999 999 999) в русский текст.
     * Копейки добавляются как "XX коп."
     */
    public static function convert(float $number): string
    {
        $number = round($number, 2);
        $intPart = (int) floor($number);
        $kopecks = (int) round(($number - $intPart) * 100);

        if ($intPart === 0) {
            $result = 'ноль рублей';
        } else {
            $result = self::intToWords($intPart) . ' ' . self::rubleWord($intPart);
        }

        $result .= ' ' . sprintf('%02d', $kopecks) . ' ' . self::kopeckWord($kopecks);

        return $result;
    }

    /**
     * Упрощённый вариант — только целая часть, без копеек.
     */
    public static function convertInteger(float $number): string
    {
        $intPart = (int) floor($number);
        if ($intPart === 0) {
            return 'ноль';
        }
        return trim(self::intToWords($intPart));
    }

    private static function intToWords(int $n): string
    {
        if ($n === 0) {
            return '';
        }

        $parts = [];

        // миллиарды
        if ($n >= 1000000000) {
            $billions = (int) floor($n / 1000000000);
            $parts[] = self::group($billions, false) . ' ' . self::pluralize($billions, 'миллиард', 'миллиарда', 'миллиардов');
            $n %= 1000000000;
        }

        // миллионы
        if ($n >= 1000000) {
            $millions = (int) floor($n / 1000000);
            $parts[] = self::group($millions, false) . ' ' . self::pluralize($millions, 'миллион', 'миллиона', 'миллионов');
            $n %= 1000000;
        }

        // тысячи (feminine!)
        if ($n >= 1000) {
            $thousands = (int) floor($n / 1000);
            $parts[] = self::group($thousands, true) . ' ' . self::pluralize($thousands, 'тысяча', 'тысячи', 'тысяч');
            $n %= 1000;
        }

        if ($n > 0) {
            $parts[] = self::group($n, false);
        }

        return implode(' ', $parts);
    }

    private static function group(int $n, bool $feminine): string
    {
        $result = '';

        if ($n >= 100) {
            $result .= self::$hundreds[(int) floor($n / 100)] . ' ';
            $n %= 100;
        }

        if ($n >= 20) {
            $result .= self::$tens[(int) floor($n / 10)] . ' ';
            $n %= 10;
        }

        if ($n > 0) {
            if ($n < 20 && $n >= 3) {
                $result .= self::$units[$n];
            } elseif ($n < 3 && $feminine) {
                $result .= self::$unitsFem[$n];
            } else {
                $result .= self::$units[$n];
            }
        }

        return trim($result);
    }

    private static function pluralize(int $n, string $form1, string $form2, string $form5): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) return $form5;
        if ($n1 > 1 && $n1 < 5) return $form2;
        if ($n1 === 1) return $form1;
        return $form5;
    }

    private static function rubleWord(int $n): string
    {
        return self::pluralize($n, 'рубль', 'рубля', 'рублей');
    }

    private static function kopeckWord(int $n): string
    {
        return self::pluralize($n, 'копейка', 'копейки', 'копеек');
    }
}
