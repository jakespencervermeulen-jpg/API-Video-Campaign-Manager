<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessCampaignData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Campaign $campaign,
        public array $data,
    ) {}

    public function handle(): void
    {
        $incomingUserIds = collect($this->data)->pluck('user_id');

        $existingUserIds = CampaignData::where('campaign_id', $this->campaign->id)
            ->whereIn('user_id', $incomingUserIds)
            ->pluck('user_id')
            ->toArray();

        foreach ($existingUserIds as $userId) {
            Log::warning('Duplicate campaign data detected', [
                'campaign_id' => $this->campaign->id,
                'user_id' => $userId,
                'action' => 'updated',
            ]);
        }

        $now = now();

        $rows = collect($this->data)->map(fn ($entry) => [
            'campaign_id' => $this->campaign->id,
            'user_id' => $entry['user_id'],
            'video_url' => $entry['video_url'],
            'custom_fields' => json_encode($entry['custom_fields'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        CampaignData::upsert(
            $rows,
            ['campaign_id', 'user_id'],
            ['video_url', 'custom_fields', 'updated_at']
        );

        Cache::forget("campaign_report_{$this->campaign->id}");
        Cache::forget('campaigns_report_all');
        Cache::forget('campaigns_analytics');
    }
}
