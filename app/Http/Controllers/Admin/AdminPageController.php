<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

abstract class AdminPageController extends Controller
{
    /**
     * @param  list<array{label: string, href?: string}>  $breadcrumbs
     * @return array{title: string, description: string, breadcrumbs: list<array{label: string, href?: string}>}
     */
    protected function pageHeader(string $title, string $description, array $breadcrumbs = []): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'breadcrumbs' => $breadcrumbs,
        ];
    }
}
