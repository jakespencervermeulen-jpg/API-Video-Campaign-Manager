<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignDataRequest;
use App\Http\Requests\StoreCampaignRequest;
use App\Jobs\ProcessCampaignData;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
            return response()->json($this->buildReport($campaign));
        }

        $campaigns = Campaign::with('client')->get();

        return response()->json($campaigns->map(fn (Campaign $c) => $this->buildReport($c)));
    }

    private function buildReport(Campaign $campaign): array
    {
        $campaign->loadMissing('client');
        $data = $campaign->campaignData;

        $customFieldKeys = $data
            ->pluck('custom_fields')
            ->filter()
            ->flatMap(fn (array $fields) => array_keys($fields))
            ->unique()
            ->values();

        return [
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'client_name' => $campaign->client->name,
            'start_date' => $campaign->start_date->toDateString(),
            'end_date' => $campaign->end_date?->toDateString(),
            'total_recipients' => $data->count(),
            'unique_custom_fields' => $customFieldKeys,
        ];
    }
}
