<?php

namespace App\Models\Common\Lookup;

use Helper\Field\Field;
use Helper\Model\ScopedModel;
use Helper\Type\State\State;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LookupTransition extends ScopedModel {

    protected $fillable = [
        'lookup_type_id', 'from_value_id', 'to_value_id', 'state',
    ];

    protected $casts = [
        'state' => 'integer',
    ];

    public function lookupType(): BelongsTo {
        return $this->belongsTo(LookupType::class);
    }

    public function fromValue(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'from_value_id');
    }

    public function toValue(): BelongsTo {
        return $this->belongsTo(LookupValue::class, 'to_value_id');
    }

    public function indexFields(): array {
        return [
            Field::id(),
            Field::state(),
            Field::toValueId(),
            Field::fromValueId(),
            Field::lookupTypeId(),
            Field::makeResource('lookup_type', 'lookupType', fields: 'idAndNameFields'),
            Field::makeResource('from_value', 'fromValue', fields: 'idAndNameFields'),
            Field::makeResource('to_value', 'toValue', fields: 'idAndNameFields'),
            Field::makeType('state_data', 'state', typeClass: State::class, typeFunction: 'getFullTypeUsingId'),
        ];
    }
}
