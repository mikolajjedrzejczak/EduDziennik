<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Responses\ErrorPage;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Route::get('/up', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureRole::class);
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            EnsureActiveAccount::class,
        );

        $middleware->web(append: [
            EnsureActiveAccount::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if (in_array($response->getStatusCode(), [419, 429], true)) {
                $retryAfter = (int) $response->headers->get('Retry-After', '0');
                $errorResponse = Inertia::render('auth/login', [
                    'errors' => ['form' => $response->getStatusCode() === 419
                        ? 'Sesja wygasła. Zaloguj się ponownie. Formularz nie został wysłany ponownie.'
                        : "Zbyt wiele prób logowania. Spróbuj ponownie za {$retryAfter} s."],
                    'retryAfter' => $retryAfter ?: null,
                    'auth' => ['user' => null, 'role' => null],
                ])->toResponse($request)->setStatusCode($response->getStatusCode());
                if ($retryAfter > 0) {
                    $errorResponse->headers->set('Retry-After', (string) $retryAfter);
                }

                return $errorResponse;
            }

            if (ErrorPage::handles($response, $request)) {
                return ErrorPage::render($response, $request);
            }

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
