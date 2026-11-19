<?php

use App\Http\Controllers\Api\V1\ChargeController;
use App\Http\Controllers\Api\V1\PartnerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DelPago Partner API — v1
|--------------------------------------------------------------------------
| Stateless, Bearer-key auth via the 'partner' middleware (scope optional).
| All JSON. Non-custodial: charges settle to the merchant's own connector.
*/

Route::prefix('v1')->group(function () {

    Route::post('/charges', [ChargeController::class, 'store'])
        ->middleware(['partner:charges.write', 'throttle:120,1']);

    Route::get('/charges/{id}', [ChargeController::class, 'show'])
        ->middleware(['partner:charges.read', 'throttle:240,1'])
        ->where('id', '[A-Za-z0-9\-]+');

    Route::get('/balance', [PartnerController::class, 'balance'])
        ->middleware(['partner:charges.read', 'throttle:120,1']);
});
