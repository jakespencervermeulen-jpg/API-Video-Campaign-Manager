<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignReportTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::create(['name' => 'Test Client']);
        $this->campaign = Campaign::create([
            'client_id' => $this->client->id,
            'name' => 'Test Campaign',
            'start_date' => '2025-07-01',
            'end_date' => '2025-08-01',
        ]);
    }

    public function test_single_campaign_report(): void
    {
        CampaignData::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => 'recipient-1',
            'video_url' => 'https://example.com/video1.mp4',
            'custom_fields' => ['first_name' => 'John', 'company' => 'Acme'],
        ]);

        CampaignData::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => 'recipient-2',
            'video_url' => 'https://example.com/video2.mp4',
            'custom_fields' => ['first_name' => 'Jane'],
        ]);

        $response = $this->getJson("/api/campaigns/report/{$this->campaign->id}");

        $response->assertStatus(200)
            ->assertJson([
                'campaign_id' => $this->campaign->id,
                'campaign_name' => 'Test Campaign',
                'client_name' => 'Test Client',
                'start_date' => '2025-07-01',
                'end_date' => '2025-08-01',
                'total_recipients' => 2,
                'unique_custom_fields' => ['first_name', 'company'],
            ]);
    }

    public function test_single_campaign_report_with_no_data(): void
    {
        $response = $this->getJson("/api/campaigns/report/{$this->campaign->id}");

        $response->assertStatus(200)
            ->assertJson([
                'total_recipients' => 0,
                'unique_custom_fields' => [],
            ]);
    }

    public function test_all_campaigns_report(): void
    {
        $campaign2 = Campaign::create([
            'client_id' => $this->client->id,
            'name' => 'Second Campaign',
            'start_date' => '2025-09-01',
        ]);

        CampaignData::create([
            'campaign_id' => $this->campaign->id,
            'user_id' => 'recipient-1',
            'video_url' => 'https://example.com/video1.mp4',
        ]);

        $response = $this->getJson('/api/campaigns/report');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(['campaign_name' => 'Test Campaign', 'total_recipients' => 1])
            ->assertJsonFragment(['campaign_name' => 'Second Campaign', 'total_recipients' => 0]);
    }

    public function test_report_returns_404_for_invalid_campaign(): void
    {
        $response = $this->getJson('/api/campaigns/report/999');

        $response->assertStatus(404);
    }
}
