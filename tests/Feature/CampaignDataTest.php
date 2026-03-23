<?php

namespace Tests\Feature;

use App\Jobs\ProcessCampaignData;
use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CampaignDataTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::create(['name' => 'Test Client']);
        $this->campaign = Campaign::create([
            'client_id' => $client->id,
            'name' => 'Test Campaign',
            'start_date' => '2025-07-01',
        ]);
    }

    public function test_store_data_returns_202_and_dispatches_job(): void
    {
        Bus::fake();

        $response = $this->postJson("/api/campaigns/{$this->campaign->id}/data", [
            'data' => [
                ['user_id' => 'recipient-1', 'video_url' => 'https://example.com/video1.mp4'],
            ],
        ]);

        $response->assertStatus(202)
            ->assertJson(['message' => 'Data accepted for processing.']);

        Bus::assertDispatched(ProcessCampaignData::class);
    }

    public function test_store_data_requires_data_array(): void
    {
        $response = $this->postJson("/api/campaigns/{$this->campaign->id}/data", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data']);
    }

    public function test_store_data_validates_each_entry(): void
    {
        $response = $this->postJson("/api/campaigns/{$this->campaign->id}/data", [
            'data' => [
                ['user_id' => '', 'video_url' => 'not-a-url'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.0.user_id', 'data.0.video_url']);
    }

    public function test_store_data_returns_404_for_invalid_campaign(): void
    {
        $response = $this->postJson('/api/campaigns/999/data', [
            'data' => [
                ['user_id' => 'recipient-1', 'video_url' => 'https://example.com/video1.mp4'],
            ],
        ]);

        $response->assertStatus(404);
    }

    public function test_job_stores_campaign_data(): void
    {
        $job = new ProcessCampaignData($this->campaign, [
            ['user_id' => 'recipient-1', 'video_url' => 'https://example.com/video1.mp4', 'custom_fields' => ['name' => 'John']],
            ['user_id' => 'recipient-2', 'video_url' => 'https://example.com/video2.mp4'],
        ]);

        $job->handle();

        $this->assertDatabaseCount('campaign_data', 2);
        $this->assertDatabaseHas('campaign_data', [
            'campaign_id' => $this->campaign->id,
            'user_id' => 'recipient-1',
            'video_url' => 'https://example.com/video1.mp4',
        ]);
    }

    public function test_job_updates_duplicate_and_logs_warning(): void
    {
        CampaignData::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => 'recipient-1',
            'video_url' => 'https://example.com/old-video.mp4',
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message, $context) =>
                $message === 'Duplicate campaign data detected'
                && $context['user_id'] === 'recipient-1'
            );

        $job = new ProcessCampaignData($this->campaign, [
            ['user_id' => 'recipient-1', 'video_url' => 'https://example.com/new-video.mp4'],
        ]);

        $job->handle();

        $this->assertDatabaseCount('campaign_data', 1);
        $this->assertDatabaseHas('campaign_data', [
            'user_id' => 'recipient-1',
            'video_url' => 'https://example.com/new-video.mp4',
        ]);
    }

    public function test_job_handles_custom_fields(): void
    {
        $job = new ProcessCampaignData($this->campaign, [
            [
                'user_id' => 'recipient-1',
                'video_url' => 'https://example.com/video1.mp4',
                'custom_fields' => ['first_name' => 'Jane', 'company' => 'Acme'],
            ],
        ]);

        $job->handle();

        $record = CampaignData::where('user_id', 'recipient-1')->first();
        $this->assertEquals(['first_name' => 'Jane', 'company' => 'Acme'], $record->custom_fields);
    }
}
