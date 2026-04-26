<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreAdminRunbookRequest;
use App\Http\Requests\Admin\StoreAdminRunbookStepRequest;
use App\Models\AdminRunbook;
use App\Models\AdminRunbookStep;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class AdminRunbooksController extends AdminPageController
{
    public function index(): Response
    {
        $runbooks = AdminRunbook::query()->with('steps')->orderBy('queue_code')->orderBy('id')->get();

        return Inertia::render('Admin/Runbooks/Index', [
            'header' => $this->pageHeader(
                'Runbooks / Playbooks',
                'Operational checklists with required evidence for incident response quality and consistency.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'System'],
                    ['label' => 'Runbooks'],
                ],
            ),
            'runbook_store_url' => route('admin.runbooks.store'),
            'step_store_url' => route('admin.runbooks.steps.store'),
            'runbooks' => $runbooks->map(static fn (AdminRunbook $r): array => [
                'id' => $r->id,
                'queue_code' => $r->queue_code,
                'title' => $r->title,
                'objective' => $r->objective,
                'is_active' => $r->is_active,
                'steps' => $r->steps->map(static fn (AdminRunbookStep $s): array => [
                    'id' => $s->id,
                    'step_order' => $s->step_order,
                    'instruction' => $s->instruction,
                    'is_required' => $s->is_required,
                    'evidence_required' => $s->evidence_required,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    public function store(StoreAdminRunbookRequest $request): RedirectResponse
    {
        AdminRunbook::query()->create($request->validated());

        return back()->with('success', 'Runbook created.');
    }

    public function storeStep(StoreAdminRunbookStepRequest $request): RedirectResponse
    {
        AdminRunbookStep::query()->create($request->validated());

        return back()->with('success', 'Runbook step added.');
    }
}
