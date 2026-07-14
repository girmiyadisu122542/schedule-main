<?php

namespace Helper\Rule;

trait CustomRuleFormRequest {

    /**
     * Creates a new validation rule instance
     *
     * @param string $rule
     * @param \Closure $validation
     *
     * @return \Helper\Rule\CustomRule
     */
    public static function makeCustomRule(string $rule, \Closure $validation): CustomRule {
        return CustomRule::make($rule, (new static())->messages(), $validation);
    }
}