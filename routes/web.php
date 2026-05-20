<?php

use App\Http\Controllers\WalletPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Blade Test Page
|--------------------------------------------------------------------------
|
| These routes are only for manual browser testing. They reuse WalletService,
| so the Blade page exercises the same locked deposit / withdrawal logic as
| the JSON API endpoints.
|
*/

Route::redirect('/', '/wallet');

Route::get('/wallet/{userId?}', [WalletPageController::class, 'show'])
    ->whereNumber('userId')
    ->name('wallet.page');

Route::post('/wallet/{userId}/deposit', [WalletPageController::class, 'deposit'])
    ->whereNumber('userId')
    ->name('wallet.deposit');

Route::post('/wallet/{userId}/withdraw', [WalletPageController::class, 'withdraw'])
    ->whereNumber('userId')
    ->name('wallet.withdraw');
