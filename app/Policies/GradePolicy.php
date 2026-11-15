<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Authorization\RecordAccess;
use Illuminate\Auth\Access\Response;

class GradePolicy
{
    public function viewAny(User $actor): bool
    {
        return RecordAccess::active($actor);
    }

    public function view(User $actor, Grade $grade): Response
    {
        return Grade::withTrashed()->visibleTo($actor)->whereKey($grade->id)->exists()
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function viewHistory(User $actor, Grade $grade): Response
    {
        return $this->view($actor, $grade);
    }

    // Global UI eligibility is deliberately separate from record creation.
    public function createAny(User $actor): bool
    {
        return RecordAccess::writer($actor);
    }

    public function create(User $actor, ?Assessment $assessment = null, ?StudentEnrollment $enrollment = null, ?string $date = null): bool
    {
        return RecordAccess::writer($actor) && $assessment !== null && $enrollment !== null
            && RecordAccess::assessment($actor, $assessment)
            && RecordAccess::writable($assessment, $enrollment, $date);
    }

    public function update(User $actor, Grade $grade): Response
    {
        $access = $this->view($actor, $grade);
        if ($access->denied()) {
            return $access;
        }
        $grade = $grade->fresh();

        return $grade && ! $grade->trashed() && RecordAccess::writer($actor)
            && RecordAccess::writable($grade->assessment, $grade->enrollment, $grade->graded_on->toDateString())
            ? Response::allow() : Response::deny();
    }

    public function delete(User $actor, Grade $grade): Response
    {
        return $this->update($actor, $grade);
    }
}
