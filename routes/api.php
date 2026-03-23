<?php

use App\Http\Controllers\CampaignController;
use Illuminate\Support\Facades\Route;

Route::post('/campaigns', [CampaignController::class, 'store']);
