<?php

namespace App\Http\Requests\Import;

use App\Constants\ImportConstant;
use Helper\Permission\PermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Translation\Message;

/**
 * The upload contract shared by every master-data import endpoint.
 *
 * Rules live here once; each entity subclasses this and implements only
 * `authorize()`, so the permission it checks stays a literal constant the
 * `PermissionAction` magic can resolve and a reader can grep — rather than a
 * string looked up from the route.
 */
abstract class ImportRequest extends FormRequest {
    use PermissionAction;

    /**
     * Each entity's subclass returns its own `import:<entity>` check.
     *
     * @return bool
     */
    abstract public function authorize(): bool;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array {
        return [
            'file' => [
                'required',
                'file',
                'mimes:' . implode(',', ImportConstant::SUPPORTED_FORMATS),
                'max:' . ImportConstant::MAX_IMPORT_FILE_SIZE_KB,
            ],
            'mode' => ['nullable', 'string', Rule::in(ImportConstant::MODES)],
            // The frontend calls with dry_run first, so the user sees
            // "12 rows OK, 3 errors" before anything is committed.
            'dry_run' => ['nullable', 'boolean'],
            // Opt in to writing the valid rows and reporting the rest, instead
            // of rejecting the whole file.
            'skip_invalid' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public function messages(): array {
        return Message::get('import') ?? [];
    }
}
