<?php

use Helper\Rule\CustomRule;
use Helper\Validation\Phone;

if (!function_exists('nationalIdisMandatory')) {
    function nationalIdisMandatory(): string {
        return NATIONAL_ID_IS_GLOBALLY_MANDATORY ? 'required' : 'sometimes';
    }
}

if (!function_exists('maxNationalIdLength')) {
    function maxNationalIdLength(): string {
        return 'digits:' . NATIONAL_ID_LENGTH;
    }
}

if (!function_exists('nationalIdValidation')) {
    function nationalIdValidation(): array {
        return [
            'string',
            maxNationalIdLength(),
            nationalIdisMandatory(),
        ];
    }
}


if (!function_exists('minimumNameLength')) {
    function minimumNameLength(int $min = MIN_NAME_LENGTH): string {
        return 'min:' . $min;
    }
}

if (!function_exists('maximumNameLength')) {
    function maximumNameLength(int $max = MAX_NAME_LENGTH): string {
        return 'max:' . $max;
    }
}

if (!function_exists('nameValidation')) {
    function nameValidation(int $min = MIN_NAME_LENGTH, int $max = MAX_NAME_LENGTH): array {
        return [
            'string',
            minimumNameLength($min),
            maximumNameLength($max),
        ];
    }
}

if (!function_exists('maxEmailLength')) {
    function maxEmailLength(): string {
        return 'max:' . MAX_EMAIL_LENGTH;
    }
}

if (!function_exists('maxPhoneLength')) {
    function maxPhoneLength(): string {
        return 'max:' . MAX_PHONE_LENGTH;
    }
}

if (!function_exists('phoneNumberValidation')) {
    function phoneNumberValidation(): array {
        return [
            'string',
            maxPhoneLength(),
            Phone::rule(),
        ];
    }
}

if (!function_exists('maxPhotoUploadSize')) {
    function maxPhotoUploadSize(): string {
        return 'max:' . round(MAXIMUM_PHOTO_UPLOAD_SIZE * 1024);
    }
}

if (!function_exists('photoValidation')) {
    function photoValidation(): array {
        return [
            'image',
            'mimes:jpg,jpeg,png',
            maxPhotoUploadSize(),
        ];
    }
}

if (!function_exists('allowedEmails')) {
    function allowedEmails(): string {
        return 'ends_with:' . implode(',', ALLOWED_EMAIL_DOMAINS);
    }
}

if (!function_exists('emailValidation')) {
    function emailValidation(): array {
        return [
            'email',
            maxEmailLength(),
            // allowedEmails(),
        ];
    }
}

if (!function_exists('dateFormat')) {
    function dateFormat(): string {
        return 'date_format:' . DATE_FORMAT;
    }
}

if (!function_exists('birthdateValidation')) {
    function birthdateValidation(): array {
        return [
            'date',
            'before:today',
            dateFormat(),
        ];
    }
}

if (!function_exists('startDateValidation')) {
    /**
     * Validate start date.
     *
     * @param bool $afterOrEqualToday Whether to require the date to be after or equal to today.
     * @return array
     */
    function startDateValidation(bool $afterOrEqualToday = true): array {
        $rules = ['date', dateFormat()];
        if ($afterOrEqualToday) {
            $rules[] = 'after_or_equal:today';
        }
        return $rules;
    }
}
if (!function_exists('endDateValidation')) {
    function endDateValidation(string $startDateField = 'start_date'): array {
        return [
            'date',
            "after:$startDateField",
            dateFormat(),
        ];
    }
}

if (!function_exists('richTextValidation')) {
    /**
     * Validate rich text length (ignoring HTML tags).
     *
     * @param int $min
     * @param int $max
     * @param bool $required
     * @return array
     */
    function richTextValidation(int $min = RICH_TEXT_MIN_LENGTH, int $max = RICH_TEXT_MAX_LENGTH, bool $required = true, array $messages = []): array {
        $rules = [];
        $rules[] = $required ? 'required' : 'nullable';
        $rules[] = CustomRule::make('rich_text_length', $messages, function ($attribute, $value) use ($min, $max, $required) {
            if (!$required && ($value === null || $value === '')) {
                return true;
            }
            $plainText = preg_replace('/<[^>]*>/', '', $value);
            $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plainText = preg_replace('/\\s+/', ' ', $plainText);
            $plainText = trim($plainText);
            return mb_strlen($plainText) >= $min && mb_strlen($plainText) <= $max;
        });
        return $rules;
    }
}

if (!function_exists('fileValidation')) {
    /**
     * Validation rules for general file uploads.
     *
     * @param int $maxSizeInKB
     * @param array $allowedMimeTypes
     * @return array
     */
    function fileValidation(int $maxSizeInKB = GENERAL_FILE_MAX_SIZE, array $allowedMimeTypes = EXTENSIONS, bool $isRequired = true): array {
        return [
            $isRequired ? 'required' : 'nullable',
            'file',
            'max:' . $maxSizeInKB,
            'mimes:' . implode(',', $allowedMimeTypes),
        ];
    }
}

if (!function_exists('passwordValidation')) {
    /**
     * Validation rules for password.
     *
     * @param bool $isRequired
     * @return array
     */
    function passwordValidation(bool $isRequired = true, bool $confirmed = false): array {
        $rules = [];
        if ($isRequired) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }
        $rules[] = 'string';
        if ($confirmed) {
            $rules[] = 'confirmed';
        }
        $rules[] = \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()->symbols();
        return $rules;
    }
}

if (!function_exists('latitudeValidation')) {
    function latitudeValidation(): array {
        return [
            'nullable', 'string', 'min:' . MIN_LATITUDE_LENGTH, 'max:' . MAX_LATITUDE_LENGTH,
            'regex:/^(\+|-)?(?:90(?:(?:\.0{1,6})?)|(?:[0-9]|[1-8][0-9])(?:(?:\.[0-9]{1,8})?))$/',
        ];
    }
}

if (!function_exists('longitudeValidation')) {
    function longitudeValidation(): array {
        return [
            'nullable', 'string', 'min:' . MIN_LATITUDE_LENGTH, 'max:' . MAX_LATITUDE_LENGTH,
            'regex:/^(\+|-)?(?:180(?:(?:\.0{1,6})?)|(?:[0-9]|[1-9][0-9]|1[0-7][0-9])(?:(?:\.[0-9]{1,8})?))$/',
        ];
    }
}