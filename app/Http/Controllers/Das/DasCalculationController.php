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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DasCalculationController extends Controller
{
    public function index(
        Request $request,
        ListDasCalculationsUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $dasCalculations = $useCase->handle();

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
        $storedDasCalculation = $useCase->handle($dasCalculation);

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
        $dasCalculation = $useCase->handle($request->validated());

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
        $items = $useCase->handle($request->validated());

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
