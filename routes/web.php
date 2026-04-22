<?php

use App\Http\Controllers\Billing\BillingInvoiceController;
use App\Http\Controllers\Das\DasCalculationController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::group([
    'as' => '',
    'prefix' => '',
    'middleware' => ['auth', 'verified'],
], function () {

    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::group([
        'as' => 'billing-invoices.',
        'prefix' => 'billing-invoices',
    ], function () {

        Route::get('/', [BillingInvoiceController::class, 'index'])->name('index');
        Route::post('/', [BillingInvoiceController::class, 'store'])->name('store');
        Route::delete('{billingInvoice}', [BillingInvoiceController::class, 'destroy'])->whereNumber('billingInvoice')->name('destroy');

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
});

require __DIR__.'/settings.php';
