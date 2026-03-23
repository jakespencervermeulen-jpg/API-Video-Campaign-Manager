<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_campaign(): void
    {
        $client = Client::create(['name' => 'Test Client']);

        $response = $this->postJson('/api/campaigns', [
            'client_id' => $client->id,
            'name' => 'Summer Campaign',
            'start_date' => '2025-07-01',
            'end_date' => '2025-08-01',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'client_id' => $client->id,
                'name' => 'Summer Campaign',
            ]);

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Summer Campaign',
            'client_id' => $client->id,
        ]);
    }

    public function test_can_create_campaign_without_end_date(): void
    {
        $client = Client::create(['name' => 'Test Client']);

        $response = $this->postJson('/api/campaigns', [
            'client_id' => $client->id,
            'name' => 'Ongoing Campaign',
            'start_date' => '2025-07-01',
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('end_date'));
    }

    public function test_create_campaign_requires_fields(): void
    {
        $response = $this->postJson('/api/campaigns', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id', 'name', 'start_date']);
    }

    public function test_create_campaign_rejects_invalid_client(): void
    {
        $response = $this->postJson('/api/campaigns', [
            'client_id' => 999,
            'name' => 'Test',
            'start_date' => '2025-07-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $client = Client::create(['name' => 'Test Client']);

        $response = $this->postJson('/api/campaigns', [
            'client_id' => $client->id,
            'name' => 'Bad Dates Campaign',
            'start_date' => '2025-08-01',
            'end_date' => '2025-07-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }
}
