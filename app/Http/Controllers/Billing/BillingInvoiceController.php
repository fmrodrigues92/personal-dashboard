<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreBillingInvoiceRequest;
use App\Http\Requests\Billing\StoreBillingInvoiceSimulationRequest;
use App\Models\BillingInvoice as BillingInvoiceModel;
use App\Src\Billing\Domain\BillingInvoice;
use App\Src\Billing\UseCases\CreateBillingInvoiceSimulationsUseCase;
use App\Src\Billing\UseCases\CreateBillingInvoiceUseCase;
use App\Src\Billing\UseCases\DeleteBillingInvoiceSimulationsUseCase;
use App\Src\Billing\UseCases\DeleteBillingInvoiceUseCase;
use App\Src\Billing\UseCases\ListBillingInvoiceSimulationsUseCase;
use App\Src\Billing\UseCases\ListBillingInvoicesUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class BillingInvoiceController extends Controller
{
    public function index(
        Request $request,
        ListBillingInvoicesUseCase $useCase,
    ): JsonResponse|InertiaResponse {
        $billingInvoices = $useCase->handle();

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $this->serializeCollection($billingInvoices),
            ]);
        }

        return Inertia::render('billing/index', [
            'initialInvoices' => $this->serializeCollection($billingInvoices),
        ]);
    }

    public function indexSimulations(
        Request $request,
        ListBillingInvoiceSimulationsUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $billingInvoices = $useCase->handle();

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $this->serializeCollection($billingInvoices),
            ]);
        }

        return redirect()->route('billing-invoices.index');
    }

    public function store(
        StoreBillingInvoiceRequest $request,
        CreateBillingInvoiceUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $billingInvoice = $useCase->handle($request->validated());

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $billingInvoice->toArray(),
            ], 201);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Billing invoice created.'),
        ]);

        return redirect()->back();
    }

    public function storeSimulations(
        StoreBillingInvoiceSimulationRequest $request,
        CreateBillingInvoiceSimulationsUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $billingInvoices = $useCase->handle($request->validated())->values();

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $billingInvoices
                    ->map(fn (BillingInvoice $billingInvoice): array => $billingInvoice->toArray())
                    ->all(),
                'meta' => [
                    'created_count' => $billingInvoices->count(),
                ],
            ], 201);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Billing invoice simulations created.'),
        ]);

        return redirect()->back();
    }

    public function destroy(
        Request $request,
        BillingInvoiceModel $billingInvoice,
        DeleteBillingInvoiceUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $useCase->handle($billingInvoice);

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'message' => 'Billing invoice deleted.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Billing invoice deleted.'),
        ]);

        return redirect()->back();
    }

    public function destroySimulations(
        Request $request,
        DeleteBillingInvoiceSimulationsUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $deletedCount = $useCase->handle();

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'message' => 'Simulated billing invoices deleted.',
                'meta' => [
                    'deleted_count' => $deletedCount,
                ],
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Billing invoice simulations deleted.'),
        ]);

        return redirect()->back();
    }

    protected function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson();
    }

    /**
     * @param  Collection<int, BillingInvoice>  $billingInvoices
     * @return array<int, array<string, bool|float|int|string|null>>
     */
    protected function serializeCollection(Collection $billingInvoices): array
    {
        return $billingInvoices
            ->map(fn (BillingInvoice $billingInvoice): array => $billingInvoice->toArray())
            ->all();
    }
}
