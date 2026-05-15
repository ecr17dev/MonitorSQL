<?php

namespace App\Http\Requests;

use App\Models\QueryRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQueryRunRequest extends FormRequest
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
            'category' => ['sometimes', 'nullable', 'string', Rule::in(QueryRun::categories())],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_favorite' => ['sometimes', 'boolean'],
        ];
    }
}
