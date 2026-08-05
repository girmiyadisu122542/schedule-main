<?php

namespace App\Rules;

use App\Services\Lookup\LookupService;
use Illuminate\Contracts\Validation\ValidationRule;
use Translation\Message;

/**
 * Asserts that a `*_lookup_value_id` payload field points at an ACTIVE value of
 * the expected lookup type.
 *
 * A plain `exists:lookup_values,id` would happily accept a ROOM_TYPE id in a
 * `degree_level_lookup_value_id` column — the foreign key cannot tell the
 * vocabularies apart because they all live in one table. Every scheduling Form
 * Request that carries a lookup column uses this instead.
 */
class LookupValueOfType implements ValidationRule {

    /**
     * @param string $typeCode the lookup type code the value must belong to,
     *                         e.g. DEGREE_LEVEL — always a constant from
     *                         helper/LookupConfig.php, never a literal
     * @param string|null $messageKey translation key for the failure message
     */
    public function __construct(
        private string $typeCode,
        private ?string $messageKey = null,
    ) {
    }

    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_numeric($value) || !LookupService::exists($this->typeCode, (int) $value)) {
            $fail($this->message());
        }
    }

    /**
     * The failure message — a registered translation key when one was given,
     * otherwise the shared "not a valid option" fallback.
     *
     * @return string
     */
    private function message(): string {
        $message = Message::get($this->messageKey ?? 'invalid_lookup_value');

        return $message ?? 'The selected value is not valid for this field.';
    }
}
