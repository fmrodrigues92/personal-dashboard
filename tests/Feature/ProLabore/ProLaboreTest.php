<?php

use App\Models\ProLaboreReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();

    actingAs($this->user);
    Sanctum::actingAs($this->user);
});

test('authenticated users can create a real pro-labore receipt through the api', function () {
    $response = postJson(route('api.pro-labore.store'), [
        'reference_month' => '2026-04-15',
        'gross_amount_brl' => 2800.00,
        'notes' => 'April receipt',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.reference_month', '2026-04-01')
        ->assertJsonPath('data.gross_amount_brl', 2800)
        ->assertJsonPath('data.notes', 'April receipt')
        ->assertJsonPath('data.is_simulation', false)
        ->assertJsonPath('data.source_revenue_brl', null);

    $this->assertDatabaseHas('pro_labore_receipts', [
        'user_id' => $this->user->id,
        'reference_month' => '2026-04-01',
        'gross_amount_brl' => '2800.00',
        'notes' => 'April receipt',
        'deleted_at' => null,
    ]);
});

test('authenticated users can generate pro-labore simulations from billing simulations', function () {
    postJson(route('api.billing-invoices.simulations.store'), [
        'type' => 'national',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-01',
        'amount_brl' => 1001.00,
    ])->assertCreated();

    $response = postJson(route('api.pro-labore.simulations.store'), [
        'start_month' => '2026-05-01',
        'end_month' => '2026-05-01',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('meta.created_count', 1)
        ->assertJsonPath('data.0.reference_month', '2026-05-01')
        ->assertJsonPath('data.0.source_revenue_brl', 1001)
        ->assertJsonPath('data.0.gross_amount_brl', 290)
        ->assertJsonPath('data.0.is_simulation', true);

    expect(ProLaboreReceipt::query()->count())->toBe(0);
});

test('listing pro-labore entries combines postgres receipts and redis simulations', function () {
    ProLaboreReceipt::query()->create([
        'user_id' => $this->user->id,
        'reference_month' => '2026-04-01',
        'gross_amount_brl' => 2800.00,
        'notes' => null,
    ]);

    postJson(route('api.billing-invoices.simulations.store'), [
        'type' => 'national',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-01',
        'amount_brl' => 10000.00,
    ])->assertCreated();

    postJson(route('api.pro-labore.simulations.store'), [
        'start_month' => '2026-05-01',
        'end_month' => '2026-05-01',
    ])->assertCreated();

    $response = $this->getJson(route('api.pro-labore.index'));

    $response
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.reference_month', '2026-05-01')
        ->assertJsonPath('data.0.gross_amount_brl', 2810)
        ->assertJsonPath('data.0.is_simulation', true)
        ->assertJsonPath('data.1.reference_month', '2026-04-01')
        ->assertJsonPath('data.1.is_simulation', false);
});

test('pro-labore simulations move exact tens to the next higher ten', function () {
    postJson(route('api.billing-invoices.simulations.store'), [
        'type' => 'national',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-01',
        'amount_brl' => 15000.00,
    ])->assertCreated();

    $response = postJson(route('api.pro-labore.simulations.store'), [
        'start_month' => '2026-05-01',
        'end_month' => '2026-05-01',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.0.source_revenue_brl', 15000)
        ->assertJsonPath('data.0.gross_amount_brl', 4210);
});

test('deleting pro-labore simulations does not delete real receipts', function () {
    ProLaboreReceipt::query()->create([
        'user_id' => $this->user->id,
        'reference_month' => '2026-04-01',
        'gross_amount_brl' => 2800.00,
        'notes' => null,
    ]);

    postJson(route('api.billing-invoices.simulations.store'), [
        'type' => 'national',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-01',
        'amount_brl' => 10000.00,
    ])->assertCreated();

    postJson(route('api.pro-labore.simulations.store'), [
        'start_month' => '2026-05-01',
        'end_month' => '2026-05-01',
    ])->assertCreated();

    $response = deleteJson(route('api.pro-labore.simulations.destroy'));

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Pro-labore simulations deleted.')
        ->assertJsonPath('meta.deleted_count', 1);

    expect(ProLaboreReceipt::query()->count())->toBe(1);

    $this->getJson(route('api.pro-labore.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_simulation', false);
});

test('users can not delete pro-labore receipts that belong to another user', function () {
    $receipt = ProLaboreReceipt::query()->create([
        'user_id' => User::factory()->create()->id,
        'reference_month' => '2026-04-01',
        'gross_amount_brl' => 2800.00,
        'notes' => null,
    ]);

    $response = deleteJson(route('api.pro-labore.destroy', $receipt));

    $response->assertNotFound();
    $this->assertDatabaseHas('pro_labore_receipts', [
        'id' => $receipt->id,
        'deleted_at' => null,
    ]);
});
