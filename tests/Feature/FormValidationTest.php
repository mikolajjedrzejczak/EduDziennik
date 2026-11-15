<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FormValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require base_path('tests/Fixtures/Forms/routes.php');
    }

    public function test_invalid_inertia_submission_redirects_with_polish_errors_and_preserves_only_non_secret_input(): void
    {
        $response = $this->from('/__tests/forms')->withHeaders(['X-Inertia' => 'true'])->post('/__tests/forms/allowed', [
            'email' => 'błędny', 'quantity' => '7', 'comment' => '  Zażółć gęślą jaźń  ',
            'password' => 'secret', 'password_confirmation' => 'other', 'current_password' => 'old-secret',
        ]);

        $response->assertRedirect('/__tests/forms')->assertSessionHasErrors(['email', 'quantity', 'password']);
        $response->assertSessionHas('errors', fn ($errors) => $errors->first('email') === 'Pole adres e-mail musi zawierać prawidłowy adres e-mail.');
        $response->assertSessionHasInput('comment', 'Zażółć gęślą jaźń');
        $response->assertSessionHasInput('quantity', '7');
        foreach (['password', 'password_confirmation', 'current_password'] as $secret) {
            $this->assertArrayNotHasKey($secret, session()->getOldInput());
        }
        $response->assertSessionMissing('probe_effect');

        $this->withCookie(config('session.cookie'), session()->getId())->get('/__tests/forms', ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? ''])
            ->assertOk()->assertJsonPath('props.errors.quantity', 'Pole liczba musi mieć wartość od 1 do 5.');
    }

    public function test_validated_allowlist_normalizes_empty_text_but_keeps_numeric_string(): void
    {
        $this->post('/__tests/forms/allowed', [
            'email' => '  test@example.org  ', 'quantity' => '2', 'comment' => '   ',
            'password' => 'długie-testowe-hasło', 'password_confirmation' => 'długie-testowe-hasło',
            'user_id' => '999', 'role' => 'admin', 'can' => true,
        ])->assertRedirect('/__tests/forms')->assertSessionHasNoErrors()
            ->assertSessionHas('probe_effect', fn ($data) => $data === [
                'email' => 'test@example.org', 'quantity' => '2', 'comment' => null,
            ]);
    }

    public function test_denied_context_cannot_execute_even_with_forged_authorization_input(): void
    {
        $this->post('/__tests/forms/denied', ['can' => true, 'context' => 'allowed', 'role' => 'admin'])
            ->assertForbidden()->assertSessionMissing('probe_effect');
    }

    public function test_fixture_routes_are_not_registered_by_the_application(): void
    {
        $this->refreshApplication();
        $this->assertFalse(collect(Route::getRoutes())->contains(fn ($route) => str_starts_with($route->uri(), '__tests/')));
    }
}
