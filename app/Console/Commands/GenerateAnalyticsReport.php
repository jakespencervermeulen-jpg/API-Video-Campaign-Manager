<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;

class GenerateAnalyticsReport extends Command
{
    protected $signature = 'campaigns:report {campaign_id?}';
    protected $description = 'Generate analytics report for campaigns';

    public function handle(): int
    {
        $campaignId = $this->argument('campaign_id');

        if ($campaignId) {
            $campaign = Campaign::with(['client', 'campaignData'])->find($campaignId);

            if (!$campaign) {
                $this->error("Campaign {$campaignId} not found.");
                return self::FAILURE;
            }

            $this->renderCampaignReport($campaign);
            return self::SUCCESS;
        }

        $campaigns = Campaign::with(['client', 'campaignData'])->get();

        if ($campaigns->isEmpty()) {
            $this->info('No campaigns found.');
            return self::SUCCESS;
        }

        $this->info("Total Campaigns: {$campaigns->count()}");
        $this->info("Total Recipients: {$campaigns->sum(fn ($c) => $c->campaignData->count())}");
        $this->newLine();

        foreach ($campaigns as $campaign) {
            $this->renderCampaignReport($campaign);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function renderCampaignReport(Campaign $campaign): void
    {
        $data = $campaign->campaignData;

        $customFieldCounts = $data
            ->pluck('custom_fields')
            ->filter()
            ->flatMap(fn (array $fields) => array_keys($fields))
            ->countBy();

        $this->info("Campaign: {$campaign->name} (ID: {$campaign->id})");
        $this->info("Client: {$campaign->client->name}");
        $this->info("Period: {$campaign->start_date->toDateString()} - " . ($campaign->end_date?->toDateString() ?? 'Ongoing'));
        $this->info("Total Recipients: {$data->count()}");

        if ($customFieldCounts->isNotEmpty()) {
            $this->info('Custom Field Usage:');
            $this->table(
                ['Field', 'Count'],
                $customFieldCounts->map(fn ($count, $field) => [$field, $count])->values()
            );
        }
    }
}
