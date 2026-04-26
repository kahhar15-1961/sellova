<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\Admin\AdminListsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SellerProfilesController extends AdminPageController
{
    public function __construct(
        private readonly AdminListsService $lists,
    ) {}

    public function __invoke(Request $request): Response
    {
        $data = $this->lists->sellerProfilesIndex($request);

        return Inertia::render('Admin/SellerProfiles/Index', [
            'header' => $this->pageHeader(
                'Seller Profiles',
                'Seller operations hub for KYC posture, storefront readiness, and payout pressure.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Marketplace'],
                    ['label' => 'Seller Profiles'],
                ],
            ),
            ...$data,
            'index_url' => route('admin.seller-profiles.index'),
            'verification_options' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'under_review', 'label' => 'Under review'],
                ['value' => 'verified', 'label' => 'Verified'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
        ]);
    }
}
