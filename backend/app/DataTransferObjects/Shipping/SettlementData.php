<?php

namespace App\DataTransferObjects\Shipping;

/**
 * A Bulgarian settlement (town, city, or village) for the home-delivery
 * "Населено място" picker — see BulgarianSettlementService for where this
 * comes from. `type` is the Cyrillic abbreviation as returned by the
 * source data (e.g. "гр.", "с."), used to disambiguate same-named
 * settlements in different municipalities (a common occurrence — village
 * names repeat across regions far more than town names do).
 */
final readonly class SettlementData
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $municipality,
        public string $region,
        public string $postalCode,
    ) {}
}
