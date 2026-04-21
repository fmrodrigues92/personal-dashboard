<?php

use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Billing\BillingInvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('api.login');

Route::group([
    'as' => 'api.',
    'prefix' => '',
    'middleware' => ['auth:sanctum'],
], function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::group([
        'as' => 'billing-invoices.',
        'prefix' => 'billing-invoices',
    ], function () {

        Route::get('/', [BillingInvoiceController::class, 'index'])->name('index');
        Route::post('/', [BillingInvoiceController::class, 'store'])->name('store');
        Route::delete('{billingInvoice}', [BillingInvoiceController::class, 'destroy'])->whereNumber('billingInvoice')->name('destroy');

        Route::get('simulations', [BillingInvoiceController::class, 'indexSimulations'])->name('simulations.index');
        Route::post('simulations', [BillingInvoiceController::class, 'storeSimulations'])->name('simulations.store');
        Route::delete('simulations', [BillingInvoiceController::class, 'destroySimulations'])->name('simulations.destroy');
    });
});
