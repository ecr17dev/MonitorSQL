<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('queries.export') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'connection_id' => ['required', 'integer', 'exists:database_connections,id'],
            'sql' => ['required', 'string', 'min:1'],
            'format' => ['required', Rule::in(['csv', 'xlsx', 'json'])],
        ];
    }
}
