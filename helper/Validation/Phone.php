<?php

namespace Helper\Validation;

use Illuminate\Validation\Rule;

class Phone {
    /**
     * Ethiopian phone number pattern
     * Must start with 09 or 07 and have 8 additional digits (total 10 digits)
     */
    public const ETHIOPIAN_PATTERN = '/^(?:\+251|251|0)(9|7)\d{8}$/';

    /**
     * Get validation rule for Ethiopian phone number format
     * This can be used as a single item in validation arrays
     *
     * @return string
     */
    public static function rule(): string {
        return 'regex:' . static::ETHIOPIAN_PATTERN;
    }

    /**
     * Alias for rule() method for backward compatibility
     *
     * @return string
     */
    public static function ethiopianRule(): string {
        return static::rule();
    }

    /**
     * Reduce a number to the single spelling this system stores.
     *
     * The pattern above accepts `+251…`, `251…` and `0…` for the same
     * subscriber, so without this the `unique` rules compare spellings rather
     * than numbers: `0912345678` and `+251912345678` are the same phone and
     * both would be accepted as two separate accounts. Every stored number is
     * normalized to the local `0…` form — the spelling the seeded users already
     * use, so no existing row has to move.
     *
     * Formatting is dropped too, which makes `+251 91 234 5678` valid input
     * rather than a "format is invalid" the user cannot see the reason for.
     *
     * A number this does not recognise is returned digits-only and left to fail
     * the pattern — normalizing must never turn an invalid number into a valid
     * one.
     *
     * @param string $phone
     * @return string
     */
    public static function normalize(string $phone): string {
        $digits = static::clean($phone);

        if ($digits === '') {
            return $phone;
        }

        if (str_starts_with($digits, '251')) {
            $digits = substr($digits, 3);
        }

        if (!str_starts_with($digits, '0')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /**
     * Validate if phone number matches Ethiopian format
     *
     * @param string $phone
     * @return bool
     */
    public static function isValidEthiopian(string $phone): bool {
        return preg_match(static::ETHIOPIAN_PATTERN, $phone) === 1;
    }

    /**
     * Format phone number for display (add spaces for readability)
     *
     * @param string $phone
     * @return string
     */
    public static function format(string $phone): string {
        if (static::isValidEthiopian($phone)) {
            return substr($phone, 0, 2) . ' ' . substr($phone, 2, 4) . ' ' . substr($phone, 6);
        }

        return $phone;
    }

    /**
     * Clean phone number (remove spaces and formatting)
     *
     * @param string $phone
     * @return string
     */
    public static function clean(string $phone): string {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}