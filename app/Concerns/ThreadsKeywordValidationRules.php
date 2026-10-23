<?php

namespace App\Concerns;

use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ThreadsKeywordValidationRules
{
    /**
     * Trim, collapse inner whitespace and lower-case the keyword (AC-34).
     * Values with control characters or invalid encoding are left untouched so that validation rejects them.
     */
    protected function normalizeKeywordInput(): void
    {
        $keyword = $this->input('keyword');

        if (! is_string($keyword) || ! mb_check_encoding($keyword, 'UTF-8') || preg_match('/\p{Cc}/u', $keyword) === 1) {
            return;
        }

        $this->merge([
            'keyword' => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $keyword))),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected function keywordRules(?int $ignoreKeywordId = null): array
    {
        return [
            'required',
            'string',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || preg_match('/\p{Cc}/u', $value) === 1) {
                    $fail('The keyword must not contain control characters.');
                }
            },
            'max:100',
            $this->uniqueKeywordRule($ignoreKeywordId),
        ];
    }

    protected function uniqueKeywordRule(?int $ignoreKeywordId): Unique
    {
        return Rule::unique('threads_keywords', 'keyword')
            ->where('user_id', $this->user()->id)
            ->ignore($ignoreKeywordId);
    }

    /**
     * @return array<string, string>
     */
    protected function keywordMessages(): array
    {
        return [
            'keyword.unique' => 'This keyword already exists.',
        ];
    }

    /**
     * Rule that rejects a new keyword once the user is at the cap for their interval (AC-37).
     */
    protected function keywordCapRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $user = $this->user();
            $interval = $user->searchInterval();
            $cap = $interval->keywordCap();

            if ($user->threadsKeywords()->count() >= $cap) {
                $fail("You can have up to {$cap} keywords with a {$interval->value}-minute search interval.");
            }
        };
    }
}
