<?php

namespace Tests\Feature\Threads;

use App\Enums\ThreadsConnectionStatus;
use App\Enums\ThreadsDataDeletionStatus;
use App\Enums\ThreadsDisconnectReason;
use App\Models\ThreadsConnection;
use App\Models\ThreadsDataDeletionRequest;
use App\Models\ThreadsKeyword;
use App\Models\ThreadsPost;
use App\Models\ThreadsSearchRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithThreads;
use Tests\TestCase;

class MetaDataDeletionCallbackTest extends TestCase
{
    use InteractsWithThreads;
    use RefreshDatabase;

    private const THREADS_ID = '29580006461583503';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureThreads();
    }

    /**
     * SPEC-01 AC-21, D-12: connections, posts and runs of the account are deleted; keywords, users and other accounts stay.
     */
    public function test_valid_delete_removes_connections_posts_and_runs_and_keeps_keywords_and_users(): void
    {
        $user = User::factory()->create();
        ThreadsConnection::factory()->for($user)->create(['threads_user_id' => self::THREADS_ID]);
        $keyword = $this->makeKeyword($user, 'laravel');
        $this->makePost($user, ['threads_keyword_id' => $keyword->id]);
        ThreadsSearchRun::factory()->for($user)->create();

        $bystander = User::factory()->create();
        ThreadsConnection::factory()->for($bystander)->create(['threads_user_id' => '777']);
        $this->makePost($bystander);
        ThreadsSearchRun::factory()->for($bystander)->create();

        $this->post(route('threads.dataDeletion'), ['signed_request' => $this->signedRequest(['user_id' => self::THREADS_ID])])->assertOk();

        $this->assertDatabaseMissing('threads_connections', ['user_id' => $user->id]);
        $this->assertSame(0, $user->threadsPosts()->count());
        $this->assertSame(0, $user->threadsSearchRuns()->count());
        $this->assertModelExists($user);
        $this->assertModelExists($keyword);
        $this->assertDatabaseHas('threads_connections', ['user_id' => $bystander->id]);
        $this->assertSame(1, $bystander->threadsPosts()->count());
        $this->assertSame(1, $bystander->threadsSearchRuns()->count());
    }

    /**
     * SPEC-01 AC-21, RT-6: the JSON holds the status URL and a new alphanumeric code for every callback.
     */
    public function test_valid_delete_returns_status_url_and_a_new_alphanumeric_code_each_time(): void
    {
        ThreadsConnection::factory()->create(['threads_user_id' => self::THREADS_ID]);
        $signedRequest = $this->signedRequest(['user_id' => self::THREADS_ID]);

        $first = $this->postJson(route('threads.dataDeletion'), ['signed_request' => $signedRequest]);
        $second = $this->postJson(route('threads.dataDeletion'), ['signed_request' => $signedRequest]);

        foreach ([$first, $second] as $response) {
            $response->assertOk();
            $response->assertJsonStructure(['url', 'confirmation_code']);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{1,32}$/', $response->json('confirmation_code'));
            $this->assertSame(url('/data-deletion').'?code='.$response->json('confirmation_code'), $response->json('url'));
            $this->assertDatabaseHas('threads_data_deletion_requests', ['confirmation_code' => $response->json('confirmation_code')]);
        }
        $this->assertNotSame($first->json('confirmation_code'), $second->json('confirmation_code'));
    }

    /**
     * SPEC-01 AC-21, NFR-2: a matched deletion writes an info log with the Threads user id and the match count, and no token.
     */
    public function test_matched_delete_writes_an_info_log_without_secrets(): void
    {
        $logs = $this->captureLogs();
        ThreadsConnection::factory()->create(['threads_user_id' => self::THREADS_ID, 'access_token' => 'SECRET-TOKEN']);
        $signedRequest = $this->signedRequest(['user_id' => self::THREADS_ID]);

        $this->post(route('threads.dataDeletion'), ['signed_request' => $signedRequest])->assertOk();

        $matching = array_values(array_filter(
            $this->logsAt($logs, 'info'),
            fn ($log) => ($log->context['user_id'] ?? null) === self::THREADS_ID,
        ));
        $this->assertCount(1, $matching);
        $this->assertSame(1, $matching[0]->context['matched_connections']);
        $this->assertNoLogContains($logs, 'SECRET-TOKEN');
        $this->assertNoLogContains($logs, $signedRequest);
    }

    /**
     * SPEC-01 AC-23: the request is completed, with a completion time, when the deletion finishes.
     */
    public function test_valid_delete_marks_the_request_completed(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        ThreadsConnection::factory()->create(['threads_user_id' => self::THREADS_ID]);

        $response = $this->postJson(route('threads.dataDeletion'), ['signed_request' => $this->signedRequest(['user_id' => self::THREADS_ID])]);

        $response->assertOk();
        $request = ThreadsDataDeletionRequest::query()->where('confirmation_code', $response->json('confirmation_code'))->firstOrFail();
        $this->assertSame(ThreadsDataDeletionStatus::Completed, $request->status);
        $this->assertSame('2026-10-23 12:00:00', $request->requested_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-23 12:00:00', $request->completed_at->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * SPEC-01 AC-22, D-15: an unmatched id is logged, recorded as completed, and answered like a match.
     */
    public function test_unmatched_delete_records_a_completed_request_and_returns_the_same_shape(): void
    {
        $logs = $this->captureLogs();
        $signedRequest = $this->signedRequest(['user_id' => '987654321']);

        $response = $this->postJson(route('threads.dataDeletion'), ['signed_request' => $signedRequest]);

        $response->assertOk();
        $response->assertJsonStructure(['url', 'confirmation_code']);
        $this->assertDatabaseHas('threads_data_deletion_requests', [
            'confirmation_code' => $response->json('confirmation_code'),
            'status' => ThreadsDataDeletionStatus::Completed->value,
        ]);
        $mentioning = array_filter($logs->getArrayCopy(), fn ($log) => $this->logMentionsNumber($log, '987654321'));
        $this->assertNotEmpty($mentioning);
        $this->assertNoLogContains($logs, $signedRequest);
    }

    /**
     * SPEC-01 AC-20: an unverifiable delete callback gets 400 and records nothing.
     */
    public function test_invalid_signed_request_returns_400_and_records_nothing(): void
    {
        $connection = ThreadsConnection::factory()->create(['threads_user_id' => self::THREADS_ID]);

        $this->postJson(route('threads.dataDeletion'), [
            'signed_request' => $this->signedRequest(['user_id' => self::THREADS_ID], 'another-secret'),
        ])->assertBadRequest();

        $this->assertModelExists($connection);
        $this->assertDatabaseCount('threads_data_deletion_requests', 0);
    }

    /**
     * SPEC-01 NFR-11: the record keeps no Threads user id and no username.
     */
    public function test_deletion_request_stores_no_threads_user_id_or_username(): void
    {
        ThreadsConnection::factory()->create(['threads_user_id' => self::THREADS_ID, 'username' => 'elrondhalfelven']);

        $this->postJson(route('threads.dataDeletion'), ['signed_request' => $this->signedRequest(['user_id' => self::THREADS_ID])])->assertOk();

        $stored = json_encode(DB::table('threads_data_deletion_requests')->get());
        $this->assertStringNotContainsString(self::THREADS_ID, $stored);
        $this->assertStringNotContainsString('elrondhalfelven', $stored);
        $this->assertEqualsCanonicalizing(
            ['id', 'confirmation_code', 'status', 'requested_at', 'completed_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('threads_data_deletion_requests'),
        );
    }

    /**
     * SPEC-01 AC-21 (plan section 3): earlier users whose `disconnected` rows hold the same account lose their data too.
     */
    public function test_delete_also_removes_data_of_earlier_disconnected_users_of_the_same_account(): void
    {
        $earlierUser = User::factory()->create();
        ThreadsConnection::factory()->for($earlierUser)->disconnected(ThreadsDisconnectReason::User)->create(['threads_user_id' => self::THREADS_ID]);
        $this->makePost($earlierUser);
        ThreadsSearchRun::factory()->for($earlierUser)->create();
        $earlierKeyword = $this->makeKeyword($earlierUser, 'php');

        $currentUser = User::factory()->create();
        ThreadsConnection::factory()->for($currentUser)->create(['threads_user_id' => self::THREADS_ID]);
        $this->makePost($currentUser);

        $this->post(route('threads.dataDeletion'), ['signed_request' => $this->signedRequest(['user_id' => self::THREADS_ID])])->assertOk();

        $this->assertDatabaseMissing('threads_connections', ['threads_user_id' => self::THREADS_ID]);
        $this->assertSame(0, $earlierUser->threadsPosts()->count());
        $this->assertSame(0, $earlierUser->threadsSearchRuns()->count());
        $this->assertSame(0, $currentUser->threadsPosts()->count());
        $this->assertModelExists($earlierKeyword);
        $this->assertSame(0, ThreadsConnection::query()->where('status', ThreadsConnectionStatus::Connected)->count());
        $this->assertSame(0, ThreadsPost::query()->count());
        $this->assertSame(1, ThreadsKeyword::query()->count());
    }
}
