<?php

namespace App\Services\Users;

use App\Models\User;
use App\Services\School\AssignmentService;
use App\Services\School\EnrollmentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** F-303: account creation. Account and optional teaching context are one commit; any failure rolls back everything. */
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
