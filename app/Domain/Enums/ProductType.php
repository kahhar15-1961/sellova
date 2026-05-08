<?php

namespace App\Domain\Enums;

enum ProductType: string
{
    case Physical = 'physical';
    case Digital = 'digital';
    case InstantDelivery = 'instant_delivery';
    case Service = 'service';

    public static function normalize(?string $value): self
    {
        return match (strtolower(trim((string) $value))) {
            'physical' => self::Physical,
            'digital' => self::Digital,
            'instant_delivery', 'instant-delivery', 'instant' => self::InstantDelivery,
            'service', 'manual_delivery', 'manual-delivery' => self::Service,
            default => self::Digital,
        };
    }

    public function requiresDeliveryChat(): bool
    {
        return $this !== self::Physical;
    }
}

