<?php

namespace App\Domain\Commands\UserSeller;

use App\Domain\Value\KycDocumentItem;

/**
 * Input contract for {@see \App\Services\UserSeller\UserSellerService::submitKyc}.
 *
 * @phpstan-type DocumentList list<KycDocumentItem>
 */
final readonly class SubmitKycCommand
{
    /**
     * @param  list<KycDocumentItem>  $documents
     */
    public function __construct(
        public int $sellerProfileId,
        public array $documents,
    ) {
    }

    public static function fromItems(int $sellerProfileId, KycDocumentItem ...$documents): self
    {
        return new self($sellerProfileId, array_values($documents));
    }
}
