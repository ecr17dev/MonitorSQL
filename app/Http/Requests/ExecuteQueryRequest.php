<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('queries.execute') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'connection_id' => ['required', 'integer', 'exists:database_connections,id'],
            'sql' => ['required', 'string', 'min:1'],
            'is_ai_generated' => ['sometimes', 'boolean'],
        ];
    }
}
