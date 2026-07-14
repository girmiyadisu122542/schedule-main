<?php

namespace App\Faker;

use App\Data\AmharicData;
use App\Helpers\AmharicFaker;
use Faker\Provider\Base;

/**
 * Amharic Provider for Faker
 * Provides Ethiopian-specific data for testing and seeding
 * Uses AmharicData as single source of truth
 */
class AmharicProvider extends Base {

    /**
     * Generate an Amharic male name
     */
    public function amharicMaleName() {
        return AmharicFaker::maleName();
    }

    /**
     * Generate an Amharic female name
     */
    public function amharicFemaleName() {
        return AmharicFaker::femaleName();
    }

    /**
     * Generate an Amharic name (any gender)
     */
    public function amharicName() {
        return AmharicFaker::name();
    }

    /**
     * Generate an Amharic surname
     */
    public function amharicSurname() {
        return AmharicFaker::surname();
    }

    /**
     * Generate a full Amharic name
     */
    public function amharicFullName() {
        return AmharicFaker::fullName();
    }

    /**
     * Generate an Ethiopian region
     */
    public function ethiopianRegion() {
        return AmharicData::getRandomRegion();
    }

    /**
     * Generate an Ethiopian city
     */
    public function ethiopianCity() {
        return AmharicData::getRandomCity();
    }

    /**
     * Generate an Ethiopian subcity
     */
    public function ethiopianSubcity() {
        return static::randomElement(AmharicData::SUBCITIES);
    }

    /**
     * Generate an Ethiopian woreda
     */
    public function ethiopianWoreda() {
        return static::randomElement(AmharicData::WOREDAS);
    }

    /**
     * Generate an Ethiopian kebele
     */
    public function ethiopianKebele() {
        return static::randomElement(AmharicData::KEBELES);
    }

    /**
     * Generate an Ethiopian occupation in Amharic
     */
    public function amharicOccupation() {
        return static::randomElement(AmharicData::OCCUPATIONS);
    }

    /**
     * Generate an Ethiopian phone number
     */
    public function ethiopianPhoneNumber() {
        return AmharicFaker::phoneNumber();
    }

    /**
     * Generate an Ethiopian mobile phone number (shorter format)
     */
    public function ethiopianMobileNumber() {
        // Strip the +251 prefix and add 0 prefix instead
        return '0' . substr(AmharicFaker::phoneNumber(), 4);
    }

    /**
     * Generate an Ethiopian national ID
     */
    public function ethiopianNationalId() {
        return AmharicFaker::nationalId();
    }

    /**
     * Generate an Ethiopian address
     */
    public function ethiopianAddress() {
        return AmharicFaker::ethiopianAddress();
    }

    /**
     * Generate multilingual name (English and Amharic)
     */
    public function multilingualName($englishKey = 'en', $amharicKey = 'am') {
        return AmharicFaker::multilingualName($englishKey, $amharicKey);
    }

    /**
     * Generate multilingual full name (English and Amharic)
     */
    public function multilingualFullName($englishKey = 'en', $amharicKey = 'am') {
        return AmharicFaker::multilingualFullName($englishKey, $amharicKey);
    }
}