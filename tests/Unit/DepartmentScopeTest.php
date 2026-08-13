<?php

use App\Services\User\DepartmentScopeService;

/**
 * DepartmentScopeService — the rule deciding WHOSE schedules a user may touch.
 *
 * Permissions answer whether a user may publish a schedule at all; this answers
 * which department's. Both halves of the rule are pure and tested here; the
 * lookups that feed them (head_user_id / dean_user_id / instructors.user_id)
 * need a database and are exercised by the feature tests.
 *
 * The distinction that matters throughout: NULL means unrestricted — the whole
 * institution — while an EMPTY array means bound to nothing at all. Conflating
 * the two either locks out the registrar or opens the institution to a user
 * with no department.
 */
test('an unrestricted scope permits any department', function () {
    expect(DepartmentScopeService::permits(null, 1))->toBeTrue();
    expect(DepartmentScopeService::permits(null, 999))->toBeTrue();
});

test('an unrestricted scope permits a schedule that names no department', function () {
    expect(DepartmentScopeService::permits(null, null))->toBeTrue();
});

test('an empty scope permits nothing', function () {
    expect(DepartmentScopeService::permits([], 1))->toBeFalse();
    expect(DepartmentScopeService::permits([], null))->toBeFalse();
});

test('a scope permits the departments it lists', function () {
    expect(DepartmentScopeService::permits([2, 5], 2))->toBeTrue();
    expect(DepartmentScopeService::permits([2, 5], 5))->toBeTrue();
});

test('a scope refuses a department it does not list', function () {
    expect(DepartmentScopeService::permits([2, 5], 3))->toBeFalse();
});

test('a restricted scope refuses a schedule that names no department', function () {
    // An offering with no department is nobody's to act on but an
    // unrestricted user's — it must not fall through to everyone.
    expect(DepartmentScopeService::permits([2, 5], null))->toBeFalse();
});

test('a scope compares by value, not by loose equality', function () {
    // in_array without strict matching would let "2abc" or true through.
    expect(DepartmentScopeService::permits([2], 2))->toBeTrue();
    expect(DepartmentScopeService::permits([0], null))->toBeFalse();
});

test('combine folds the instructor department in with the headed ones', function () {
    expect(DepartmentScopeService::combine([2, 5], 7))->toBe([2, 5, 7]);
});

test('combine ignores a user who is not an instructor', function () {
    expect(DepartmentScopeService::combine([2, 5], null))->toBe([2, 5]);
});

test('combine deduplicates a head who also teaches in their own department', function () {
    expect(DepartmentScopeService::combine([2, 5], 5))->toBe([2, 5]);
});

test('combine casts ids so a string from the database still matches strictly', function () {
    // pluck() can hand back strings; permits() compares strictly, so a
    // non-integer here would silently refuse the user their own department.
    expect(DepartmentScopeService::combine(['2', '5'], null))->toBe([2, 5]);
});

test('combine returns a list, so the ids survive a JSON round trip as an array', function () {
    // array_unique preserves keys; without re-indexing this reaches the
    // frontend as an object and every scope check there breaks.
    $combined = DepartmentScopeService::combine([2, 2, 5], null);

    expect(array_keys($combined))->toBe([0, 1]);
    expect(json_encode($combined))->toBe('[2,5]');
});

test('a user bound to nothing sees nothing rather than everything', function () {
    // The whole point of separating null from []: a bug that returned []
    // for the registrar would lock them out, and one that returned null
    // for an unbound user would hand them the institution.
    expect(DepartmentScopeService::permits(DepartmentScopeService::combine([], null), 1))->toBeFalse();
});
