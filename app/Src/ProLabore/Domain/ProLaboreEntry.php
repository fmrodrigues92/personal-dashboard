<?php

namespace App\Src\ProLabore\Domain;

use App\Models\ProLaboreReceipt as ProLaboreReceiptModel;
use Carbon\CarbonImmutable;

readonly class ProLaboreEntry
{
    public function __construct(
        public int $id,
        public CarbonImmutable $referenceMonth,
        public float $grossAmountBrl,
        public ?float $sourceRevenueBrl,
        public ?string $source,
        public ?string $notes,
        public bool $isSimulation,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
        public ?CarbonImmutable $deletedAt,
    ) {}

    public static function fromModel(ProLaboreReceiptModel $receipt): self
    {
        return new self(
            id: $receipt->id,
            referenceMonth: $receipt->reference_month->startOfMonth(),
            grossAmountBrl: $receipt->gross_amount_brl,
            sourceRevenueBrl: null,
            source: null,
            notes: $receipt->notes,
            isSimulation: false,
            createdAt: $receipt->created_at,
            updatedAt: $receipt->updated_at,
            deletedAt: $receipt->deleted_at,
        );
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public static function fromSimulationRecord(array $record): self
    {
        return new self(
            id: (int) $record['id'],
            referenceMonth: CarbonImmutable::parse($record['reference_month'])->startOfMonth(),
            grossAmountBrl: (float) $record['gross_amount_brl'],
            sourceRevenueBrl: (float) $record['source_revenue_brl'],
            source: (string) $record['source'],
            notes: null,
            isSimulation: true,
            createdAt: CarbonImmutable::parse($record['created_at']),
            updatedAt: CarbonImmutable::parse($record['updated_at']),
            deletedAt: null,
        );
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reference_month' => $this->referenceMonth->toDateString(),
            'gross_amount_brl' => $this->grossAmountBrl,
            'source_revenue_brl' => $this->sourceRevenueBrl,
            'source' => $this->source,
            'notes' => $this->notes,
            'is_simulation' => $this->isSimulation,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
            'deleted_at' => $this->deletedAt?->toISOString(),
        ];
    }
}
