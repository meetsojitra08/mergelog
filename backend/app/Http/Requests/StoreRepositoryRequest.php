<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepositoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'full_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\/\s]+\/[^\/\s]+$/',
                Rule::unique('repositories', 'full_name')->where('user_id', $this->user()->id),
            ],
            'github_token' => ['nullable', 'string'],
        ];
    }
}
