# Personalized Video Campaign Manager

A Laravel API for managing personalized video campaigns. Clients can create campaigns, add recipient data with personalized video URLs, and generate analytics reports.

## Getting Started

### Prerequisites

- Docker & Docker Compose

### Setup

```bash
git clone https://github.com/jakespencervermeulen-jpg/API-Video-Campaign-Manager
cd API-Video-Campaign-Manager
docker compose up -d --build
```

The setup script will automatically:
- Install Composer dependencies
- Generate the application key
- Wait for the database to be ready
- Run migrations
- Set file permissions

Once running:
- **API:** http://localhost:8088/api
- **Swagger Docs:** http://localhost:8088/api/documentation

### Running the Queue Worker

The data ingestion endpoint processes records asynchronously via Redis queues. Start a worker:

```bash
docker exec -it video-campaign-manager-app php artisan queue:work redis
```

### Running Tests

```bash
docker exec video-campaign-manager-app php artisan test
```

## API Endpoints

### POST /api/campaigns

Create a new campaign.

**Request:**
```json
{
    "client_id": 1,
    "name": "Summer Campaign",
    "start_date": "2025-07-01",
    "end_date": "2025-08-01"
}
```

**Response (201):**
```json
{
    "data": {
        "id": 1,
        "client_id": 1,
        "name": "Summer Campaign",
        "start_date": "2025-07-01",
        "end_date": "2025-08-01",
        "created_at": "2025-07-01T00:00:00+00:00",
        "updated_at": "2025-07-01T00:00:00+00:00"
    }
}
```

### POST /api/campaigns/{campaign_id}/data

Add recipient data to a campaign. Returns immediately with 202 and processes data in the background.

**Request:**
```json
{
    "data": [
        {
            "user_id": "recipient-1",
            "video_url": "https://example.com/video1.mp4",
            "custom_fields": {
                "first_name": "John",
                "company": "Acme"
            }
        },
        {
            "user_id": "recipient-2",
            "video_url": "https://example.com/video2.mp4"
        }
    ]
}
```

**Response (202):**
```json
{
    "message": "Data accepted for processing."
}
```

### GET /api/campaigns/report

Get a report for all campaigns including recipient details.

**Response (200):**
```json
[
    {
        "campaign_id": 1,
        "campaign_name": "Summer Campaign",
        "client_name": "Acme Corp",
        "start_date": "2025-07-01",
        "end_date": "2025-08-01",
        "total_recipients": 2,
        "recipients": [
            {
                "user_id": "recipient-1",
                "video_url": "https://example.com/video1.mp4",
                "custom_fields": {"first_name": "John", "company": "Acme"}
            }
        ]
    }
]
```

### GET /api/campaigns/report/{campaign_id}

Get a report for a single campaign.

### GET /api/campaigns/analytics

Get aggregated analytics across all campaigns.

**Response (200):**
```json
{
    "total_campaigns": 2,
    "total_recipients": 150,
    "campaigns": [
        {
            "campaign_id": 1,
            "campaign_name": "Summer Campaign",
            "client_name": "Acme Corp",
            "total_recipients": 100,
            "custom_field_usage": {
                "first_name": 100,
                "company": 75
            }
        }
    ]
}
```

## Background Job System

The `POST /api/campaigns/{campaign_id}/data` endpoint is designed for high-volume data ingestion. When a request is received:

1. The endpoint validates the payload and immediately returns HTTP 202.
2. A `ProcessCampaignData` job is dispatched to a Redis-backed queue.
3. The job processes the data using an efficient `upsert` query to handle both inserts and updates in a single database operation.
4. If a `user_id` already exists for the campaign, the record is updated and a warning is logged via Laravel's logging system.
5. After processing, report and analytics caches are invalidated so subsequent reads reflect the latest data.

### Duplicate Handling

When a duplicate `user_id` is detected for a campaign, the system uses an **update** strategy — the existing record is updated with the new `video_url` and `custom_fields`. All duplicate attempts are logged using Laravel's logging service for visibility.

### Custom Fields

The `custom_fields` column uses a JSON type, allowing arbitrary key-value data to be stored without schema changes. These fields are surfaced in both the report endpoint (per recipient) and the analytics endpoint (usage counts per field name).

## Analytics Command

Generate analytics reports from the command line:

```bash
# All campaigns
docker exec video-campaign-manager-app php artisan campaigns:report

# Single campaign
docker exec video-campaign-manager-app php artisan campaigns:report 1
```
