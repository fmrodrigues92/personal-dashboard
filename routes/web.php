<?php

use App\Http\Controllers\Billing\BillingInvoiceController;
use App\Http\Controllers\Das\DasCalculationController;
use App\Http\Controllers\ProLabore\ProLaboreController;
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

    Route::get('dashboard', [DasCalculationController::class, 'dashboard'])->name('dashboard');

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

require __DIR__.'/settings.php';
