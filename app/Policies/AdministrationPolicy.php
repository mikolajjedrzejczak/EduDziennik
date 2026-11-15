<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\RecordAccess;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

abstract class AdministrationPolicy
{
    public function viewAny(User $actor): bool
    {
        return RecordAccess::admin($actor);
    }

    public function view(User $actor, Model $record): bool|Response
    {
        return RecordAccess::admin($actor);
    }

    public function create(User $actor): bool
    {
        return RecordAccess::admin($actor);
    }

    public function update(User $actor, Model $record): bool
    {
        return RecordAccess::admin($actor);
    }

    public function delete(User $actor, Model $record): bool
    {
        return RecordAccess::admin($actor);
    }
}
