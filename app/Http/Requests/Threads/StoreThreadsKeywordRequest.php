<?php

namespace App\Http\Requests\Threads;

use App\Concerns\ThreadsKeywordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreThreadsKeywordRequest extends FormRequest
{
    use ThreadsKeywordValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeKeywordInput();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => [...$this->keywordRules(), $this->keywordCapRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->keywordMessages();
    }
}
