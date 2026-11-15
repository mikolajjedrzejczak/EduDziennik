<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;
use App\Services\Authorization\RecordAccess;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class SubjectPolicy extends AdministrationPolicy
{
    public function viewAny(User $actor): bool
    {
        return RecordAccess::active($actor);
    }

    public function view(User $actor, Model $record): Response
    {
        return $record instanceof Subject && Subject::withTrashed()->visibleTo($actor)->whereKey($record->getKey())->exists()
            ? Response::allow() : Response::denyAsNotFound();
    }
}
