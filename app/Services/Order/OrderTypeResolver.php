<?php

namespace App\Services\Order;

use App\Domain\Enums\ProductType;
use App\Models\Order;

class OrderTypeResolver
{
    /**
     * @return array{order_type: string, flow_type: string, product_types: list<string>, has_digital: bool, has_physical: bool, is_mixed: bool}
     */
    public function resolve(Order $order): array
    {
        $order->loadMissing('orderItems');

        $types = $order->orderItems
            ->map(static fn ($item): string => ProductType::normalize((string) ($item->product_type_snapshot ?? ''))->value)
            ->filter()
            ->unique()
            ->values();

        if ($types->isEmpty()) {
            $types = collect([ProductType::normalize((string) ($order->product_type ?? ''))->value]);
        }

        $hasPhysical = $types->contains(ProductType::Physical->value);
        $hasDigital = $types->contains(static fn (string $type): bool => ProductType::normalize($type)->requiresDeliveryChat());
        $isMixed = $hasPhysical && $hasDigital;

        $orderType = match (true) {
            $isMixed => 'mixed',
            $hasPhysical => 'physical',
            $types->contains(ProductType::Service->value) => 'service',
            default => 'digital',
        };

        return [
            'order_type' => $orderType,
            'flow_type' => match ($orderType) {
                'physical' => 'physical_delivery',
                'mixed' => 'mixed_order',
                default => 'digital_escrow',
            },
            'product_types' => $types->all(),
            'has_digital' => $hasDigital,
            'has_physical' => $hasPhysical,
            'is_mixed' => $isMixed,
        ];
    }
}
