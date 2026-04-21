<?php

namespace App\Domain\Value;

/**
 * One KYC document upload line for {@see \App\Services\UserSeller\UserSellerService::submitKyc}.
 */
final readonly class KycDocumentItem
{
    public function __construct(
        public string $docType,
        public string $storagePath,
        public string $checksumSha256,
    ) {
    }
}
