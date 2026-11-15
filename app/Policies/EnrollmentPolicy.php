<?php

namespace App\Policies;

use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Authorization\RecordAccess;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class EnrollmentPolicy extends AdministrationPolicy
{
    public function viewAny(User $actor): bool
    {
        return RecordAccess::active($actor);
    }

    public function view(User $actor, Model $record): Response
    {
        return $record instanceof StudentEnrollment && StudentEnrollment::visibleTo($actor)->whereKey($record->getKey())->exists()
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $actor, Model $record): bool
    {
        return false;
    }
}
