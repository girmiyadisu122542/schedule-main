<?php

namespace Helper\Traits;

use Illuminate\Http\Exceptions\HttpResponseException;
use Translation\Message;

trait PreventsDeletionWithRelations {
    /**
     * Boot the trait
     */
    protected static function bootPreventsDeletionWithRelations() {
        static::deleting(function ($model) {
            $model->preventDeletionIfHasChildren();
        });
    }

    /**
     * Ultra-fast deletion prevention check
     */
    protected function preventDeletionIfHasChildren() {
        if (!property_exists($this, 'protectedChildRelations')) {
            return;
        }

        $blockingRelations = [];

        foreach ($this->protectedChildRelations as $relationName) {
            if (method_exists($this, $relationName) && $this->$relationName()->exists()) {
                $blockingRelations[] = $this->convertToHumanReadable($relationName);
            }
        }

        if (!empty($blockingRelations)) {
            $modelName = $this->convertToHumanReadable(class_basename($this));
            $relations = implode(', ', $blockingRelations);

            $bindings = ['modelName' => $modelName, 'relations' => $relations];

            throw new HttpResponseException(
                response()->json([
                    'message' => Message::get('model_has_related_relations', $bindings),
                ], 422)
            );
        }
    }

    /**
     * Convert camelCase or PascalCase string to human-readable format
     *
     * @param string $string
     * @return string
     */
    protected function convertToHumanReadable(string $string): string {
        $string = preg_replace('/(?<!^)([A-Z])/', ' $1', $string);

        return strtolower($string);
    }
}
