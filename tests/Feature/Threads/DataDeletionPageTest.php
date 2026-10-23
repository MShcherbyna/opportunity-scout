<?php

namespace Tests\Feature\Threads;

use App\Models\ThreadsDataDeletionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DataDeletionPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SPEC-01 AC-24, MC-6: a known code shows the request with its status (N-8); the page is public.
     */
    public function test_known_code_shows_the_pending_request_status(): void
    {
        ThreadsDataDeletionRequest::factory()->pending()->create([
            'confirmation_code' => 'ABC123xyz',
            'requested_at' => '2026-10-20 08:00:00',
        ]);

        $this->get(route('data-deletion', ['code' => 'ABC123xyz']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('legal/DataDeletion')
                ->where('codeNotFound', false)
                ->where('deletionRequest.confirmationCode', 'ABC123xyz')
                ->where('deletionRequest.status', 'pending')
                ->where('deletionRequest.requestedAt', fn (string $value) => Carbon::parse($value)->utc()->format('Y-m-d H:i:s') === '2026-10-20 08:00:00')
                ->where('deletionRequest.completedAt', null)
            );
    }

    /**
     * SPEC-01 AC-24: a completed request also shows when it completed.
     */
    public function test_known_code_shows_the_completed_request_status(): void
    {
        ThreadsDataDeletionRequest::factory()->completed()->create([
            'confirmation_code' => 'DONE456',
            'completed_at' => '2026-10-21 09:30:00',
        ]);

        $this->get(route('data-deletion', ['code' => 'DONE456']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('codeNotFound', false)
                ->where('deletionRequest.status', 'completed')
                ->where('deletionRequest.completedAt', fn (string $value) => Carbon::parse($value)->utc()->format('Y-m-d H:i:s') === '2026-10-21 09:30:00')
            );
    }

    /**
     * SPEC-01 AC-25: a well-formed but unknown code sets `codeNotFound` (N-9).
     */
    public function test_unknown_code_sets_code_not_found(): void
    {
        $this->get(route('data-deletion', ['code' => 'NOSUCHCODE1']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('legal/DataDeletion')
                ->where('codeNotFound', true)
                ->where('deletionRequest', null)
            );
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedCodes(): array
    {
        return [
            'longer than 32 characters' => [str_repeat('a', 33)],
            'contains a symbol' => ['abc-123'],
            'sql wildcard' => ['%'],
            'array instead of string' => [['a']],
        ];
    }

    /**
     * SPEC-01 AC-25, UI-7: a malformed code is answered with N-9 without querying the deletion requests.
     */
    #[DataProvider('malformedCodes')]
    public function test_malformed_code_sets_code_not_found_without_querying(mixed $code): void
    {
        ThreadsDataDeletionRequest::factory()->create(['confirmation_code' => 'a']);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->get(route('data-deletion', ['code' => $code]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('codeNotFound', true)
            ->where('deletionRequest', null)
        );
        $this->assertSame([], array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'threads_data_deletion_requests'))));
    }
}
