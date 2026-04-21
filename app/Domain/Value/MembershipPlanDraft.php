<?php

namespace App\Domain\Value;

/**
 * Admin/system payload to define a membership plan.
 *
 * @phpstan-type Benefits array<string, mixed>
 * @phpstan-type CommissionModifier array<string, mixed>
 */
final readonly class MembershipPlanDraft
{
    /**
     * @param  array<string, mixed>  $benefitsJson
     * @param  array<string, mixed>  $commissionModifierJson
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $billingPeriod,
        public string $price,
        public string $currency,
        public array $benefitsJson,
        public array $commissionModifierJson,
        public bool $isActive,
    ) {
    }
}
