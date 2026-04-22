<?php

namespace App\Http\Controllers\Das;

use App\Http\Controllers\Controller;
use App\Http\Requests\Das\DasTimelineRequest;
use App\Http\Requests\Das\StoreDasCalculationRequest;
use App\Http\Requests\Das\UpdateDasTaxBreakdownRequest;
use App\Models\DasCalculationTaxBreakdown as DasCalculationTaxBreakdownModel;
use App\Src\Das\Domain\DasCalculation;
use App\Src\Das\UseCases\CalculateDasUseCase;
use App\Src\Das\UseCases\GetDasTimelineUseCase;
use App\Src\Das\UseCases\ListDasCalculationsUseCase;
use App\Src\Das\UseCases\ShowDasCalculationUseCase;
use App\Src\Das\UseCases\UpdateDasTaxBreakdownUseCase;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DasCalculationController extends Controller
{
    public function dashboard(
        DasTimelineRequest $request,
        GetDasTimelineUseCase $useCase,
    ): JsonResponse|InertiaResponse {
        $filters = $request->validated();
        $items = $useCase->handle($filters, $this->currentUserId($request));

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $items,
            ]);
        }

        $hasReferenceMonth = array_key_exists('reference_month', $filters);
        $referenceMonth = isset($filters['reference_month'])
            ? CarbonImmutable::parse($filters['reference_month'])->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        return Inertia::render('dashboard', [
            'initialTimeline' => $items,
            'initialTimelineFilters' => [
                'reference_month' => $referenceMonth->toDateString(),
                'months_before' => (int) ($filters['months_before'] ?? ($hasReferenceMonth ? 0 : 12)),
                'months_after' => (int) ($filters['months_after'] ?? ($hasReferenceMonth ? 0 : 12)),
                'rule_version' => $filters['rule_version'] ?? null,
            ],
        ]);
    }

    public function index(
        Request $request,
        ListDasCalculationsUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $dasCalculations = $useCase->handle($this->currentUserId($request));

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $this->serializeCollection($dasCalculations),
            ]);
        }

        return redirect()->route('dashboard');
    }

    public function show(
        Request $request,
        int $dasCalculation,
        ShowDasCalculationUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $storedDasCalculation = $useCase->handle($dasCalculation, $this->currentUserId($request));

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $storedDasCalculation->toArray(),
            ]);
        }

        return redirect()->route('dashboard');
    }

    public function store(
        StoreDasCalculationRequest $request,
        CalculateDasUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $dasCalculation = $useCase->handle($request->validated(), $this->currentUserId($request));

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $dasCalculation->toArray(),
            ], 201);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('DAS calculation created.'),
        ]);

        return redirect()->back();
    }

    public function timeline(
        DasTimelineRequest $request,
        GetDasTimelineUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $items = $useCase->handle($request->validated(), $this->currentUserId($request));

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $items,
            ]);
        }

        return redirect()->route('dashboard');
    }

    public function updateTaxBreakdown(
        UpdateDasTaxBreakdownRequest $request,
        DasCalculationTaxBreakdownModel $taxBreakdown,
        UpdateDasTaxBreakdownUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $this->ensureOwnsTaxBreakdown($request, $taxBreakdown);
        $dasCalculation = $useCase->handle(
            $taxBreakdown,
            (float) $request->validated('adjusted_amount_brl'),
        );

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $dasCalculation->toArray(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('DAS tax breakdown updated.'),
        ]);

        return redirect()->back();
    }

    protected function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson();
    }

    protected function currentUserId(Request $request): int
    {
        return (int) $request->user()->id;
    }

    protected function ensureOwnsTaxBreakdown(Request $request, DasCalculationTaxBreakdownModel $taxBreakdown): void
    {
        abort_unless($taxBreakdown->dasCalculation->user_id === $this->currentUserId($request), 404);
    }

    /**
     * @param  Collection<int, DasCalculation>  $dasCalculations
     * @return array<int, array<string, array<int, array<string, float|int|string|null>>|array<string, mixed>|bool|float|int|string|null>>
     */
    protected function serializeCollection(Collection $dasCalculations): array
    {
        return $dasCalculations
            ->map(fn (DasCalculation $dasCalculation): array => $dasCalculation->toArray())
            ->all();
    }
}
