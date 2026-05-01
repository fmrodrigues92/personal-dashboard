<?php

use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Billing\BillingInvoiceController;
use App\Http\Controllers\Das\DasCalculationController;
use App\Http\Controllers\ProLabore\ProLaboreController;
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

    Route::group([
        'as' => 'das-calculations.',
        'prefix' => 'das-calculations',
    ], function () {

        Route::get('timeline', [DasCalculationController::class, 'timeline'])->name('timeline');
        Route::patch('tax-breakdowns/{taxBreakdown}', [DasCalculationController::class, 'updateTaxBreakdown'])->whereNumber('taxBreakdown')->name('tax-breakdowns.update');
        Route::get('/', [DasCalculationController::class, 'index'])->name('index');
        Route::post('/', [DasCalculationController::class, 'store'])->name('store');
        Route::get('{dasCalculation}', [DasCalculationController::class, 'show'])->whereNumber('dasCalculation')->name('show');
    });

    Route::group([
        'as' => 'pro-labore.',
        'prefix' => 'pro-labore',
    ], function () {

        Route::get('/', [ProLaboreController::class, 'index'])->name('index');
        Route::post('/', [ProLaboreController::class, 'store'])->name('store');
        Route::delete('{proLaboreReceipt}', [ProLaboreController::class, 'destroy'])->whereNumber('proLaboreReceipt')->name('destroy');

        Route::post('simulations', [ProLaboreController::class, 'storeSimulations'])->name('simulations.store');
        Route::delete('simulations', [ProLaboreController::class, 'destroySimulations'])->name('simulations.destroy');
    });
});
