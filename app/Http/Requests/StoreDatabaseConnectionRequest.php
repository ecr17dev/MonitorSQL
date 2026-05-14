<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatabaseConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('connections.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', Rule::in(['mysql', 'mariadb', 'pgsql'])],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:5000'],
            'ssl_enabled' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'max_rows' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'query_timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:600'],
        ];
    }
}
