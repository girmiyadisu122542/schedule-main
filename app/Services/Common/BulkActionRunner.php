<?php

namespace App\Services\Common;

use Illuminate\Support\Collection;
use Translation\Message;

/**
 * Runs one action over many rows and reports what happened to each.
 *
 * Bulk here means "the same decision, taken many times" — NOT a mass update.
 * Every row goes through the identical per-row service call the single-item
 * endpoint uses, so its permission, scope, lifecycle and conflict rules all
 * still apply. That is the whole design: a bulk approve of forty offerings must
 * not be able to approve one that a single approve would have refused.
 *
 * Consequently a bulk run is PARTIAL by nature. Thirty-eight succeed, two are
 * refused because they were already published or belong to another department,
 * and the caller is told exactly which and why. Rolling the whole batch back
 * because one row was ineligible would be worse: the operator would have to
 * find the offender by hand and re-run everything.
 */
class BulkActionRunner {

    /**
     * Apply `$action` to every row `$resolve` can find.
     *
     * @param array<int, int|string> $ids the rows to act on
     * @param callable $resolve fn(int|string $id): object|null — load one row
     * @param callable $action fn(object $row): object|string — the per-row
     *                         service call, returning the row on success or an
     *                         error translation key on refusal
     * @param callable|null $label fn(object $row): string — how to name a row
     *                             in the failure list
     *
     * @return array{succeeded: int, failed: array<int, array{id: mixed, label: string|null, reason: string, reason_message: string}>}
     */
    public static function run(array $ids, callable $resolve, callable $action, ?callable $label = null): array {
        $succeeded = 0;
        $failed = [];

        foreach (collect($ids)->unique()->values() as $id) {
            $row = $resolve($id);

            if (!$row) {
                $failed[] = static::failure($id, null, 'not_found_or_out_of_scope');

                continue;
            }

            // A refusal is a returned key, not an exception — the services all
            // work that way. An exception really is exceptional and is left to
            // propagate rather than being flattened into a row failure.
            $result = $action($row);

            if (is_string($result)) {
                $failed[] = static::failure($id, $label ? $label($row) : null, $result);

                continue;
            }

            $succeeded++;
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    /**
     * One refused row, with its reason already in the reader's language.
     *
     * The translation happens HERE rather than in the client. The reason is a
     * message key — `invalid_status_transition` — and shipping the raw key to
     * the UI meant users read the key. Every other endpoint in this system
     * returns prose; a bulk result should not be the exception.
     *
     * The key is kept alongside it: it is stable, so a client can still branch
     * on it, whereas the sentence is free to be reworded.
     *
     * @param mixed $id
     * @param string|null $label
     * @param string $reason a message key
     *
     * @return array{id: mixed, label: string|null, reason: string, reason_message: string}
     */
    private static function failure($id, ?string $label, string $reason): array {
        $message = Message::get($reason);

        return [
            'id' => $id,
            'label' => $label,
            'reason' => $reason,
            // Falls back to the key only when nothing is registered for it,
            // which is a missing translation rather than a normal outcome.
            'reason_message' => is_string($message) && $message !== '' ? $message : $reason,
        ];
    }

    /**
     * The distinct reasons a run refused rows, for a one-line summary.
     *
     * @param array $outcome the value returned by {@see self::run()}
     * @return \Illuminate\Support\Collection
     */
    public static function reasons(array $outcome): Collection {
        return collect($outcome['failed'])->pluck('reason')->unique()->values();
    }
}
