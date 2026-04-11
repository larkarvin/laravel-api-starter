<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Traits\ApiResponder;
use Illuminate\Routing\Controller;

abstract class ApiController extends Controller
{
    use ApiResponder;
}
