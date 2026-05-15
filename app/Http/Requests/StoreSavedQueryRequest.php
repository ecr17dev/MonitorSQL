<?php

namespace App\Http\Requests;

use App\Models\SavedQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavedQueryRequest extends FormRequest
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
            'connection_id' => ['required', 'integer', 'exists:database_connections,id'],
            'name' => ['required', 'string', 'max:255'],
            'sql' => ['required', 'string'],
            'is_favorite' => ['sometimes', 'boolean'],
            'category' => ['sometimes', 'nullable', 'string', Rule::in(SavedQuery::categories())],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
