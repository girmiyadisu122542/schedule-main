<?php

namespace App\Http\Requests\Lookup;

use App\Models\Common\Lookup\LookupValue;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;

class LookupTypeStatusRequest extends FormRequest {
    use PermissionAction;

    public function authorize(): bool {
        return $this->userCanChangeDynamicValueStatus();
    }

    public function rules(): array {
        return [
            'status_lookup_value_id' => LookupValue::statusLookupValueIdRules($this->messages()),
        ];
    }
}
