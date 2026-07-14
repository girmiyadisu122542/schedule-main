<?php

namespace App\Helpers;

use App\Data\AmharicData;

class AmharicFaker {
    /**
     * Get a random male name
     */
    public static function maleName(): string {
        return AmharicData::getRandomMaleName();
    }

    /**
     * Get a random female name
     */
    public static function femaleName(): string {
        return AmharicData::getRandomFemaleName();
    }

    /**
     * Get a random name (any gender)
     */
    public static function name(): string {
        return AmharicData::getRandomName();
    }

    /**
     * Get a random surname
     */
    public static function surname(): string {
        return AmharicData::getRandomSurname();
    }

    public static function fullName(): string {
        return static::name() . ' ' . static::surname();
    }

    /**
     * Generate Ethiopian phone number
     */
    public static function phoneNumber(): string {
        $prefix = AmharicData::getRandomPhonePrefix();
        return $prefix . str_pad(random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }
    /**
     * Generate Ethiopian national ID
     */
    public static function nationalId(): string {
        return str_pad(random_int(0, 9999999999999999), NATIONAL_ID_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Generate multilingual name array
     */
    public static function multilingualName(string $englishKey = 'en', string $amharicKey = 'am'): array {
        $name = static::name();

        return [
            $englishKey => toEnglish($name),
            $amharicKey => $name,
        ];
    }

    /**
     * Generate multilingual full name array
     */
    public static function multilingualFullName(string $englishKey = 'en', string $amharicKey = 'am'): array {
        $name = static::fullName();

        return [
            $englishKey => toEnglish($name),
            $amharicKey => $name,
        ];
    }

    /**
     * Generate an Ethiopian address
     */
    public static function ethiopianAddress(): array {
        return [
            'region' => AmharicData::getRandomRegion(),
            'city' => AmharicData::getRandomCity(),
            'subcity' => AmharicData::SUBCITIES[array_rand(AmharicData::SUBCITIES)],
            'woreda' => AmharicData::WOREDAS[array_rand(AmharicData::WOREDAS)],
            'kebele' => AmharicData::KEBELES[array_rand(AmharicData::KEBELES)],
            'house_number' => fake()->buildingNumber(),
        ];
    }

    /**
     * Get a random amharic words
     */
    public static function randomWords(int $count): string {
        return AmharicData::getRandomWords($count);
    }

    /**
     * Generate appropriate names based on gender using AmharicData methods
     *
     * @param int $gender
     * @param string $english
     * @param string $amharic
     * @return array
     */
    public static function nameByGender(int $gender, string $english, string $amharic): array {
        if ($gender === MALE) {
            $name = self::maleName();
            return [
                $english => toEnglish($name),
                $amharic => $name,
            ];
        } else {
            $name = self::femaleName();
            return [
                $english => toEnglish($name),
                $amharic => $name,
            ];
        }
    }

    public static function email($name = null, $keep = false): string {
        if (!$name) {
            $name = toEnglish(self::name());
        }

        $return = $name;

        if (!$keep) {
            $return .= generateRandomNumber(6);
        }

        return $return . '@schedule.com';
    }

    public static function inclusiveness(): string {
        return AmharicData::getRandomInclusiveness();
    }
}
