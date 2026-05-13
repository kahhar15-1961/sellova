<?php

declare(strict_types=1);

namespace Tests\Feature\Error;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ErrorStatusPageTest extends TestCase
{
    public function test_missing_route_uses_premium_404_page(): void
    {
        $response = $this->get('/missing-premium-page');

        $response->assertNotFound();
        $response->assertSee('Error\/Status', false);
        $response->assertSee('&quot;status&quot;:404', false);
        $response->assertSee('&quot;search_href&quot;:&quot;\/marketplace&quot;', false);
    }

    public function test_runtime_exception_uses_premium_500_page_when_debug_is_disabled(): void
    {
        config()->set('app.debug', false);

        Route::middleware('web')->get('/__test/internal-server-error', static function (): void {
            throw new \RuntimeException('Simulated internal failure.');
        });

        $response = $this->get('/__test/internal-server-error');

        $response->assertStatus(500);
        $response->assertSee('Error\/Status', false);
        $response->assertSee('&quot;status&quot;:500', false);
        $response->assertSee('&quot;support_href&quot;:&quot;\/support&quot;', false);
    }
}
