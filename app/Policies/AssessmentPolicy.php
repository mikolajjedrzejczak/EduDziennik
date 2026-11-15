<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Authorization\RecordAccess;
use Illuminate\Auth\Access\Response;

class AssessmentPolicy
{
    public function view(User $actor, Assessment $assessment): Response
    {
        return RecordAccess::assessment($actor, $assessment) ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $actor, SchoolClass $class, Subject $subject, Term $term): bool
    {
        $class = $class->fresh();
        $subject = $subject->fresh();
        $term = $term->fresh();

        return RecordAccess::writer($actor) && $class && $subject && $term
            && ! $class->trashed() && ! $subject->trashed() && $term->closed_at === null
            && $class->academic_year_id === $term->academic_year_id
            && RecordAccess::assigned($actor, $class->id, $subject->id, $term->id);
    }

    public function delete(User $actor, Assessment $assessment): Response
    {
        $access = $this->view($actor, $assessment);
        if ($access->denied()) {
            return $access;
        }

        return RecordAccess::writer($actor) && RecordAccess::writable($assessment)
            && ! $assessment->grades()->withTrashed()->exists() ? Response::allow() : Response::deny();
    }
}
