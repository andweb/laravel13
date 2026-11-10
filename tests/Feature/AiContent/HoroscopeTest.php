<?php

declare(strict_types=1);

namespace Tests\Feature\AiContent;

use App\Jobs\GenerateAiContent;
use App\Models\AiGeneration;
use App\Models\User;
use App\Services\Llm\LlmManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class HoroscopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-content.driver' => 'fake',
            'ai-content.drivers.fake.text' => 'Овен. День спокойный.',
            'ai-content.timezone' => 'Europe/Moscow',
            'ai-content.horoscope.days_range' => 7,
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 12:00:00', 'Europe/Moscow'));
        Queue::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_guest_receives_401(): void
    {
        $this->getJson('/api/v1/horoscope')->assertUnauthorized();
    }

    public function test_date_outside_the_range_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/horoscope?date=2026-11-02')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);

        $this->getJson('/api/v1/horoscope?date=10.11.2026')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);

        $this->assertDatabaseCount('ai_generations', 0);
    }

    public function test_missing_date_uses_today_in_the_configured_timezone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/horoscope')
            ->assertAccepted()
            ->assertJsonPath('data.status', 'pending');

        $this->assertNotNull(
            AiGeneration::query()->horoscopeFor('2026-11-10')->whereNull('user_id')->first()
        );
    }

    public function test_the_same_date_creates_one_row_and_one_job(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $created = $this->getJson('/api/v1/horoscope?date=2026-11-10')
            ->assertAccepted()
            ->assertHeader('Retry-After', '2')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.text', null);

        $this->getJson('/api/v1/horoscope?date=2026-11-10')
            ->assertAccepted()
            ->assertJsonPath('data.id', $created->json('data.id'));

        Queue::assertPushed(GenerateAiContent::class, 1);
        $this->assertDatabaseCount('ai_generations', 1);
    }

    public function test_finished_horoscope_is_cached_until_the_end_of_today(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $id = $this->getJson('/api/v1/horoscope?date=2026-11-10')->json('data.id');
        $this->pushedJob()->handle($this->app->make(LlmManager::class));

        $text = $this->getJson('/api/v1/horoscope?date=2026-11-10')
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->json('data.text');

        $this->assertSame('Овен. День спокойный.', $text);
        $this->assertSame([
            'id' => $id,
            'text' => 'Овен. День спокойный.',
        ], Cache::get('ai-content:horoscope:2026-11-10'));

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/horoscope?date=2026-11-10')->assertOk();

        $this->assertStringNotContainsString('ai_generations', $this->queries());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 00:05:00', 'Europe/Moscow'));
        $this->assertNull(Cache::get('ai-content:horoscope:2026-11-10'));

        $this->getJson('/api/v1/horoscope?date=2026-11-10')
            ->assertOk()
            ->assertJsonPath('data.text', 'Овен. День спокойный.');

        Queue::assertPushed(GenerateAiContent::class, 1);
        $this->assertSame('Овен. День спокойный.', Cache::get('ai-content:horoscope:2026-11-10')['text'] ?? null);
    }

    public function test_failed_horoscope_is_queued_again(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $id = $this->getJson('/api/v1/horoscope?date=2026-11-10')->json('data.id');
        $this->pushedJob()->failed(new RuntimeException('provider body must stay out of the row'));

        $this->assertSame('failed', AiGeneration::query()->findOrFail($id)->status->value);

        $this->getJson('/api/v1/horoscope?date=2026-11-10')
            ->assertAccepted()
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(GenerateAiContent::class, 2);
        $this->assertSame('pending', AiGeneration::query()->findOrFail($id)->status->value);
        $this->assertNull(AiGeneration::query()->findOrFail($id)->error);
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

    private function queries(): string
    {
        $sql = '';

        foreach (DB::getQueryLog() as $query) {
            if (is_string($query['query'] ?? null)) {
                $sql .= ' '.$query['query'];
            }
        }

        return $sql;
    }
}
