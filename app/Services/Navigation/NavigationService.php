<?php

namespace App\Services\Navigation;

use App\Models\User;
use App\Services\Authorization\RecordAccess;

/**
 * Server-built navigation (F-007). Only routes that exist and are ready are
 * listed; features add their entries when they ship. The client renders the
 * result and never decides what a role may reach.
 */
class NavigationService
{
    public const ROLE_DASHBOARDS = [
        'student' => 'student.dashboard',
        'teacher' => 'teacher.dashboard',
        'admin' => 'admin.dashboard',
    ];

    public static function dashboardRoute(User $user): ?string
    {
        return RecordAccess::active($user) ? (self::ROLE_DASHBOARDS[$user->role] ?? null) : null;
    }

    /**
     * @return array{primary: list<array<string, string>>, secondary: list<array<string, string>>}|null
     */
    public function for(?User $user): ?array
    {
        $dashboard = $user ? self::dashboardRoute($user) : null;
        if ($dashboard === null) {
            return null;
        }

        $primary = [
            ['key' => 'dashboard', 'label' => 'Pulpit', 'shortLabel' => 'Start', 'href' => route($dashboard, absolute: false), 'icon' => 'dashboard'],
        ];
        if ($user->role === 'admin') {
            $primary[] = ['key' => 'classes', 'label' => 'Klasy i przedmioty', 'shortLabel' => 'Klasy', 'href' => route('admin.classes.index', absolute: false), 'icon' => 'classes'];
            $primary[] = ['key' => 'subjects', 'label' => 'Przedmioty', 'shortLabel' => 'Przedmioty', 'href' => route('admin.subjects.index', absolute: false), 'icon' => 'subjects'];
            $primary[] = ['key' => 'assignments', 'label' => 'Przydziały', 'shortLabel' => 'Przydziały', 'href' => route('admin.assignments.index', absolute: false), 'icon' => 'assignments'];
        }

        return [
            'primary' => $primary,
            'secondary' => [
                ['key' => 'profile', 'label' => 'Profil i hasło', 'shortLabel' => 'Profil', 'href' => route('profile.edit', absolute: false), 'icon' => 'profile'],
            ],
        ];
    }
}
