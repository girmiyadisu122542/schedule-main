<?php

use App\Models\Schedule\ScheduleSetting;
use App\Services\Schedule\ScheduleSettingService;

/**
 * The rules the audit found missing, pinned down.
 *
 * Deliberately unit tests over pure-ish inputs: the generators are integration
 * territory, but the arithmetic these rules turn on — how many invigilators a
 * hall needs, how a candidate scores — is exactly the part that regresses
 * quietly, and it needs no database to check.
 */

/**
 * An unsaved settings row, so these run without touching a database while
 * still going through the real model — the casts are part of what is tested.
 */
function ruleSetting(array $overrides = []): ScheduleSetting {
    return new ScheduleSetting(array_merge([
        'students_per_invigilator' => 30,
        'min_invigilators_per_room' => 1,
        'max_exams_per_day' => 2,
        'min_hours_between_exams' => 0,
        'weight_spread_sessions' => 10,
        'weight_avoid_gaps' => 6,
        'weight_room_fit' => 3,
        'weight_same_building' => 4,
        'allow_cross_campus_day' => false,
        'exam_type_durations' => ['midterm' => 90, 'final' => 180],
    ], $overrides));
}


/**
 * Reach the generator's private hall-splitting logic.
 *
 * Private because nothing outside the generator should be choosing halls, but
 * it is the one piece of C9 worth unit-testing: the rest of that path needs a
 * database and the seven EXCLUDE constraints to mean anything.
 */
function hall(int $id, int $seats): \App\Models\Physical\Room {
    $room = new \App\Models\Physical\Room();
    $room->id = $id;
    $room->capacity = $seats;
    $room->exam_capacity = $seats;

    return $room;
}

function chooseHallsFor(\Illuminate\Support\Collection $halls, int $seatsNeeded): ?array {
    $service = app(\App\Services\Schedule\ExamScheduleGeneratorService::class);
    $method = new ReflectionMethod($service, 'chooseHalls');
    $method->setAccessible(true);

    return $method->invoke($service, $halls, $seatsNeeded);
}

// ---- C11: invigilators derived from occupancy -----------------------------

test('a hall needs one invigilator per configured block of students', function () {
    $service = new ScheduleSettingService();

    expect($service->invigilatorsFor(ruleSetting(), 30))->toBe(1)
        ->and($service->invigilatorsFor(ruleSetting(), 31))->toBe(2)
        ->and($service->invigilatorsFor(ruleSetting(), 120))->toBe(4);
});

test('a nearly empty hall still gets the configured minimum', function () {
    $service = new ScheduleSettingService();

    // Nobody invigilates alone where the rule says two, however small the room.
    expect($service->invigilatorsFor(ruleSetting(['min_invigilators_per_room' => 2]), 5))->toBe(2);
});

test('an unconfigured institution falls back rather than staffing nothing', function () {
    $service = new ScheduleSettingService();

    // The default is one invigilator per 50, so 90 seated needs two.
    expect($service->invigilatorsFor(null, 90))->toBe(2);
});

test('a hall of fifty or fewer needs only one invigilator', function () {
    $service = new ScheduleSettingService();

    // The point of the 50 default: an ordinary hall is watched by one person,
    // and only a genuinely large sitting earns a second.
    expect($service->invigilatorsFor(null, 15))->toBe(1)
        ->and($service->invigilatorsFor(null, 40))->toBe(1)
        ->and($service->invigilatorsFor(null, 50))->toBe(1);
});

test('a large hall scales past the first invigilator', function () {
    $service = new ScheduleSettingService();

    expect($service->invigilatorsFor(null, 51))->toBe(2)
        ->and($service->invigilatorsFor(null, 60))->toBe(2)
        ->and($service->invigilatorsFor(null, 120))->toBe(3)
        ->and($service->invigilatorsFor(null, 300))->toBe(6);
});

// ---- C4: duration resolves by exam type ----------------------------------

test('a midterm and a final resolve to different lengths', function () {
    $service = new ScheduleSettingService();

    expect($service->examTypeDuration(ruleSetting(), 'midterm'))->toBe(90)
        ->and($service->examTypeDuration(ruleSetting(), 'final'))->toBe(180);
});

test('an exam type nobody configured falls through instead of guessing', function () {
    $service = new ScheduleSettingService();

    expect($service->examTypeDuration(ruleSetting(), 'makeup'))->toBeNull()
        ->and($service->examTypeDuration(ruleSetting(), null))->toBeNull();
});

// ---- C8: spacing is expressed in minutes ---------------------------------

test('the configured rest between exams converts to minutes', function () {
    $service = new ScheduleSettingService();

    expect($service->minMinutesBetweenExams(ruleSetting(['min_hours_between_exams' => 4])))->toBe(240)
        // Zero means "only the overlap rule applies", which is what the
        // database enforced on its own before this setting existed.
        ->and($service->minMinutesBetweenExams(ruleSetting()))->toBe(0);
});

test('an unset per-day cap falls back rather than scheduling nothing', function () {
    $service = new ScheduleSettingService();

    // The CHECK constraint forbids storing 0, so a zero here means "nothing
    // configured". Reading it literally would cap every cohort at no exams at
    // all and report a conflict for each one.
    expect($service->maxExamsPerDay(ruleSetting(['max_exams_per_day' => 0])))->toBe(2)
        ->and($service->maxExamsPerDay(null))->toBe(2)
        ->and($service->maxExamsPerDay(ruleSetting(['max_exams_per_day' => 3])))->toBe(3);
});

// ---- C10: scoring prefers the better slot, not the first one -------------

test('a weight of zero switches its preference off', function () {
    $service = new ScheduleSettingService();

    $weights = $service->weights(ruleSetting(['weight_spread_sessions' => 0]));

    expect($weights['spread_sessions'])->toBe(0)
        ->and($weights['avoid_gaps'])->toBe(6);
});

// ---- C9: choosing halls for a cohort no single hall holds --------------

test('a cohort that fits one hall is not split', function () {
    $halls = collect([hall(1, 200)]);

    // 150 into a 200-seat hall: one row, no parts.
    expect(chooseHallsFor($halls, 150))->toHaveCount(1)
        ->and(chooseHallsFor($halls, 150)[0]['seats'])->toBe(150);
});

test('an oversized cohort takes the fewest halls that hold it', function () {
    $halls = collect([hall(1, 30), hall(2, 120), hall(3, 60)]);

    // Largest first, so 140 becomes 120 + 20 across two halls rather than
    // being scattered over three — every extra hall is another set of
    // invigilators and another place for a paper to go astray.
    $chosen = chooseHallsFor($halls, 140);

    expect($chosen)->toHaveCount(2)
        ->and($chosen[0]['seats'])->toBe(120)
        ->and($chosen[1]['seats'])->toBe(20)
        ->and(array_sum(array_column($chosen, 'seats')))->toBe(140);
});

test('a cohort larger than every hall together cannot be placed', function () {
    $halls = collect([hall(1, 30)]);

    // Null, not a partial allocation: seating some of a cohort and silently
    // leaving the rest without a hall is worse than reporting failure.
    expect(chooseHallsFor($halls, 500))->toBeNull();
});

// ---- C17: shifting a session to another weekday ------------------------

/**
 * The weekday wrap, as `ScheduleBulkService::shiftDays` computes it.
 *
 * ISO days run 1..7, so the arithmetic has to be done on 0..6 and shifted
 * back — the off-by-one that produces "day 8" is the obvious way to get this
 * wrong, and it would violate the day_of_week check constraint rather than
 * failing visibly.
 */
function shiftWeekday(int $from, int $shift): int {
    return ((($from - 1 + $shift) % 7) + 7) % 7 + 1;
}

test('shifting a weekday wraps around the week in both directions', function () {
    // Forward off the end of the week.
    expect(shiftWeekday(7, 1))->toBe(1)
        ->and(shiftWeekday(6, 2))->toBe(1)
        ->and(shiftWeekday(5, 3))->toBe(1);

    // Backward off the start — PHP's % returns a negative for a negative
    // operand, which is exactly what the extra +7 is there to absorb.
    expect(shiftWeekday(1, -1))->toBe(7)
        ->and(shiftWeekday(3, -5))->toBe(5)
        ->and(shiftWeekday(1, -6))->toBe(2);
});

test('shifting a weekday by a whole week is a no-op', function () {
    foreach (range(1, 7) as $day) {
        expect(shiftWeekday($day, 7))->toBe($day)
            ->and(shiftWeekday($day, -7))->toBe($day)
            ->and(shiftWeekday($day, 0))->toBe($day);
    }
});

test('a shifted weekday is always a legal ISO day', function () {
    // The constraint the database enforces: never 0, never 8.
    foreach (range(1, 7) as $day) {
        foreach (range(-6, 6) as $shift) {
            expect(shiftWeekday($day, $shift))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(7);
        }
    }
});
