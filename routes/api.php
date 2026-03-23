<?php

use App\Http\Controllers\CampaignController;
use Illuminate\Support\Facades\Route;

Route::post('/campaigns', [CampaignController::class, 'store']);
Route::post('/campaigns/{campaign}/data', [CampaignController::class, 'storeData']);
Route::get('/campaigns/report/{campaign?}', [CampaignController::class, 'report']);
