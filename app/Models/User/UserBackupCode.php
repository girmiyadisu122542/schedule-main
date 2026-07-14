<?php

namespace App\Models\User;

use App\Models\User;
use Helper\Field\Field;
use Helper\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserBackupCode extends BaseModel {
    use SoftDeletes;

    /**
     * Fillable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', 'code', 'status',
    ];


    /**
     * Relationship Section
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function indexFields(): array {
        return [
            Field::value('code'),
            Field::status('status'),
            Field::used(fn ($data) => $data->status == USER_BACKUP_CODE_USED),
        ];
    }
}
