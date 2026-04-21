<?php

namespace App\Services\Contracts;

interface ServiceBoundaryNotes
{
    /**
     * Marker interface: implementation details live in docs/HTTP_DOMAIN_PIPELINE.md
     * and in each financial-critical service (transaction + idempotency + invariants).
     *
     * Domain state and ledger types are expressed as backed enums in {@see \App\Domain\Enums}
     * (values match the MySQL ENUM columns). Commands and models should prefer these over raw strings.
     */
}
