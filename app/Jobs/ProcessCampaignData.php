<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        foreach ($this->data as $entry) {
            $existing = CampaignData::where('campaign_id', $this->campaign->id)
                ->where('user_id', $entry['user_id'])
                ->first();

            if ($existing) {
                Log::warning('Duplicate campaign data detected', [
                    'campaign_id' => $this->campaign->id,
                    'user_id' => $entry['user_id'],
                    'action' => 'updated',
                ]);

                $existing->update([
                    'video_url' => $entry['video_url'],
                    'custom_fields' => $entry['custom_fields'] ?? null,
                ]);

                continue;
            }

            CampaignData::create([
                'campaign_id' => $this->campaign->id,
                'user_id' => $entry['user_id'],
                'video_url' => $entry['video_url'],
                'custom_fields' => $entry['custom_fields'] ?? null,
            ]);
        }
    }
}
