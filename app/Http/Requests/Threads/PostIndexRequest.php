<?php

namespace App\Http\Requests\Threads;

use Illuminate\Foundation\Http\FormRequest;

class PostIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    /**
     * The search term, or an empty string when none was given.
     */
    public function search(): string
    {
        return (string) ($this->validated('search') ?? '');
    }

    /**
     * The keyword filter, or null when none was given.
     */
    public function keyword(): ?string
    {
        return $this->validated('keyword');
    }

    /**
     * The requested page, 1 when none was given.
     */
    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    /**
     * The filters echoed back to the page.
     *
     * @return array{search: string, keyword: string|null}
     */
    public function filters(): array
    {
        return ['search' => $this->search(), 'keyword' => $this->keyword()];
    }
}
