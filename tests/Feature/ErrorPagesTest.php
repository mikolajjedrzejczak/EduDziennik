<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->group(function (): void {
            Route::get('/_test/forbidden', fn () => abort(403));
            Route::get('/_test/unavailable', fn () => abort(503));
            Route::post('/_test/unavailable', fn () => abort(503));
            Route::get('/_test/boom', fn () => throw new \RuntimeException('secret-database-detail'));
        });
    }

    private function inertia(string $method, string $uri): TestResponse
    {
        return $this->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])->{$method}($uri);
    }

    public function test_forbidden_and_missing_pages_render_shared_error_without_protected_data(): void
    {
        $this->get('/_test/forbidden')->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('shared/error')->where('status', 403)->has('message')->missing('correlationId'));
        $this->get('/_test/no-such-page')->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('shared/error')->where('status', 404));
    }

    public function test_hidden_and_nonexistent_records_are_indistinguishable(): void
    {
        Route::middleware('web')->get('/_test/records/{id}', fn (int $id) => $id === 1
            ? abort(404)
            : throw (new ModelNotFoundException)->setModel('Grade', [$id]));
        $hidden = $this->get('/_test/records/1')->assertNotFound();
        $missing = $this->get('/_test/records/2')->assertNotFound();
        $this->assertSame($hidden->viewData('page')['props']['message'], $missing->viewData('page')['props']['message']);
        $missing->assertDontSee('ModelNotFound')->assertDontSee('No query results');
    }

    public function test_unexpected_exception_renders_neutral_500_with_correlation_id_and_no_trace(): void
    {
        config(['app.debug' => false]);
        Log::spy();
        $response = $this->get('/_test/boom')->assertStatus(500)
            ->assertInertia(fn (Assert $page) => $page->component('shared/error')->where('status', 500)->has('correlationId'));
        $response->assertDontSee('secret-database-detail')->assertDontSee('RuntimeException')->assertDontSee('vendor/');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9-]{8,}$/', $response->viewData('page')['props']['correlationId']);
    }

    public function test_503_get_offers_a_retry_url_but_a_mutation_does_not(): void
    {
        $this->get('/_test/unavailable?term=1')->assertStatus(503)
            ->assertInertia(fn (Assert $page) => $page->where('status', 503)->where('retryUrl', '/_test/unavailable?term=1'));
        $this->post('/_test/unavailable')->assertStatus(503)
            ->assertInertia(fn (Assert $page) => $page->where('status', 503)->missing('retryUrl'));
    }

    public function test_json_requests_keep_json_errors(): void
    {
        $this->getJson('/_test/forbidden')->assertForbidden()->assertJsonMissingPath('props');
    }

    public function test_419_and_429_keep_the_login_contract(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => abort(419));
        $this->post('/_test/expired')->assertStatus(419)
            ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
    }

    public function test_error_pages_do_not_leak_other_accounts_through_shared_props(): void
    {
        $response = $this->get('/_test/forbidden');
        $props = $response->viewData('page')['props'];
        $this->assertNull($props['auth']['user']);
        $this->assertArrayNotHasKey('password', $props);
    }
}
