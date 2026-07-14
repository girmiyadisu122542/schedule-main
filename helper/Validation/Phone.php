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