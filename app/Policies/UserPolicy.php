<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\RecordAccess;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends AdministrationPolicy
{
    public function delete(User $actor, Model $record): bool
    {
        // Last-admin and historical-role invariants belong to the locked UserService.
        return RecordAccess::admin($actor) && $record instanceof User && ! $actor->is($record);
    }
}
