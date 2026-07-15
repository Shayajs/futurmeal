<?php

namespace App\Data;

readonly class ProductReference
{
    public function __construct(
        public ?string $referenceType = null,
        public ?int $referenceId = null,
        public ?int $foodItemId = null,
        public ?string $label = null,
        public ?string $barcode = null,
    ) {}
}
