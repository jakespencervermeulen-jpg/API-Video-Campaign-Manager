<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignDataRequest;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Jobs\ProcessCampaignData;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class CampaignController extends Controller
{
    #[OA\Post(
        path: '/api/campaigns',
        summary: 'Create a new campaign',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['client_id', 'name', 'start_date'],
                properties: [
                    new OA\Property(property: 'client_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Summer Campaign'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2025-07-01'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2025-08-01', nullable: true),
                ]
            )
        ),
        tags: ['Campaigns'],
        responses: [
            new OA\Response(response: 201, description: 'Campaign created successfully', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'client_id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Summer Campaign'),
                        new OA\Property(property: 'start_date', type: 'string', example: '2025-07-01'),
                        new OA\Property(property: 'end_date', type: 'string', example: '2025-08-01', nullable: true),
                        new OA\Property(property: 'created_at', type: 'string', example: '2025-07-01T00:00:00+00:00'),
                        new OA\Property(property: 'updated_at', type: 'string', example: '2025-07-01T00:00:00+00:00'),
                    ], type: 'object'),
                ]
            )),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = Campaign::create($request->validated());

        return (new CampaignResource($campaign))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Post(
        path: '/api/campaigns/{campaign_id}/data',
        summary: 'Add user data to a campaign',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            required: ['user_id', 'video_url'],
                            properties: [
                                new OA\Property(property: 'user_id', type: 'string', example: 'recipient-1'),
                                new OA\Property(property: 'video_url', type: 'string', format: 'url', example: 'https://example.com/video1.mp4'),
                                new OA\Property(property: 'custom_fields', type: 'object', example: '{"first_name": "John", "company": "Acme"}', nullable: true),
                            ]
                        )
                    ),
                ]
            )
        ),
        tags: ['Campaign Data'],
        parameters: [
            new OA\Parameter(name: 'campaign_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 202, description: 'Data accepted for processing', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Data accepted for processing.'),
                ]
            )),
            new OA\Response(response: 404, description: 'Campaign not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function storeData(StoreCampaignDataRequest $request, Campaign $campaign): JsonResponse
    {
        ProcessCampaignData::dispatch($campaign, $request->validated('data'));

        return response()->json(['message' => 'Data accepted for processing.'], 202);
    }

    #[OA\Get(
        path: '/api/campaigns/report/{campaign_id}',
        summary: 'Get campaign report with recipients',
        tags: ['Reports'],
        parameters: [
            new OA\Parameter(name: 'campaign_id', in: 'path', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Campaign report', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'campaign_id', type: 'integer', example: 1),
                    new OA\Property(property: 'campaign_name', type: 'string', example: 'Summer Campaign'),
                    new OA\Property(property: 'client_name', type: 'string', example: 'Acme Corp'),
                    new OA\Property(property: 'start_date', type: 'string', example: '2025-07-01'),
                    new OA\Property(property: 'end_date', type: 'string', example: '2025-08-01', nullable: true),
                    new OA\Property(property: 'total_recipients', type: 'integer', example: 2),
                    new OA\Property(
                        property: 'recipients',
                        type: 'array',
                        items: new OA\Items(properties: [
                            new OA\Property(property: 'user_id', type: 'string', example: 'recipient-1'),
                            new OA\Property(property: 'video_url', type: 'string', example: 'https://example.com/video1.mp4'),
                            new OA\Property(property: 'custom_fields', type: 'object', example: '{"first_name": "John"}', nullable: true),
                        ])
                    ),
                ]
            )),
            new OA\Response(response: 404, description: 'Campaign not found'),
        ]
    )]
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

    #[OA\Get(
        path: '/api/campaigns/analytics',
        summary: 'Get aggregated analytics across all campaigns',
        tags: ['Reports'],
        responses: [
            new OA\Response(response: 200, description: 'Analytics report', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'total_campaigns', type: 'integer', example: 5),
                    new OA\Property(property: 'total_recipients', type: 'integer', example: 150),
                    new OA\Property(
                        property: 'campaigns',
                        type: 'array',
                        items: new OA\Items(properties: [
                            new OA\Property(property: 'campaign_id', type: 'integer', example: 1),
                            new OA\Property(property: 'campaign_name', type: 'string', example: 'Summer Campaign'),
                            new OA\Property(property: 'client_name', type: 'string', example: 'Acme Corp'),
                            new OA\Property(property: 'total_recipients', type: 'integer', example: 50),
                            new OA\Property(property: 'custom_field_usage', type: 'object', example: '{"first_name": 50, "company": 30}'),
                        ])
                    ),
                ]
            )),
        ]
    )]
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
