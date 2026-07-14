<?php

namespace App\Models\User;

use App\Models\User;
use Helper\Field\Field;
use Helper\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLog extends Model {
    use ModelTrait, HasFactory;

    /**
     * Relationship Section
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * Fields that will be use when
     * Returning data to the browser
     *
     * @return array
     */
    public function indexFields(): array {
        return [
            Field::id(),
            Field::loginTime(),
            Field::logoutTime(),
            Field::ip(),
            Field::device(),
            Field::userAgent(),
            Field::reason(),
            Field::makeResource('user', fields: 'idAndNameFields'),
        ];
    }
}
