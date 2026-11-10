<?php

declare(strict_types=1);

namespace Tests\Feature\AiContent;

use App\Jobs\GenerateAiContent;
use App\Models\AiGeneration;
use App\Models\User;
use App\Services\Llm\LlmManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class StatementTest extends TestCase
{
    use RefreshDatabase;

    private const PROBLEM = 'На кухне прорвало трубу, вода течёт второй час.';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-content.driver' => 'fake',
            'ai-content.drivers.fake.text' => 'Середина заявления.',
        ]);

        Queue::fake();
    }

    public function test_guest_receives_401(): void
    {
        $this->postStatement('key-1')->assertUnauthorized();
    }

    public function test_problem_and_idempotency_key_are_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/statements', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['problem', 'idempotency_key']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/statements', ['problem' => self::PROBLEM])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_same_key_returns_the_same_statement_and_one_job(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user, 'sanctum')
            ->postStatement('key-1')
            ->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.text', null);

        $id = $created->json('data.id');
        $created->assertHeader('Location', route('ai-content.statements.show', $id));
        $created->assertHeader('Retry-After', '2');

        $this->actingAs($user, 'sanctum')
            ->postStatement('key-1')
            ->assertAccepted()
            ->assertJsonPath('data.id', $id);

        Queue::assertPushed(GenerateAiContent::class, 1);
        $this->assertDatabaseCount('ai_generations', 1);
    }

    public function test_same_key_with_another_problem_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postStatement('key-1')->assertAccepted();

        $this->actingAs($user, 'sanctum')
            ->postStatement('key-1', 'В ванной нет горячей воды уже третий день.')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['problem']);

        Queue::assertPushed(GenerateAiContent::class, 1);
    }

    public function test_another_user_cannot_read_the_statement(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $id = $this->actingAs($owner, 'sanctum')->postStatement('key-1')->json('data.id');

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/v1/statements/'.$id)
            ->assertForbidden();
    }

    public function test_finished_job_returns_the_full_statement(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user, 'sanctum')->postStatement('key-1')->json('data.id');
        $this->pushedJob()->handle($this->app->make(LlmManager::class));

        $generation = AiGeneration::query()->findOrFail($id);
        $date = $generation->created_at->timezone('Europe/Moscow')->format('d.m.Y');

        $text = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/statements/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->json('data.text');

        $this->assertIsString($text);
        $this->assertStringContainsString('В управляющую компанию', $text);
        $this->assertStringContainsString('Середина заявления.', $text);
        $this->assertStringContainsString('Прошу рассмотреть обращение', $text);
        $this->assertStringContainsString($user->name, $text);
        $this->assertStringContainsString($date, $text);
    }

    public function test_failed_job_returns_502(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user, 'sanctum')->postStatement('key-1')->json('data.id');
        $this->pushedJob()->failed(new RuntimeException('provider body and key must stay out of the row'));

        $generation = AiGeneration::query()->findOrFail($id);
        $this->assertSame('RuntimeException', $generation->error);
        $this->assertStringNotContainsString('provider body', (string) $generation->error);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/statements/'.$id)
            ->assertStatus(502)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.text', null);
    }

    public function test_requests_over_the_limit_receive_429(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postStatement('key-'.$attempt)->assertAccepted();
        }

        $this->postStatement('key-6')->assertTooManyRequests();
    }

    private function pushedJob(): GenerateAiContent
    {
        $job = null;

        Queue::assertPushed(GenerateAiContent::class, function (GenerateAiContent $pushed) use (&$job): bool {
            $job = $pushed;

            return true;
        });

        $this->assertInstanceOf(GenerateAiContent::class, $job);

        return $job;
    }

    /**
     * @return TestResponse
     */
    private function postStatement(string $key, ?string $problem = null)
    {
        return $this->postJson('/api/v1/statements', [
            'problem' => $problem ?? self::PROBLEM,
        ], [
            'Idempotency-Key' => $key,
        ]);
    }
}
