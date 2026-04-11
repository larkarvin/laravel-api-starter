<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are prefixed with /api and assigned the "api" middleware
| group. Version-specific routes live in routes/api/v{n}.php and are
| loaded below under their respective prefix + namespace groups.
|
*/

Route::prefix('v1')->name('v1.')->group(base_path('routes/api/v1.php'));
