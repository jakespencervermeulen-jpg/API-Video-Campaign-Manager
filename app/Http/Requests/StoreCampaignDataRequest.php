<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'array', 'min:1'],
            'data.*.user_id' => ['required', 'string'],
            'data.*.video_url' => ['required', 'url'],
            'data.*.custom_fields' => ['nullable', 'array'],
        ];
    }
}
