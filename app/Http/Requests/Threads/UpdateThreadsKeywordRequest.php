<?php

namespace App\Http\Requests\Threads;

use App\Concerns\ThreadsKeywordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateThreadsKeywordRequest extends FormRequest
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
        $keyword = $this->user()->threadsKeywords()->whereKey($this->route('keyword'))->firstOrFail();

        return [
            'keyword' => $this->keywordRules($keyword->id),
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
