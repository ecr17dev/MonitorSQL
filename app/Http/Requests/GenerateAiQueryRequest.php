<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAiQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('queries.ai_generate') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'connection_id' => ['required', 'integer', 'exists:database_connections,id'],
            'question' => ['required', 'string', 'min:2', 'max:5000'],
            'conversation_id' => ['sometimes', 'string', 'size:36'],
            'selected_tables' => ['sometimes', 'array'],
            'selected_tables.*' => ['string', 'max:255'],
        ];
    }
}
