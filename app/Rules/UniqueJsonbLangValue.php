<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class UniqueJsonbLangValue implements Rule {
    protected $model;
    protected $column;
    protected $lang;
    protected $message;
    protected $ignoreId;
    protected $strict;
    protected $targetColumn;
    protected $callback;

    public function __construct($model, $column, $lang, $message, $ignoreId = null, $strict = false, $targetColumn = 'id', ?\Closure $callback = null) {
        $this->model = $model;
        $this->column = $column;
        $this->lang = $lang;
        $this->ignoreId = $ignoreId;
        $this->message = $message;
        $this->strict = $strict;
        $this->targetColumn = $targetColumn;
        $this->callback = $callback;
    }

    public function passes($attribute, $value) {
        if ($this->strict) {
            return $this->model::checkJsonbValueUniqueStrict(
                column: $this->column,
                value: $value,
                ignoreId: $this->ignoreId,
                targetColumn: $this->targetColumn,
                callback: $this->callback
            );
        }

        return $this->model::checkJsonbLangValueUnique(
            column: $this->column,
            value: $value,
            ignoreId: $this->ignoreId,
            targetColumn: $this->targetColumn,
            lang: $this->lang,
            callback: $this->callback
        );
    }

    public function message() {
        return $this->message;
    }
}
