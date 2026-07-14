<?php

namespace Helper\Rule;

use Illuminate\Contracts\Validation\ValidationRule;

class CustomRule implements ValidationRule {

    /**
     * Closure used to validate the input
     * @var \Closure
     */
    public $validation;

    /**
     * The rule name
     * @var string
     */
    public $rule;

    /**
     * The attribute name
     * @var string
     */
    public $attribute;

    /**
     * The message key
     * @var string
     */
    public $messageKey;

    /**
     * The message key and values
     *
     * @var string
     */
    public $messages;

    public function __construct(string $rule, array $messages, \Closure $validation) {
        $this->rule = $rule;
        $this->messages = $messages;
        $this->validation = $validation;
    }

    /**
     * Creates a new
     * instance of Custom rule
     *
     * @param string $rule
     * @param array $messages
     * @param \Closure $validation
     *
     * @return \Helper\Rule\CustomRule
     */
    public static function make(string $rule, array $messages, \Closure $validation) {
        return new static($rule, $messages, $validation);
    }

    /**
     * Returns the rule name
     *
     * @return string
     */
    public function getRule(): string {
        return $this->rule;
    }

    /**
     * Sets the message key for the current
     * validation
     *
     * @param string $messageKey
     * @return \Helper\Rule\CustomRule
     */
    public function setMessageKey($messageKey): CustomRule {
        $this->messageKey = $messageKey;
        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void {
        $validation = $this->validation;
        $this->attribute = $attribute;
        $passes = $validation($attribute, $value);

        if ($passes === false) {
            $fail($this->message());
        } elseif (is_string($passes)) {
            $fail($passes);
        }
    }

    /**
     * The message that will be returned
     * when validation failed
     *
     * @return string
     */
    public function message(): string {
        $messageKey = $this->messageKey ?? "{$this->attribute}.{$this->rule}";
        $attributeLabel = trans()->has("validation.attributes.{$this->attribute}")
            ? __("validation.attributes.{$this->attribute}")
            : $this->attribute;

        return $this->messages[$messageKey]
            ?? __("validation.{$this->rule}", ['attribute' => $attributeLabel]);
    }

    /**
     * formats new rules
     * to align with the
     * CustomRule class
     *
     * @param array $rules
     * @return array
     */
    public static function format($rules): array {
        $newRules = [];
        foreach ($rules as $ruleKey => $ruleValidators) {
            $singleRule = [];
            if (!str_contains($ruleKey, '.*')) {
                $newRules[$ruleKey] = $ruleValidators;
                continue;
            }

            foreach ($ruleValidators as $ruleValidator) {
                $newRuleValidator = $ruleValidator;
                if ($newRuleValidator instanceof CustomRule) {
                    $newRuleValidator = clone($ruleValidator);
                    $newRuleValidator->setMessageKey("{$ruleKey}.{$newRuleValidator->getRule()}");
                }

                array_push($singleRule, $newRuleValidator);
            }

            $newRules[$ruleKey] = $singleRule;
        }

        return $newRules;
    }
}
