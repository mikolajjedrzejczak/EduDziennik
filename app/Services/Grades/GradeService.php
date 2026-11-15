<?php

namespace App\Services\Grades;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GradeService
{
    /** @param array<string, mixed> $input */
    public function create(int $actorId, int $enrollmentId, int $assessmentId, array $input): Grade
    {
        return DB::transaction(function () use ($actorId, $enrollmentId, $assessmentId, $input) {
            [$actor, $enrollment, $assessment, $term] = $this->context($actorId, $enrollmentId, $assessmentId);
            $data = $this->validate([
                'category_id' => $assessment->category_id, 'weight' => $assessment->default_weight,
                'topic' => $assessment->topic, 'graded_on' => $assessment->graded_on->toDateString(), 'comment' => null,
                ...$input,
            ]);
            $this->checkDates($enrollment, $term, $data);
            Gate::forUser($actor)->authorize('create', [Grade::class, $assessment, $enrollment, $data['graded_on']]);
            $grade = Grade::create([...$data, 'student_enrollment_id' => $enrollment->id, 'assessment_id' => $assessment->id, 'issued_by' => $actor->id, 'version' => 1]);
            $this->audit($grade, $actor, 'create', null, null);

            return $grade;
        }, 3);
    }

    /** @param array<string, mixed> $input */
    public function update(int $actorId, int $gradeId, int $version, string $reason, array $input): Grade
    {
        return $this->mutate($actorId, $gradeId, $version, $reason, $input, false);
    }

    public function delete(int $actorId, int $gradeId, int $version, string $reason): Grade
    {
        return $this->mutate($actorId, $gradeId, $version, $reason, [], true);
    }

    /** @param array<string, mixed> $input */
    private function mutate(int $actorId, int $gradeId, int $version, string $reason, array $input, bool $delete): Grade
    {
        $actor = User::findOrFail($actorId);
        Gate::forUser($actor)->authorize('view', Grade::findOrFail($gradeId));
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'min:3', 'max:500']])->validate();

        return DB::transaction(function () use ($actorId, $gradeId, $version, $reason, $input, $delete) {
            $reference = Grade::findOrFail($gradeId);
            [$actor, $enrollment, $assessment, $term] = $this->context($actorId, $reference->student_enrollment_id, $reference->assessment_id);
            $grade = Grade::lockForUpdate()->findOrFail($gradeId);
            Gate::forUser($actor)->authorize($delete ? 'delete' : 'update', $grade);
            if ($grade->version !== $version) {
                throw ValidationException::withMessages(['conflict' => 'Ocena została zmieniona. Odśwież dane.']);
            }
            $old = $this->snapshot($grade);
            if (! $delete) {
                $data = $this->validate([...$grade->only(['category_id', 'value', 'weight', 'topic', 'comment']), 'graded_on' => $grade->graded_on->toDateString(), ...$input]);
                $this->checkDates($enrollment, $term, $data);
                $grade->fill($data);
                if (! $grade->isDirty()) {
                    return $grade;
                }
            } else {
                $this->checkDates($enrollment, $term, ['graded_on' => $grade->graded_on->toDateString(), 'category_id' => $grade->category_id]);
                $grade->deleted_at = now();
            }
            $grade->version++;
            $grade->save();
            $this->audit($grade, $actor, $delete ? 'delete' : 'update', $old, trim($reason));

            return $grade;
        }, 3);
    }

    /** @return array{User, StudentEnrollment, Assessment, Term} */
    private function context(int $actorId, int $enrollmentId, int $assessmentId): array
    {
        $reference = Assessment::findOrFail($assessmentId);
        $membership = StudentEnrollment::findOrFail($enrollmentId);
        $term = Term::lockForUpdate()->findOrFail($reference->term_id);
        $users = User::whereIn('id', [$actorId, $membership->student_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $actor = $users->get($actorId);
        $student = $users->get($membership->student_id);
        if (! $actor || ! $student || $student->status !== 'active' || $student->role !== 'student') {
            throw ValidationException::withMessages(['student' => 'Konto ucznia lub autora jest niedostępne.']);
        }
        (new WriteContext)->authorize($actor, $term, $reference->school_class_id, $reference->subject_id);
        $enrollment = StudentEnrollment::lockForUpdate()->findOrFail($enrollmentId);
        $assessment = Assessment::lockForUpdate()->findOrFail($assessmentId);
        if ($enrollment->school_class_id !== $assessment->school_class_id) {
            throw ValidationException::withMessages(['enrollment' => 'Członkostwo nie należy do klasy kolumny.']);
        }

        return [$actor, $enrollment, $assessment, $term];
    }

    /** @param array<string, mixed> $data */
    private function checkDates(StudentEnrollment $enrollment, Term $term, array $data): void
    {
        (new WriteContext)->dateAndCategory($term, $data['graded_on'], (int) $data['category_id']);
        $end = $enrollment->ends_on ?? $enrollment->schoolClass->academicYear->ends_on;
        if ($data['graded_on'] < $enrollment->starts_on->toDateString() || $data['graded_on'] > $end->toDateString()) {
            throw ValidationException::withMessages(['graded_on' => 'Data oceny musi mieścić się w członkostwie.']);
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validate(array $data): array
    {
        return Validator::make($data, [
            'category_id' => ['required', 'integer:strict', 'exists:grade_categories,id'],
            'value' => ['required', 'integer:strict', 'between:1,6'],
            'weight' => ['required', 'integer:strict', 'between:1,5'],
            'topic' => ['required', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:500'],
            'graded_on' => ['required', 'date_format:Y-m-d'],
        ])->validate();
    }

    /** @return array<string, mixed> */
    public function snapshot(Grade $grade): array
    {
        $assessment = $grade->assessment;
        $enrollment = $grade->enrollment;
        $snapshot = ['schema_version' => 1];
        foreach (['id', 'student_enrollment_id', 'assessment_id', 'category_id', 'issued_by'] as $key) {
            $snapshot[$key] = (string) $grade->getAttribute($key);
        }
        $snapshot['student_id'] = (string) $enrollment->student_id;
        foreach (['school_class_id', 'subject_id', 'term_id'] as $key) {
            $snapshot[$key] = (string) $assessment->getAttribute($key);
        }

        return [...$snapshot, ...$grade->only(['value', 'weight', 'topic', 'comment', 'version']),
            'graded_on' => $grade->graded_on->toDateString(), 'deleted_at' => $grade->deleted_at?->toISOString()];
    }

    /** @param array<string, mixed>|null $old */
    private function audit(Grade $grade, User $actor, string $action, ?array $old, ?string $reason): void
    {
        GradeAudit::create(['grade_id' => $grade->id, 'actor_id' => $actor->id, 'action' => $action,
            'occurred_at' => now(), 'old_values' => $old, 'new_values' => $this->snapshot($grade),
            'reason' => $reason, 'grade_version' => $grade->version]);
    }
}
