<?php

namespace App\Http\Requests\Threads;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRelevanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_relevant' => ['required', 'boolean'],
        ];
    }

    /**
     * The wanted relevance state (the request carries the target value, not a flip).
     */
    public function isRelevant(): bool
    {
        return $this->boolean('is_relevant');
    }
}
