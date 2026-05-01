<?php

namespace App\Http\Controllers\ProLabore;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProLabore\GenerateProLaboreSimulationsRequest;
use App\Http\Requests\ProLabore\StoreProLaboreReceiptRequest;
use App\Models\ProLaboreReceipt as ProLaboreReceiptModel;
use App\Src\ProLabore\Domain\ProLaboreEntry;
use App\Src\ProLabore\UseCases\CreateProLaboreReceiptUseCase;
use App\Src\ProLabore\UseCases\DeleteProLaboreReceiptUseCase;
use App\Src\ProLabore\UseCases\DeleteProLaboreSimulationsUseCase;
use App\Src\ProLabore\UseCases\GenerateProLaboreSimulationsUseCase;
use App\Src\ProLabore\UseCases\ListProLaboreEntriesUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProLaboreController extends Controller
{
    public function index(
        Request $request,
        ListProLaboreEntriesUseCase $useCase,
    ): JsonResponse|InertiaResponse {
        $entries = $useCase->handle($this->currentUserId($request));

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $this->serializeCollection($entries),
            ]);
        }

        return Inertia::render('pro-labore/index', [
            'initialEntries' => $this->serializeCollection($entries),
        ]);
    }

    public function store(
        StoreProLaboreReceiptRequest $request,
        CreateProLaboreReceiptUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $entry = $useCase->handle($request->validated(), $this->currentUserId($request));

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $entry->toArray(),
            ], 201);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Pro-labore receipt created.'),
        ]);

        return redirect()->back();
    }

    public function storeSimulations(
        GenerateProLaboreSimulationsRequest $request,
        GenerateProLaboreSimulationsUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $entries = $useCase->handle($request->validated(), $this->currentUserId($request))->values();

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'data' => $entries
                    ->map(fn (ProLaboreEntry $entry): array => $entry->toArray())
                    ->all(),
                'meta' => [
                    'created_count' => $entries->count(),
                ],
            ], 201);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Pro-labore simulations generated.'),
        ]);

        return redirect()->back();
    }

    public function destroy(
        Request $request,
        ProLaboreReceiptModel $proLaboreReceipt,
        DeleteProLaboreReceiptUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $this->ensureOwnsReceipt($request, $proLaboreReceipt);
        $useCase->handle($proLaboreReceipt);

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'message' => 'Pro-labore receipt deleted.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Pro-labore receipt deleted.'),
        ]);

        return redirect()->back();
    }

    public function destroySimulations(
        Request $request,
        DeleteProLaboreSimulationsUseCase $useCase,
    ): JsonResponse|RedirectResponse {
        $deletedCount = $useCase->handle($this->currentUserId($request));

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'message' => 'Pro-labore simulations deleted.',
                'meta' => [
                    'deleted_count' => $deletedCount,
                ],
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Pro-labore simulations deleted.'),
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

    protected function ensureOwnsReceipt(Request $request, ProLaboreReceiptModel $receipt): void
    {
        abort_unless($receipt->user_id === $this->currentUserId($request), 404);
    }

    /**
     * @param  Collection<int, ProLaboreEntry>  $entries
     * @return array<int, array<string, bool|float|int|string|null>>
     */
    protected function serializeCollection(Collection $entries): array
    {
        return $entries
            ->map(fn (ProLaboreEntry $entry): array => $entry->toArray())
            ->all();
    }
}
