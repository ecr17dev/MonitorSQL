<?php

namespace App\Http\Requests;

use App\Models\SavedQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSavedQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'sql' => ['sometimes', 'string'],
            'is_favorite' => ['sometimes', 'boolean'],
            'category' => ['sometimes', 'nullable', 'string', Rule::in(SavedQuery::categories())],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'connection_id' => ['sometimes', 'integer', 'exists:database_connections,id'],
        ];
    }
}
