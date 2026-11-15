<?php

namespace App\Policies;

use App\Models\GradeAudit;
use App\Models\User;
use App\Services\Authorization\RecordAccess;
use Illuminate\Auth\Access\Response;

class GradeAuditPolicy
{
    public function viewAny(User $actor): bool
    {
        return RecordAccess::active($actor);
    }

    public function view(User $actor, GradeAudit $audit): Response
    {
        return GradeAudit::visibleTo($actor)->whereKey($audit->id)->exists()
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, GradeAudit $audit): bool
    {
        return false;
    }

    public function delete(User $actor, GradeAudit $audit): bool
    {
        return false;
    }
}
