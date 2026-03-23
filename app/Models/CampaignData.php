<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignData extends Model
{
    protected $table = 'campaign_data';

    protected $fillable = ['campaign_id', 'user_id', 'video_url', 'custom_fields'];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
