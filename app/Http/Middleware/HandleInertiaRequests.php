<?php

namespace App\Http\Middleware;

use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Navigation\NavigationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'flash' => ['success' => fn () => $request->session()->get('success'), 'error' => fn () => $request->session()->get('error')],
            'auth' => [
                'user' => $request->user() ? [
                    'id' => (string) $request->user()->id,
                    'name' => $request->user()->name,
                    'firstName' => $request->user()->first_name,
                    'lastName' => $request->user()->last_name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                ] : null,
                'role' => $request->user()?->role,
            ],
            'can' => [
                'createGrade' => $request->user()?->can('createAny', Grade::class) ?? false,
                'manageUsers' => $request->user()?->can('viewAny', User::class) ?? false,
                'manageSchool' => $request->user()?->can('create', SchoolClass::class) ?? false,
            ],
            'navigation' => fn () => app(NavigationService::class)->for($request->user()),
        ];
    }
}
