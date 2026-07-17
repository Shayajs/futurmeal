<?php

namespace App\Data;

readonly class AiWeekPlanDraft
{
    /**
     * @param  list<AiWeekPlanItemDraft>  $items
     * @param  list<string>  $errors
     */
    public function __construct(
        public array $items,
        public array $errors = [],
    ) {}

    public function resolvedCount(): int
    {
        return count(array_filter($this->items, fn (AiWeekPlanItemDraft $i) => $i->resolved));
    }

    public function unresolvedCount(): int
    {
        return count(array_filter($this->items, fn (AiWeekPlanItemDraft $i) => ! $i->resolved));
    }

    /** @return array<string, list<AiWeekPlanItemDraft>> */
    public function itemsByDate(): array
    {
        $grouped = [];
        foreach ($this->items as $item) {
            $grouped[$item->date][] = $item;
        }

        return $grouped;
    }

    /** @return array{items: list<array<string, mixed>>, errors: list<string>} */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (AiWeekPlanItemDraft $i) => $i->toArray(), $this->items),
            'errors' => $this->errors,
        ];
    }

    /** @param array{items?: list<array<string, mixed>>, errors?: list<string>} $data */
    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn (array $row) => AiWeekPlanItemDraft::fromArray($row),
            $data['items'] ?? [],
        );

        return new self($items, $data['errors'] ?? []);
    }
}
