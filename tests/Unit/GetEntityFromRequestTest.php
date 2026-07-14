<?php

/**
 * getEntityFromRequest() -- the multi-entity filter parser feeding
 * applyRoleBasedQuery(entityId: ...) on every list endpoint. It merges the
 * multi-select `entity_ids` array with the legacy single `entity_id`, keeps
 * only positive integers and returns null when nothing usable was sent.
 */
test('returns null when no entity filter is sent', function () {
    request()->replace([]);

    expect(getEntityFromRequest())->toBeNull();
});

test('reads the legacy single entity_id as a one-element array', function () {
    request()->replace(['entity_id' => 5]);

    expect(getEntityFromRequest())->toBe([5]);
});

test('reads the multi-select entity_ids array', function () {
    request()->replace(['entity_ids' => [3, 5, 9]]);

    expect(getEntityFromRequest())->toBe([3, 5, 9]);
});

test('merges entity_ids with the legacy entity_id', function () {
    request()->replace(['entity_ids' => [3], 'entity_id' => 7]);

    expect(getEntityFromRequest())->toBe([3, 7]);
});

test('sanitizes non-numeric, zero and negative values away', function () {
    request()->replace(['entity_ids' => ['3', 'abc', -2, 0, '5', null]]);

    expect(getEntityFromRequest())->toBe([3, 5]);
});

test('deduplicates repeated ids across both keys', function () {
    request()->replace(['entity_ids' => [3, '3', 3], 'entity_id' => 3]);

    expect(getEntityFromRequest())->toBe([3]);
});

test('returns null when every value is invalid', function () {
    request()->replace(['entity_ids' => ['abc', -1, 0]]);

    expect(getEntityFromRequest())->toBeNull();
});

test('returns null for an empty entity_ids array', function () {
    request()->replace(['entity_ids' => []]);

    expect(getEntityFromRequest())->toBeNull();
});

test('casts numeric strings from the query string to integers', function () {
    request()->replace(['entity_ids' => ['4', '8']]);

    expect(getEntityFromRequest())->toBe([4, 8]);
});
