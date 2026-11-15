<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the shared/error Inertia page (F-404). Props are an allowlist:
 * status, neutral message, optional correlation id and safe GET retry URL.
 * Exception messages and traces are never exposed.
 */
class ErrorPage
{
    public const STATUSES = [403, 404, 500, 503];

    public static function handles(Response $response, Request $request): bool
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return false;
        }
        if (! in_array($response->getStatusCode(), self::STATUSES, true)) {
            return false;
        }

        // Debug mode keeps the framework's diagnostic page for unexpected failures.
        return ! ($response->getStatusCode() === 500 && config('app.debug'));
    }

    public static function render(Response $response, Request $request): Response
    {
        $status = $response->getStatusCode();
        $props = [
            'status' => $status,
            'message' => __('errors.'.$status),
            'auth' => [
                'user' => $request->user() ? ['role' => $request->user()->role] : null,
                'role' => $request->user()?->role,
            ],
        ];

        if ($status === 500) {
            $props['correlationId'] = (string) Str::uuid();
            Log::error('Unexpected error page rendered.', [
                'correlation_id' => $props['correlationId'],
                'method' => $request->method(),
                'path' => $request->path(),
            ]);
        }

        if ($status === 503 && $request->isMethod('GET')) {
            $props['retryUrl'] = $request->getRequestUri();
        }

        $page = Inertia::render('shared/error', $props)->toResponse($request)->setStatusCode($status);
        if ($response->headers->has('Retry-After')) {
            $page->headers->set('Retry-After', (string) $response->headers->get('Retry-After'));
        }

        return $page;
    }
}
