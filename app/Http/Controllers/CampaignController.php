<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignDataRequest;
use App\Http\Requests\StoreCampaignRequest;
use App\Jobs\ProcessCampaignData;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CampaignController extends Controller
{
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = Campaign::create($request->validated());

        return response()->json($campaign, 201);
    }

    public function storeData(StoreCampaignDataRequest $request, Campaign $campaign): JsonResponse
    {
        ProcessCampaignData::dispatch($campaign, $request->validated('data'));

        return response()->json(['message' => 'Data accepted for processing.'], 202);
    }

    public function report(?Campaign $campaign = null): JsonResponse
    {
        if ($campaign) {
            $data = Cache::remember("campaign_report_{$campaign->id}", 3600, fn () => $this->buildReport($campaign));
            return response()->json($data);
        }

        $data = Cache::remember('campaigns_report_all', 3600, function () {
            return Campaign::with(['client', 'campaignData'])->get()
                ->map(fn (Campaign $c) => $this->buildReport($c))
                ->toArray();
        });

        return response()->json($data);
    }

    public function analytics(): JsonResponse
    {
        $data = Cache::remember('campaigns_analytics', 3600, function () {
            $campaigns = Campaign::with(['client', 'campaignData'])->get();

            $report = $campaigns->map(function (Campaign $campaign) {
                $data = $campaign->campaignData;

                $customFieldCounts = $data
                    ->pluck('custom_fields')
                    ->filter()
                    ->flatMap(fn (array $fields) => array_keys($fields))
                    ->countBy()
                    ->toArray();

                return [
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'client_name' => $campaign->client->name,
                    'total_recipients' => $data->count(),
                    'custom_field_usage' => $customFieldCounts,
                ];
            });

            return [
                'total_campaigns' => $campaigns->count(),
                'total_recipients' => $campaigns->sum(fn ($c) => $c->campaignData->count()),
                'campaigns' => $report,
            ];
        });

        return response()->json($data);
    }

    private function buildReport(Campaign $campaign): array
    {
        $campaign->loadMissing(['client', 'campaignData']);

        return [
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'client_name' => $campaign->client->name,
            'start_date' => $campaign->start_date->toDateString(),
            'end_date' => $campaign->end_date?->toDateString(),
            'total_recipients' => $campaign->campaignData->count(),
            'recipients' => $campaign->campaignData->map(fn ($d) => [
                'user_id' => $d->user_id,
                'video_url' => $d->video_url,
                'custom_fields' => $d->custom_fields,
            ])->values(),
        ];
    }
}
