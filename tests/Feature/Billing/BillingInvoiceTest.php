<?php

use App\Models\BillingInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\from;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();

    actingAs($this->user);
    Sanctum::actingAs($this->user);
});

test('authenticated users can create a real national billing invoice through the api', function () {
    $response = postJson(route('api.billing-invoices.store'), [
        'billing_date' => '2026-04-10 15:30:00',
        'type' => 'national',
        'cnae' => '6201500',
        'cnae_annex' => 3,
        'customer_name' => 'ACME Brazil',
        'customer_external_id' => 'cust_001',
        'amount_brl' => 1500.75,
    ]);

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'billing_date',
                'type',
                'cnae',
                'cnae_annex',
                'cnae_calculation',
                'customer_name',
                'customer_external_id',
                'amount_brl',
                'amount_usd',
                'usd_brl_exchange_rate',
                'is_simulation',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
        ])
        ->assertJsonPath('data.type', 'national')
        ->assertJsonPath('data.is_simulation', false)
        ->assertJsonPath('data.amount_usd', null)
        ->assertJsonPath('data.cnae_calculation', null)
        ->assertJsonPath('data.usd_brl_exchange_rate', null);

    $this->assertDatabaseHas('billing_invoices', [
        'type' => 'national',
        'customer_name' => 'ACME Brazil',
        'customer_external_id' => 'cust_001',
        'amount_brl' => '1500.75',
        'is_simulation' => 0,
        'deleted_at' => null,
    ]);
});

test('web requests use the same controller and redirect after creating a billing invoice', function () {
    $response = from('/dashboard')->post(route('billing-invoices.store'), [
        'billing_date' => '2026-04-10 15:30:00',
        'type' => 'national',
        'cnae' => '6201500',
        'cnae_annex' => 3,
        'customer_name' => 'ACME Brazil',
        'customer_external_id' => 'cust_001',
        'amount_brl' => 1500.75,
    ]);

    $response->assertRedirect('/dashboard');

    $this->assertDatabaseCount('billing_invoices', 1);
});

test('international invoices require usd fields', function () {
    $response = postJson(route('api.billing-invoices.store'), [
        'billing_date' => '2026-04-10 15:30:00',
        'type' => 'international',
        'cnae' => '6201500',
        'cnae_annex' => 3,
        'customer_name' => 'ACME Global',
        'customer_external_id' => 'cust_002',
        'amount_brl' => 2200.00,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'amount_usd',
            'usd_brl_exchange_rate',
        ]);
});

test('authenticated users can create billing invoice simulations in batch through the api', function () {
    $response = postJson(route('api.billing-invoices.simulations.store'), [
        'type' => 'national',
        'start_date' => '2026-01-10',
        'end_date' => '2026-03-25',
        'amount_brl' => 1000.00,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('meta.created_count', 3)
        ->assertJsonCount(3, 'data');

    expect(BillingInvoice::query()->count())->toBe(3);

    expect(
        BillingInvoice::query()
            ->orderBy('billing_date')
            ->get()
            ->map(fn (BillingInvoice $billingInvoice): array => [
                'billing_date' => $billingInvoice->billing_date->format('Y-m-d H:i:s'),
                'is_simulation' => $billingInvoice->is_simulation,
                'amount_brl' => $billingInvoice->amount_brl,
            ])
            ->all()
    )->toBe([
        [
            'billing_date' => '2026-01-01 00:00:00',
            'is_simulation' => true,
            'amount_brl' => 1000.0,
        ],
        [
            'billing_date' => '2026-02-01 00:00:00',
            'is_simulation' => true,
            'amount_brl' => 1000.0,
        ],
        [
            'billing_date' => '2026-03-01 00:00:00',
            'is_simulation' => true,
            'amount_brl' => 1000.0,
        ],
    ]);
});

test('authenticated users can list billing invoices through the api', function () {
    BillingInvoice::query()->create(realBillingInvoiceAttributes([
        'billing_date' => '2026-02-01 00:00:00',
        'customer_external_id' => 'cust_101',
    ]));

    BillingInvoice::query()->create(simulatedBillingInvoiceAttributes([
        'billing_date' => '2026-03-01 00:00:00',
    ]));

    $response = $this->getJson(route('api.billing-invoices.index'));

    $response
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.billing_date', '2026-03-01T00:00:00.000000Z')
        ->assertJsonPath('data.0.is_simulation', true)
        ->assertJsonPath('data.1.customer_external_id', 'cust_101');
});

test('authenticated users can list only billing invoice simulations through the api', function () {
    BillingInvoice::query()->create(realBillingInvoiceAttributes([
        'customer_external_id' => 'cust_201',
    ]));

    BillingInvoice::query()->create(simulatedBillingInvoiceAttributes([
        'billing_date' => '2026-01-01 00:00:00',
    ]));

    BillingInvoice::query()->create(simulatedBillingInvoiceAttributes([
        'billing_date' => '2026-02-01 00:00:00',
    ]));

    $response = $this->getJson(route('api.billing-invoices.simulations.index'));

    $response
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.billing_date', '2026-02-01T00:00:00.000000Z')
        ->assertJsonPath('data.0.is_simulation', true)
        ->assertJsonPath('data.1.billing_date', '2026-01-01T00:00:00.000000Z');
});

test('deleting a real billing invoice uses soft deletes', function () {
    $billingInvoice = BillingInvoice::query()->create(realBillingInvoiceAttributes());

    $response = deleteJson(route('api.billing-invoices.destroy', $billingInvoice));

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Billing invoice deleted.');

    $this->assertSoftDeleted($billingInvoice);
});

test('deleting a simulated billing invoice removes it physically', function () {
    $billingInvoice = BillingInvoice::query()->create(simulatedBillingInvoiceAttributes());

    $response = deleteJson(route('api.billing-invoices.destroy', $billingInvoice));

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Billing invoice deleted.');

    $this->assertModelMissing($billingInvoice);
    $this->assertDatabaseCount('billing_invoices', 0);
});

test('deleting all simulations removes only simulated billing invoices', function () {
    BillingInvoice::query()->create(simulatedBillingInvoiceAttributes([
        'billing_date' => '2026-01-01 00:00:00',
    ]));

    BillingInvoice::query()->create(simulatedBillingInvoiceAttributes([
        'billing_date' => '2026-02-01 00:00:00',
    ]));

    BillingInvoice::query()->create(realBillingInvoiceAttributes([
        'customer_external_id' => 'cust_003',
    ]));

    $response = deleteJson(route('api.billing-invoices.simulations.destroy'));

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Simulated billing invoices deleted.')
        ->assertJsonPath('meta.deleted_count', 2);

    expect(BillingInvoice::query()->count())->toBe(1);
    expect(BillingInvoice::query()->where('is_simulation', true)->count())->toBe(0);
    expect(BillingInvoice::query()->where('is_simulation', false)->count())->toBe(1);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function realBillingInvoiceAttributes(array $overrides = []): array
{
    return array_merge([
        'billing_date' => '2026-04-10 15:30:00',
        'type' => 'national',
        'cnae' => '6201500',
        'cnae_annex' => 3,
        'cnae_calculation' => null,
        'customer_name' => 'ACME Brazil',
        'customer_external_id' => 'cust_001',
        'amount_brl' => 1500.75,
        'amount_usd' => null,
        'usd_brl_exchange_rate' => null,
        'is_simulation' => false,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function simulatedBillingInvoiceAttributes(array $overrides = []): array
{
    return array_merge([
        'billing_date' => '2026-01-01 00:00:00',
        'type' => 'national',
        'cnae' => null,
        'cnae_annex' => null,
        'cnae_calculation' => null,
        'customer_name' => null,
        'customer_external_id' => null,
        'amount_brl' => 1000.00,
        'amount_usd' => null,
        'usd_brl_exchange_rate' => null,
        'is_simulation' => true,
    ], $overrides);
}
