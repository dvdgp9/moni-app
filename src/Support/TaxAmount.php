<?php
declare(strict_types=1);

namespace Moni\Support;

use InvalidArgumentException;

final class TaxAmount
{
    /** Accept Spanish currency input and ungrouped decimal-dot input; never silently coerce invalid text. */
    public static function parse(string $input): float
    {
        $input = trim(str_replace(["\xc2\xa0", "\xe2\x80\xaf", '€', ' '], '', $input));
        if (preg_match('/^-?\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/D', $input)) {
            $input = str_replace('.', '', $input);
        }
        $input = str_replace(',', '.', $input);
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $input)) {
            throw new InvalidArgumentException('Introduce un importe válido, por ejemplo 1.234,56.');
        }
        $value = (float)$input;
        if (!is_finite($value) || abs($value) > 999999999.99) {
            throw new InvalidArgumentException('El importe supera el límite admitido.');
        }
        return round($value, 2);
    }

    public static function format(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public static function copy(float $value): string
    {
        return number_format($value, 2, ',', '');
    }
}
