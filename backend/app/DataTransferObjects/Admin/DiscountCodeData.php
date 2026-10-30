<?php

namespace App\DataTransferObjects\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class DiscountCodeData
{
    public function __construct(
        public string $code,
        public float $discountPercent,
        public int $maxUses,
        public ?CarbonImmutable $startsAt,
        public ?CarbonImmutable $expiresAt,
        public bool $isActive,
    ) {}

    /**
     * @param  array{code: string, discount_percent: float|string, max_uses: int|string, starts_at?: string|null, expires_at?: string|null, is_active?: bool|string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            // Uppercased on save, not just compared case-insensitively at
            // redemption (DiscountCodeService) - so the admin list never
            // shows what look like two different codes ("spooky10" vs
            // "SPOOKY10") that would actually collide at checkout.
            code: Str::upper(trim($data['code'])),
            discountPercent: (float) $data['discount_percent'],
            maxUses: (int) $data['max_uses'],
            startsAt: self::dayBoundary($data['starts_at'] ?? null, startOfDay: true),
            expiresAt: self::dayBoundary($data['expires_at'] ?? null, startOfDay: false),
            isActive: filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /**
     * The admin form's start/end fields are plain dates (<input
     * type="date">, validated as Y-m-d) - interpreted as a full day in the
     * store's own local time (this store is Bulgaria-only, same
     * "Europe/Sofia" assumption other date-sensitive code here makes), not
     * the app's UTC (see config/app.php's 'timezone'). An admin picking
     * "31.10.2026" as an end date means "through the end of that day in
     * Bulgaria", not midnight UTC - two hours earlier, and a real
     * production bug if gotten wrong (see the migration that shipped
     * SPOOKY10, computed by hand for exactly this reason before this class
     * existed).
     */
    private static function dayBoundary(?string $date, bool $startOfDay): ?CarbonImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        $local = CarbonImmutable::createFromFormat('Y-m-d', $date, 'Europe/Sofia');

        return ($startOfDay ? $local->startOfDay() : $local->endOfDay())->setTimezone('UTC');
    }
}
