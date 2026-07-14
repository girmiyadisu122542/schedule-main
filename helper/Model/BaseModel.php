<?php

namespace Helper\Model;

use Constants\AppConstant;
use Helper\Traits\JsonbTrait;
use Helper\Traits\ModelTrait;
use Helper\Traits\PreventsDeletionWithRelations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model {
    use ModelTrait, JsonbTrait, HasFactory,
        PreventsDeletionWithRelations;

    public function __construct(...$args) {
        parent::__construct(...$args);
        $this->setConnection(AppConstant::SCHEDULE_DATABASE_CONNECTION);
    }
}
