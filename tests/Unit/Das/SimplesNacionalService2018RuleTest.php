<?php

use App\Models\BillingInvoice;
use App\Src\Das\Rules\SimplesNacionalService2018Rule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

test('calculate returns the expected das for two national invoices and one international invoice', function () {
    $rule = new SimplesNacionalService2018Rule;

    /** @var Collection<int, BillingInvoice> $monthlyInvoices */
    $monthlyInvoices = collect([
        new BillingInvoice([
            'id' => 1,
            'billing_date' => '2026-01-10 10:00:00',
            'type' => 'national',
            'cnae' => '6201500',
            'cnae_annex' => 3,
            'cnae_calculation' => null,
            'customer_name' => 'Customer A',
            'customer_external_id' => 'cust_a',
            'amount_brl' => 13000.00,
            'amount_usd' => null,
            'usd_brl_exchange_rate' => null,
            'is_simulation' => false,
        ]),
        new BillingInvoice([
            'id' => 2,
            'billing_date' => '2026-01-15 10:00:00',
            'type' => 'national',
            'cnae' => '6201500',
            'cnae_annex' => 3,
            'cnae_calculation' => null,
            'customer_name' => 'Customer B',
            'customer_external_id' => 'cust_b',
            'amount_brl' => 8107.52,
            'amount_usd' => null,
            'usd_brl_exchange_rate' => null,
            'is_simulation' => false,
        ]),
        new BillingInvoice([
            'id' => 3,
            'billing_date' => '2026-01-20 10:00:00',
            'type' => 'International',
            'cnae' => '6201500',
            'cnae_annex' => 3,
            'cnae_calculation' => null,
            'customer_name' => 'Customer C',
            'customer_external_id' => 'cust_c',
            'amount_brl' => 20000.00,
            'amount_usd' => 4000.00,
            'usd_brl_exchange_rate' => 5.00,
            'is_simulation' => false,
        ]),
    ]);

    $calculatedDas = $rule->calculate(
        monthlyInvoices: $monthlyInvoices,
        referenceMonth: CarbonImmutable::parse('2026-01-01'),
        rollingRevenueBrl: 156000.00,
        factorRApplied: true,
    );

    expect($calculatedDas->monthlyRevenueBrl)->toBe(41107.52)
        ->and($calculatedDas->dasTotalBrl)->toBe(1877.26)
        ->and($calculatedDas->metadata['rolling_revenue_brl'])->toBe(156000.0)
        ->and($calculatedDas->metadata['rate_summary'])->toContain(
            [
                'annex_used' => 3,
                'invoice_type' => 'national',
                'effective_rate_percentage' => 6.0,
                'exempt_components' => [],
            ],
            [
                'annex_used' => 3,
                'invoice_type' => 'international',
                'effective_rate_percentage' => 3.054,
                'exempt_components' => ['iss', 'cofins', 'pis_pasep'],
            ],
        );
});
