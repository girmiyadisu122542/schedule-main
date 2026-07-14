<?php

use Andegna\DateTimeFactory;
use Andegna\Validator\DateValidator as EthiopianDateValidator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Translation\Front\Amharic;

if (!function_exists('normalizeCalendarPreferenceInput')) {
    function normalizeCalendarPreferenceInput($calendarSystemInput): ?int {
        if ($calendarSystemInput === null) {
            return null;
        }

        if (is_int($calendarSystemInput) || (is_string($calendarSystemInput) && ctype_digit(trim($calendarSystemInput)))) {
            $value = (int) $calendarSystemInput;
            return in_array($value, array_keys(CALENDAR_SYSTEMS), true) ? $value : null;
        }

        $value = strtolower(trim((string) $calendarSystemInput));
        if ($value === '') {
            return null;
        }

        return match ($value) {
            'gregorian', 'gc', 'g', '1' => CALENDAR_SYSTEM_GREGORIAN,
            'ethiopian', 'ethiopic', 'ec', 'et', '2' => CALENDAR_SYSTEM_ETHIOPIAN,
            default => null,
        };
    }
}

if (!function_exists('resolveDateRequest')) {
    function resolveDateRequest($request = null): ?Request {
        if ($request instanceof Request) {
            return $request;
        }

        try {
            $resolved = request();
            return $resolved instanceof Request ? $resolved : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('resolveCalendarPreference')) {
    function resolveCalendarPreference($calendarSystemInput = null, $request = null): int {
        $explicit = normalizeCalendarPreferenceInput($calendarSystemInput);
        if ($explicit !== null) {
            return $explicit;
        }

        $request = resolveDateRequest($request);
        if ($request) {
            $candidates = [
                $request->header('calendar_preference'),
                $request->header('calendar_type'),
                $request->header('calendar-type'),
                $request->header('calendar_system'),
                $request->header('calendar-system'),
                $request->input('calendar_preference'),
                $request->input('calendar_type'),
                $request->input('calendar_system'),
                $request->input('calendar-system'),
            ];

            foreach ($candidates as $candidate) {
                $resolved = normalizeCalendarPreferenceInput($candidate);
                if ($resolved !== null) {
                    return $resolved;
                }
            }

            $language = getCurrentLanguage($request);

            if (is_string($language) && str_starts_with(strtolower(trim($language)), Amharic::getKey())) {
                return CALENDAR_SYSTEM_ETHIOPIAN;
            }
        }

        return DEFAULT_CALENDAR_SYSTEM;
    }
}

if (!function_exists('getCurrentCalendarPreference')) {
    function getCurrentCalendarPreference($request = null): int {
        return resolveCalendarPreference(null, $request);
    }
}

if (!function_exists('getCalendarSystemName')) {
    function getCalendarSystemName(int $calendarSystem): string {
        return $calendarSystem === CALENDAR_SYSTEM_ETHIOPIAN ? 'ethiopian' : 'gregorian';
    }
}

if (!function_exists('isEthiopianLeapYear')) {
    function isEthiopianLeapYear(int $year): bool {
        return ($year + 1) % 4 === 0;
    }
}

if (!function_exists('validateGregorianDateParts')) {
    function validateGregorianDateParts(int $year, int $month, int $day): bool {
        return checkdate($month, $day, $year);
    }
}

if (!function_exists('validateEthiopianDateParts')) {
    function validateEthiopianDateParts(int $year, int $month, int $day): bool {
        try {
            return (new EthiopianDateValidator($day, $month, $year))->isValid();
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('parseDateStringParts')) {
    function parseDateStringParts(string $value): ?array {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(['/', '.'], '-', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        $patterns = [
            '/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T](\d{1,2})(?::(\d{1,2}))?(?::(\d{1,2}))?)?$/',
            '/^(\d{1,2})-(\d{1,2})-(\d{4})(?:[ T](\d{1,2})(?::(\d{1,2}))?(?::(\d{1,2}))?)?$/',
        ];

        foreach ($patterns as $index => $pattern) {
            if (!preg_match($pattern, $value, $matches)) {
                continue;
            }

            if ($index === 0) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];
            } else {
                $day = (int) $matches[1];
                $month = (int) $matches[2];
                $year = (int) $matches[3];
            }

            return [
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'hour' => isset($matches[4]) ? (int) $matches[4] : 0,
                'minute' => isset($matches[5]) ? (int) $matches[5] : 0,
                'second' => isset($matches[6]) ? (int) $matches[6] : 0,
            ];
        }

        return null;
    }
}

if (!function_exists('resolveDateTimezone')) {
    function resolveDateTimezone(?string $timezone = null): DateTimeZone {
        if (!$timezone) {
            if (function_exists('config')) {
                try {
                    $configuredTimezone = config('app.timezone');
                    if (is_string($configuredTimezone) && $configuredTimezone !== '') {
                        $timezone = $configuredTimezone;
                    }
                } catch (\Throwable $e) {
                    $timezone = null;
                }
            }

            $timezone = $timezone ?: 'Africa/Addis_Ababa';
        }

        try {
            return new DateTimeZone($timezone);
        } catch (\Throwable $e) {
            return new DateTimeZone('Africa/Addis_Ababa');
        }
    }
}

if (!function_exists('convertEthiopianToGregorian')) {
    function convertEthiopianToGregorian(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?string $timezone = null
    ): ?Carbon {
        if (!validateEthiopianDateParts($year, $month, $day)) {
            return null;
        }

        try {
            $ethiopianDate = DateTimeFactory::of(
                $year,
                $month,
                $day,
                $hour,
                $minute,
                $second,
                resolveDateTimezone($timezone)
            );

            return Carbon::instance($ethiopianDate->toGregorian());
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('parseGregorianDateValue')) {
    function parseGregorianDateValue($value, ?string $timezone = null): ?Carbon {
        if ($value == null) {
            return $value;
        }

        try {
            if ($value instanceof Carbon) {
                return $value->copy();
            }

            if ($value instanceof DateTimeInterface) {
                return Carbon::instance(DateTime::createFromInterface($value));
            }

            if (is_numeric($value) && (int) $value > 1000000) {
                return Carbon::createFromTimestamp((int) $value, resolveDateTimezone($timezone));
            }

            if (is_string($value) && trim($value) !== '') {
                return Carbon::parse($value, resolveDateTimezone($timezone));
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}

if (!function_exists('parseEthiopianDateToGregorian')) {
    function parseEthiopianDateToGregorian($value, ?string $timezone = null): ?Carbon {
        if (!is_string($value)) {
            return null;
        }

        $parts = parseDateStringParts($value);
        if (!$parts) {
            return null;
        }

        return convertEthiopianToGregorian(
            year: $parts['year'],
            month: $parts['month'],
            day: $parts['day'],
            hour: $parts['hour'],
            minute: $parts['minute'],
            second: $parts['second'],
            timezone: $timezone
        );
    }
}

if (!function_exists('parseDateToGregorian')) {
    function parseDateToGregorian($value, $calendarSystem = null, $request = null, ?string $timezone = null): ?Carbon {
        if ($value === null || $value === '') {
            return null;
        }

        $preferredCalendar = resolveCalendarPreference($calendarSystem, $request);

        if ($preferredCalendar === CALENDAR_SYSTEM_ETHIOPIAN) {
            $ethiopian = parseEthiopianDateToGregorian($value, $timezone);
            if ($ethiopian) {
                return $ethiopian;
            }

            return parseGregorianDateValue($value, $timezone);
        }

        $gregorian = parseGregorianDateValue($value, $timezone);
        if ($gregorian) {
            return $gregorian;
        }

        return parseEthiopianDateToGregorian($value, $timezone);
    }
}

if (!function_exists('validateDateByCalendar')) {
    function validateDateByCalendar($value, $calendarSystem = null, $request = null, ?string $timezone = null): bool {
        return parseDateToGregorian($value, $calendarSystem, $request, $timezone) instanceof Carbon;
    }
}

if (!function_exists('convertGregorianToEthiopianObject')) {
    function convertGregorianToEthiopianObject($date, ?string $timezone = null) {
        $gregorian = parseGregorianDateValue($date, $timezone);
        if (!$gregorian) {
            return null;
        }

        try {
            return DateTimeFactory::fromDateTime($gregorian->toDateTime());
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('convertGregorianToEthiopianParts')) {
    function convertGregorianToEthiopianParts($date, ?string $timezone = null): ?array {
        $ethiopianDate = convertGregorianToEthiopianObject($date, $timezone);
        if (!$ethiopianDate) {
            return null;
        }

        return [
            'year' => $ethiopianDate->getYear(),
            'month' => $ethiopianDate->getMonth(),
            'day' => $ethiopianDate->getDay(),
            'hour' => $ethiopianDate->getHour(),
            'minute' => $ethiopianDate->getMinute(),
            'second' => $ethiopianDate->getSecond(),
        ];
    }
}

if (!function_exists('convertGregorianToEthiopian')) {
    function convertGregorianToEthiopian($date, string $format = DATE_FORMAT, ?string $timezone = null): ?string {
        $ethiopianDate = convertGregorianToEthiopianObject($date, $timezone);
        if (!$ethiopianDate) {
            return null;
        }

        try {
            return $ethiopianDate->format($format);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('formatDateByCalendar')) {
    function formatDateByCalendar(
        $date,
        $calendarSystem = null,
        string $format = DATE_FORMAT,
        ?string $gregorianFormat = DATE_FORMAT,
        ?string $ethiopianFormat = null,
        $request = null,
        ?string $timezone = null
    ): ?string {
        $gregorianDate = parseGregorianDateValue($date, $timezone);

        if (!$gregorianDate) {
            return null;
        }

        $calendarSystem = resolveCalendarPreference($calendarSystem, $request);

        if ($calendarSystem == CALENDAR_SYSTEM_ETHIOPIAN) {
            return convertGregorianToEthiopian($gregorianDate, $ethiopianFormat ?? $format, $timezone);
        }

        return $gregorianDate->format($gregorianFormat ?? $format);
    }
}

if (!function_exists('convertDateBetweenCalendars')) {
    function convertDateBetweenCalendars(
        $value,
        int $fromCalendarSystem,
        int $toCalendarSystem,
        string $outputFormat = DATE_FORMAT,
        ?string $timezone = null
    ): ?string {
        $gregorian = parseDateToGregorian($value, $fromCalendarSystem, null, $timezone);
        if (!$gregorian) {
            return null;
        }

        return formatDateByCalendar(
            $gregorian,
            $toCalendarSystem,
            $outputFormat,
            $outputFormat,
            null,
            $timezone
        );
    }
}

if (!function_exists('calculateWorkingEndDate')) {
    function calculateWorkingEndDate(string $startDate, int $days = 1): string {
        $date = Carbon::parse($startDate);

        return match (true) {
            $days <= 0 => $startDate,
            default => (function () use ($date, $days) {
                $remaining = $days;
                while ($remaining > 0) {
                    $date->addDay();
                    if (!$date->isWeekend()) {
                        $remaining--;
                    }
                }
                return $date->toDateString();
            })(),
        };
    }
}

if (!function_exists('isDatePlusDaysFuture')) {
    function isDatePlusDaysFuture($dateInput, int $days): bool {
        $date = parseDateToGregorian($dateInput);
        if (!$date) {
            throw new InvalidArgumentException('Invalid date input');
        }

        $expiryDate = $date->copy()->addDays($days)->startOfDay();
        return $expiryDate->greaterThanOrEqualTo(Carbon::today());
    }
}

if (!function_exists('isDateTodayOrFuture')) {
    function isDateTodayOrFuture($dateInput): bool {
        $date = parseDateToGregorian($dateInput);
        if (!$date) {
            throw new InvalidArgumentException('Invalid date input');
        }

        return $date->startOfDay()->greaterThanOrEqualTo(Carbon::today());
    }
}

if (!function_exists('calculateDaysBetween')) {
    function calculateDaysBetween($startDate, $endDate): int {
        $start = parseDateToGregorian($startDate)?->startOfDay();
        $end = parseDateToGregorian($endDate)?->startOfDay();

        if (!$start || !$end) {
            return 0;
        }

        return $start->diffInDays($end);
    }
}

if (!function_exists('parseTime')) {
    function parseTime(null|string $time): ?Carbon {
        if (!$time) {
            return null;
        }

        foreach (['H:i', 'H:i:s'] as $format) {
            try {
                return Carbon::createFromFormat($format, $time);
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }
}

if (!function_exists('formatTimeHm')) {
    /**
     * normalize a stored time / datetime value to a 24-hour "H:i" (HH:MM) string
     *
     * postgres `time` columns come back as "HH:MM:SS"; the frontend time picker
     * and its validation expect a bare "HH:MM", so trim the seconds here.
     *
     * @param string|null $time
     * @return string|null
     */
    function formatTimeHm(null|string $time): ?string {
        $parsed = parseTime($time);

        return $parsed?->format('H:i');
    }
}

if (!function_exists('durationBetweenTime')) {
    function durationBetweenTime($startTime, $endTime) {
        try {
            $startTime = parseTime($startTime);
            $endTime = parseTime($endTime);

            return $endTime->diffInMinutes($startTime, true);
        } catch (Exception $e) {
            return null;
        }

        return null;
    }
}