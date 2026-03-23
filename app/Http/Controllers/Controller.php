<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Video Campaign Manager API',
    description: 'API for managing personalized video campaigns',
)]
abstract class Controller
{

}
