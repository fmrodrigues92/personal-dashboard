<?php

use App\Models\BillingInvoice;
use App\Models\DasCalculation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();

    actingAs($this->user);
    Sanctum::actingAs($this->user);
});

test('authenticated users can calculate das for a month using the 2018 rule', function () {
    BillingInvoice::query()->create([
        'user_id' => $this->user->id,
        'billing_date' => '2026-04-10 10:00:00',
        'type' => 'national',
        'cnae' => '6201500',
        'cnae_annex' => 3,
        'cnae_calculation' => null,
        'customer_name' => 'ACME Brazil',
        'customer_external_id' => 'cust_001',
        'amount_brl' => 10000.00,
        'amount_usd' => null,
        'usd_brl_exchange_rate' => null,
        'is_simulation' => false,
    ]);

    BillingInvoice::query()->create([
        'user_id' => $this->user->id,
        'billing_date' => '2026-04-15 10:00:00',
        'type' => 'international',
        'cnae' => '6202300',
        'cnae_annex' => 5,
        'cnae_calculation' => null,
        'customer_name' => 'ACME Global',
        'customer_external_id' => 'cust_002',
        'amount_brl' => 5000.00,
        'amount_usd' => 1000.00,
        'usd_brl_exchange_rate' => 5.00,
        'is_simulation' => false,
    ]);

    $response = postJson(route('api.das-calculations.store'), [
        'reference_month' => '2026-04-01',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.rule_version', 'simples_nacional_service_2018')
        ->assertJsonPath('data.factor_r_applied', true)
        ->assertJsonPath('data.monthly_revenue_brl', 15000)
        ->assertJsonPath('data.das_total_brl', 752.7)
        ->assertJsonPath('data.metadata.rate_summary.0.effective_rate_percentage', 6)
        ->assertJsonPath('data.metadata.rate_summary.1.effective_rate_percentage', 3.054)
        ->assertJsonPath('data.is_projection', false);

    $this->assertDatabaseHas('billing_invoices', [
        'customer_external_id' => 'cust_001',
        'cnae_calculation' => 3,
    ]);

    $this->assertDatabaseHas('billing_invoices', [
        'customer_external_id' => 'cust_002',
        'cnae_calculation' => 3,
    ]);

    $this->assertDatabaseHas('das_calculations', [
        'reference_month' => '2026-04-01 00:00:00',
        'rule_version' => 'simples_nacional_service_2018',
        'monthly_revenue_brl' => 15000,
        'das_total_brl' => 752.7,
        'is_projection' => 0,
    ]);

    $this->assertDatabaseHas('das_calculation_tax_breakdowns', [
        'tax_component_code' => 'iss',
        'invoice_type' => 'international',
        'calculated_amount_brl' => '0.00',
    ]);
});

test('past month das calculations ignore simulation invoices', function () {
    $pastMonth = now()->startOfMonth()->subMonth();

    BillingInvoice::query()->create([
        'user_id' => $this->user->id,
        'billing_date' => $pastMonth->copy()->day(10)->format('Y-m-d H:i:s'),
        'type' => 'national',
        'cnae' => '6201500',
        'cnae_annex' => 3,
        'cnae_calculation' => null,
        'customer_name' => 'ACME Real',
        'customer_external_id' => 'cust_real',
        'amount_brl' => 10000.00,
        'amount_usd' => null,
        'usd_brl_exchange_rate' => null,
        'is_simulation' => false,
    ]);

    BillingInvoice::query()->create([
        'user_id' => $this->user->id,
        'billing_date' => $pastMonth->copy()->day(15)->format('Y-m-d H:i:s'),
        'type' => 'national',
        'cnae' => null,
        'cnae_annex' => null,
        'cnae_calculation' => null,
        'customer_name' => null,
        'customer_external_id' => null,
        'amount_brl' => 50000.00,
        'amount_usd' => null,
        'usd_brl_exchange_rate' => null,
        'is_simulation' => true,
    ]);

    $response = postJson(route('api.das-calculations.store'), [
        'reference_month' => $pastMonth->toDateString(),
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.monthly_revenue_brl', 10000)
        ->assertJsonPath('data.das_total_brl', 600)
        ->assertJsonPath('data.is_projection', false);
});

test('current month das timeline uses simulation invoices when they exist', function () {
    $currentMonth = now()->startOfMonth();
    $previousMonth = $currentMonth->subMonth();

    BillingInvoice::query()->create([
        'user_id' => $this->user->id,
        'billing_date' => $previousMonth->copy()->day(10)->format('Y-m-d H:i:s'),
        'type' => 'national',
        'cnae' => '6201500',
        'cnae_annex' => 3,
        'cnae_calculation' => null,
        'customer_name' => 'ACME Real',
        'customer_external_id' => 'cust_real',
        'amount_brl' => 10000.00,
        'amount_usd' => null,
        'usd_brl_exchange_rate' => null,
        'is_simulation' => false,
    ]);

    postJson(route('api.billing-invoices.simulations.store'), [
        'type' => 'national',
        'start_date' => $currentMonth->toDateString(),
        'end_date' => $currentMonth->toDateString(),
        'amount_brl' => 10000.00,
    ])->assertCreated();

    $response = $this->getJson(route('api.das-calculations.timeline', [
        'reference_month' => $currentMonth->toDateString(),
    ]));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference_month', $currentMonth->toDateString())
        ->assertJsonPath('data.0.monthly_revenue_brl', 10000)
        ->assertJsonPath('data.0.das_total_brl', 600)
        ->assertJsonPath('data.0.is_projection', true);
});

test('initial activity uses proportionalized rbt12 for the first month', function () {
    BillingInvoice::query()->create([
        'user_id' => $this->user->id,
        'billing_date' => '2026-04-10 10:00:00',
        'type' => 'national',
        'cnae' => '6201500',
        'cnae_annex' => 3,
        'cnae_calculation' => null,
        'customer_name' => 'ACME Start',
        'customer_external_id' => 'cust_start',
        'amount_brl' => 20000.00,
        'amount_usd' => null,
        'usd_brl_exchange_rate' => null,
        'is_simulation' => false,
    ]);

    $response = postJson(route('api.das-calculations.store'), [
        'reference_month' => '2026-04-01',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.metadata.rbt12.national_brl', 240000)
        ->assertJsonPath('data.metadata.rbt12.international_brl', 0)
        ->assertJsonPath('data.metadata.rbt12.total_brl', 240000)
        ->assertJsonPath('data.das_total_brl', 1460);
});

test('initial activity with only international revenue does not break accounting scenario', function () {
    BillingInvoice::query()->create([
        'user_id' => $this->user->id,
        'billing_date' => '2026-04-10 10:00:00',
        'type' => 'international',
        'cnae' => '6201500',
        'cnae_annex' => 3,
        'cnae_calculation' => null,
        'customer_name' => 'ACME Export',
        'customer_external_id' => 'cust_export',
        'amount_brl' => 20000.00,
        'amount_usd' => 4000.00,
        'usd_brl_exchange_rate' => 5.00,
        'is_simulation' => false,
    ]);

    $response = postJson(route('api.das-calculations.store'), [
        'reference_month' => '2026-04-01',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.metadata.rbt12.national_brl', 0)
        ->assertJsonPath('data.metadata.rbt12.international_brl', 240000)
        ->assertJsonPath('data.metadata.rbt12.total_brl', 240000)
        ->assertJsonPath('data.metadata.das_contabilidade.das_total_brl', 610.8);
});

test('authenticated users can list das calculations through the api', function () {
    $dasCalculation = DasCalculation::query()->create([
        'user_id' => $this->user->id,
        'reference_month' => '2026-04-01',
        'rule_version' => 'simples_nacional_service_2018',
        'factor_r_applied' => true,
        'monthly_revenue_brl' => 10000.00,
        'das_total_brl' => 600.00,
        'is_projection' => false,
        'metadata' => ['invoice_count' => 1],
    ]);

    $dasCalculation->taxBreakdowns()->create([
        'tax_component_code' => 'cpp',
        'annex_used' => 3,
        'invoice_type' => 'national',
        'calculated_amount_brl' => 260.40,
        'adjusted_amount_brl' => null,
        'rate_percentage' => 2.604,
    ]);

    $response = $this->getJson(route('api.das-calculations.index'));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.das_total_brl', 600)
        ->assertJsonPath('data.0.tax_breakdowns.0.tax_component_code', 'cpp');
});

test('authenticated users can show one das calculation through the api', function () {
    $dasCalculation = DasCalculation::query()->create([
        'user_id' => $this->user->id,
        'reference_month' => '2026-04-01',
        'rule_version' => 'simples_nacional_service_2018',
        'factor_r_applied' => true,
        'monthly_revenue_brl' => 10000.00,
        'das_total_brl' => 600.00,
        'is_projection' => false,
        'metadata' => ['invoice_count' => 1],
    ]);

    $dasCalculation->taxBreakdowns()->create([
        'tax_component_code' => 'cpp',
        'annex_used' => 3,
        'invoice_type' => 'national',
        'calculated_amount_brl' => 260.40,
        'adjusted_amount_brl' => null,
        'rate_percentage' => 2.604,
    ]);

    $response = $this->getJson(route('api.das-calculations.show', $dasCalculation->id));

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $dasCalculation->id)
        ->assertJsonPath('data.tax_breakdowns.0.calculated_amount_brl', 260.4);
});

test('authenticated users can correct a das tax breakdown amount', function () {
    $dasCalculation = DasCalculation::query()->create([
        'user_id' => $this->user->id,
        'reference_month' => '2026-04-01',
        'rule_version' => 'simples_nacional_service_2018',
        'factor_r_applied' => true,
        'monthly_revenue_brl' => 10000.00,
        'das_total_brl' => 600.00,
        'is_projection' => false,
        'metadata' => ['invoice_count' => 1],
    ]);

    $taxBreakdown = $dasCalculation->taxBreakdowns()->create([
        'tax_component_code' => 'cpp',
        'annex_used' => 3,
        'invoice_type' => 'national',
        'calculated_amount_brl' => 260.40,
        'adjusted_amount_brl' => null,
        'rate_percentage' => 2.604,
    ]);

    $response = patchJson(route('api.das-calculations.tax-breakdowns.update', $taxBreakdown->id), [
        'adjusted_amount_brl' => 260.41,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.tax_breakdowns.0.adjusted_amount_brl', 260.41)
        ->assertJsonPath('data.tax_breakdowns.0.effective_amount_brl', 260.41)
        ->assertJsonPath('data.das_total_brl', 260.41);
});

test('projected das calculations can not receive manual corrections', function () {
    $dasCalculation = DasCalculation::query()->create([
        'user_id' => $this->user->id,
        'reference_month' => '2027-04-01',
        'rule_version' => 'simples_nacional_service_2018',
        'factor_r_applied' => true,
        'monthly_revenue_brl' => 10000.00,
        'das_total_brl' => 600.00,
        'is_projection' => true,
        'metadata' => ['invoice_count' => 1],
    ]);

    $taxBreakdown = $dasCalculation->taxBreakdowns()->create([
        'tax_component_code' => 'cpp',
        'annex_used' => 3,
        'invoice_type' => 'national',
        'calculated_amount_brl' => 260.40,
        'adjusted_amount_brl' => null,
        'rate_percentage' => 2.604,
    ]);

    $response = patchJson(route('api.das-calculations.tax-breakdowns.update', $taxBreakdown->id), [
        'adjusted_amount_brl' => 260.41,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['adjusted_amount_brl']);
});

test('authenticated users can get the das timeline', function () {
    $currentMonth = now()->startOfMonth();

    DasCalculation::query()->create([
        'user_id' => $this->user->id,
        'reference_month' => $currentMonth->toDateString(),
        'rule_version' => 'simples_nacional_service_2018',
        'factor_r_applied' => true,
        'monthly_revenue_brl' => 10000.00,
        'das_total_brl' => 600.00,
        'is_projection' => false,
        'metadata' => ['invoice_count' => 1],
    ]);

    postJson(route('api.billing-invoices.simulations.store'), [
        'type' => 'national',
        'start_date' => $currentMonth->addMonth()->toDateString(),
        'end_date' => $currentMonth->addMonth()->toDateString(),
        'amount_brl' => 1000.00,
    ])->assertCreated();

    $response = $this->getJson(route('api.das-calculations.timeline', [
        'reference_month' => $currentMonth->toDateString(),
        'months_before' => 1,
        'months_after' => 1,
    ]));

    $response
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.1.reference_month', $currentMonth->toDateString())
        ->assertJsonPath('data.1.das_total_brl', 600)
        ->assertJsonPath('data.2.is_projection', true);
});

test('timeline returns only the requested month when reference_month is provided', function () {
    $currentMonth = now()->startOfMonth();

    DasCalculation::query()->create([
        'user_id' => $this->user->id,
        'reference_month' => $currentMonth->toDateString(),
        'rule_version' => 'simples_nacional_service_2018',
        'factor_r_applied' => true,
        'monthly_revenue_brl' => 10000.00,
        'das_total_brl' => 600.00,
        'is_projection' => false,
        'metadata' => ['invoice_count' => 1],
    ]);

    $response = $this->getJson(route('api.das-calculations.timeline', [
        'reference_month' => $currentMonth->format('Y-m'),
    ]));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference_month', $currentMonth->toDateString())
        ->assertJsonPath('data.0.das_total_brl', 600);
});

test('authenticated users can only list their own das calculations', function () {
    DasCalculation::query()->create([
        'user_id' => $this->user->id,
        'reference_month' => '2026-04-01',
        'rule_version' => 'simples_nacional_service_2018',
        'factor_r_applied' => true,
        'monthly_revenue_brl' => 10000.00,
        'das_total_brl' => 600.00,
        'is_projection' => false,
        'metadata' => ['invoice_count' => 1],
    ]);

    DasCalculation::query()->create([
        'user_id' => User::factory()->create()->id,
        'reference_month' => '2026-05-01',
        'rule_version' => 'simples_nacional_service_2018',
        'factor_r_applied' => true,
        'monthly_revenue_brl' => 20000.00,
        'das_total_brl' => 1200.00,
        'is_projection' => false,
        'metadata' => ['invoice_count' => 1],
    ]);

    $response = $this->getJson(route('api.das-calculations.index'));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.monthly_revenue_brl', 10000);
});

test('users can not show das calculations that belong to another user', function () {
    $dasCalculation = DasCalculation::query()->create([
        'user_id' => User::factory()->create()->id,
        'reference_month' => '2026-04-01',
        'rule_version' => 'simples_nacional_service_2018',
        'factor_r_applied' => true,
        'monthly_revenue_brl' => 10000.00,
        'das_total_brl' => 600.00,
        'is_projection' => false,
        'metadata' => ['invoice_count' => 1],
    ]);

    $response = $this->getJson(route('api.das-calculations.show', $dasCalculation->id));

    $response->assertNotFound();
});
