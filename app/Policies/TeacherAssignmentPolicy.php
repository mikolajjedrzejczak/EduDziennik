<?php

namespace App\Policies;

use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Authorization\RecordAccess;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class TeacherAssignmentPolicy extends AdministrationPolicy
{
    public function viewAny(User $actor): bool
    {
        return RecordAccess::active($actor);
    }

    public function view(User $actor, Model $record): Response
    {
        return $record instanceof TeacherAssignment && TeacherAssignment::visibleTo($actor)->whereKey($record->getKey())->exists()
            ? Response::allow() : Response::denyAsNotFound();
    }
}
