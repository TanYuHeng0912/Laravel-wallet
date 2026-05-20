<?php

use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wallet API Routes
|--------------------------------------------------------------------------
|
| The practical test asks for simple API endpoints, so the routes intentionally
| accept a user id in the URL instead of adding authentication scaffolding.
| In a production product this would normally come from the authenticated user.
|
*/

Route::prefix('users/{userId}/wallet')->whereNumber('userId')->group(function (): void {
    Route::get('/', [WalletController::class, 'show']);
    Route::get('/transactions', [WalletController::class, 'transactions']);
    Route::post('/deposit', [WalletController::class, 'deposit']);
    Route::post('/withdraw', [WalletController::class, 'withdraw']);
});
