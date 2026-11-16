<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    /** @param array{first_name: string, last_name: string} $data */
    public function update(int $actorId, array $data): void
    {
        DB::transaction(function () use ($actorId, $data): void {
            $user = User::lockForUpdate()->findOrFail($actorId);
            Gate::forUser($user)->authorize('updateProfile', $user);
            $user->update(['first_name' => $data['first_name'], 'last_name' => $data['last_name']]);
        });
    }

    public function password(int $actorId, string $currentPassword, string $password, string $sessionId): void
    {
        DB::transaction(function () use ($actorId, $currentPassword, $password, $sessionId): void {
            $user = User::lockForUpdate()->findOrFail($actorId);
            Gate::forUser($user)->authorize('updateProfile', $user);
            // Recheck under the account lock: another password change may have committed after validation.
            if (! Hash::check($currentPassword, $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Obecne hasło jest nieprawidłowe.']);
            }
            $user->password = $password;
            $user->remember_token = Str::random(60);
            $user->save();
            DB::table('sessions')->where('user_id', $actorId)->where('id', '!=', $sessionId)->delete();
        });
    }
}
