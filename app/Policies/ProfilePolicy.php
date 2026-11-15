<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\RecordAccess;

class ProfilePolicy
{
    public function view(User $actor, User $profile): bool
    {
        return RecordAccess::active($actor) && $actor->is($profile);
    }

    public function update(User $actor, User $profile): bool
    {
        return $this->view($actor, $profile);
    }
}
