<?php

namespace App\Validation;

use Illuminate\Validation\Validator;

class CustomValidator extends Validator {
    public function validateString($attribute, $value): bool {
        return is_string($value) && ! $this->containsEmoji($value);
    }

    private function containsEmoji(string $value): bool {
        return preg_match('/[\x{1F000}-\x{1F9FF}\x{1FA00}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE0F}\x{200D}]/u', $value) === 1;
    }
}
