<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignDataRequest;
use App\Http\Requests\StoreCampaignRequest;
use App\Jobs\ProcessCampaignData;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;

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
}
