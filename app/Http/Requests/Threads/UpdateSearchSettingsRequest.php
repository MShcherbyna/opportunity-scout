<?php

namespace App\Http\Requests\Threads;

use App\Enums\FirstSearchLookback;
use App\Enums\SearchInterval;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSearchSettingsRequest extends FormRequest
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
            'manual_only' => ['required', 'boolean'],
            'interval_minutes' => [
                'bail',
                'required',
                'integer',
                Rule::enum(SearchInterval::class),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $interval = SearchInterval::from((int) $value);
                    $cap = $interval->keywordCap();

                    if ($this->user()->threadsKeywords()->count() > $cap) {
                        $fail("A {$interval->value}-minute interval allows up to {$cap} keywords. Remove keywords first.");
                    }
                },
            ],
            'first_search_lookback_days' => ['required', 'integer', Rule::enum(FirstSearchLookback::class)],
        ];
    }
}
