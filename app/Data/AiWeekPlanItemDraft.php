<?php

namespace App\Data;

readonly class AiWeekPlanItemDraft
{
    public function __construct(
        public string $date,
        public string $slot,
        public string $label,
        public ?float $quantityG,
        public ?int $recipeId,
        public ?string $referenceType,
        public ?int $referenceId,
        public ?int $foodItemId,
        public bool $resolved,
        public ?string $warning = null,
        public string $matchKind = 'none',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'slot' => $this->slot,
            'label' => $this->label,
            'quantity_g' => $this->quantityG,
            'recipe_id' => $this->recipeId,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'food_item_id' => $this->foodItemId,
            'resolved' => $this->resolved,
            'warning' => $this->warning,
            'match_kind' => $this->matchKind,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            date: (string) $data['date'],
            slot: (string) $data['slot'],
            label: (string) $data['label'],
            quantityG: isset($data['quantity_g']) ? (float) $data['quantity_g'] : null,
            recipeId: isset($data['recipe_id']) ? (int) $data['recipe_id'] : null,
            referenceType: $data['reference_type'] ?? null,
            referenceId: isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            foodItemId: isset($data['food_item_id']) ? (int) $data['food_item_id'] : null,
            resolved: (bool) ($data['resolved'] ?? false),
            warning: $data['warning'] ?? null,
            matchKind: (string) ($data['match_kind'] ?? 'none'),
        );
    }
}
