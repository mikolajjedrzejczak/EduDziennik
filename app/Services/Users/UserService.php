<?php

namespace App\Services\Users;

use App\Models\User;
use App\Services\School\AssignmentService;
use App\Services\School\EnrollmentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** F-303/F-304: transactional account creation and editing with access/history protection. */
class UserService
{
    public function __construct(private readonly EnrollmentService $enrollments, private readonly AssignmentService $assignments) {}

    /**
     * @param  array{first_name: string, last_name: string, email: string, password: string, role: string, status: string}  $account
     * @param  array{school_class_id: int, starts_on: string, ends_on?: string|null}|null  $enrollment  students only
     * @param  list<array{school_class_id: int, subject_id: int, term_id: int}>  $assignments  teachers only
     */
    public function create(int $actorId, array $account, ?array $enrollment = null, array $assignments = []): User
    {
        return DB::transaction(function () use ($actorId, $account, $enrollment, $assignments) {
            try {
                $user = User::create($account);
            } catch (UniqueConstraintViolationException) {
                // Lost a race against a concurrent create: same message as the request-level unique rule.
                throw ValidationException::withMessages(['email' => 'Ten adres e-mail jest już zajęty.']);
            }

            if ($user->role === 'student' && $enrollment !== null) {
                $this->remap('enrollment.', fn () => $this->enrollments->enroll(
                    $user->id, (int) $enrollment['school_class_id'], $enrollment['starts_on'], $enrollment['ends_on'] ?? null, null, $actorId,
                ), ['student_id' => 'status']);
            }

            if ($user->role === 'teacher') {
                foreach ($assignments as $index => $row) {
                    $this->remap("assignments.{$index}.", fn () => $this->assignments->assign(
                        $user->id, (int) $row['school_class_id'], (int) $row['subject_id'], (int) $row['term_id'],
                    ), ['teacher_id' => '*status']);
                }
            }

            return $user;
        }, 3);
    }

    /**
     * Account edits share a permanent lock, including promotions and self-edits.
     * User locks also serialize relation creation and grade writes with role changes.
     *
     * @param  array{first_name: string, last_name: string, email: string, role: string, status: string, password?: string|null}  $account
     */
    public function update(int $actorId, int $userId, array $account): User
    {
        return DB::transaction(function () use ($actorId, $userId, $account) {
            DB::table('domain_locks')->where('key', 'admin_membership')->lockForUpdate()->firstOrFail();
            $users = User::withTrashed()->whereIn('id', [$actorId, $userId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($actorId);
            $user = $users->get($userId);
            abort_if($actor === null, 403);
            abort_if($user === null || $user->trashed(), 404);
            Gate::forUser($actor)->authorize('update', $user);

            $roleChanged = $user->role !== $account['role'];
            $statusChanged = $user->status !== $account['status'];
            if ($roleChanged && ($user->enrollments()->exists() || $user->assignments()->exists())) {
                throw ValidationException::withMessages(['role' => 'Nie można zmienić roli konta z historycznymi relacjami. Dezaktywuj konto i utwórz inne.']);
            }
            if ($user->role === 'admin' && $user->status === 'active'
                && ($account['role'] !== 'admin' || $account['status'] !== 'active')
                && ! User::where('role', 'admin')->where('status', 'active')->where('id', '!=', $userId)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['status' => 'Musi pozostać co najmniej jeden aktywny administrator.']);
            }

            $resetPassword = isset($account['password']) && $account['password'] !== '';
            // Explicit allowlist: neither relation IDs nor framework security attributes can be assigned.
            $user->fill(collect($account)->only(['first_name', 'last_name', 'email', 'role', 'status'])->all());
            if ($resetPassword) {
                $user->password = $account['password'];
            }
            if ($roleChanged || $statusChanged || $resetPassword) {
                $user->remember_token = Str::random(60);
            }
            try {
                $user->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['email' => 'Ten adres e-mail jest już zajęty.']);
            }
            if ($roleChanged || $statusChanged || $resetPassword) {
                DB::table('sessions')->where('user_id', $userId)->delete();
            }

            return $user;
        }, 3);
    }

    /**
     * Maps field errors of the domain services onto the create form's nested field names.
     * A "*" target is an absolute key, anything else is prefixed.
     *
     * @param  array<string, string>  $overrides
     */
    private function remap(string $prefix, callable $operation, array $overrides = []): void
    {
        try {
            $operation();
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $key = isset($overrides[$field]) ? ltrim($overrides[$field], '*') : $prefix.$field;
                $errors[$key] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }
}
