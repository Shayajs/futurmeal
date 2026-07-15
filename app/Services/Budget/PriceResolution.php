<?php

namespace App\Services\Budget;

use App\Enums\PriceSource;

readonly class PriceResolution
{
    public function __construct(
        public float $pricePerKg,
        public PriceSource $source,
        public ?string $locationLabel = null,
        public ?string $date = null,
        public ?string $barcode = null,
        public ?int $contributionCount = null,
    ) {}

    public function sourceLabel(): string
    {
        if ($this->source === PriceSource::User) {
            if ($this->locationLabel) {
                return 'Ton prix · '.$this->locationLabel;
            }

            return $this->source->label();
        }

        if ($this->source === PriceSource::Community) {
            $suffix = $this->locationLabel ?? 'enseigne';
            $count = $this->contributionCount ? " ({$this->contributionCount} relevé".($this->contributionCount > 1 ? 's' : '').')' : '';

            return 'Communauté · '.$suffix.$count;
        }

        if ($this->locationLabel) {
            return 'Open Prices · '.$this->locationLabel;
        }

        return 'Open Prices · moyenne';
    }
}
