<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class LoginService
{
    public const FAILURE = 'Nieprawidłowy e-mail lub hasło.';

    public function authenticate(string $email, string $password, string $ip): void
    {
        // Hash keys so cache storage does not contain plaintext account identifiers.
        $accountKey = 'login:account:'.hash('sha256', $email.'|'.$ip);
        $ipKey = 'login:ip:'.hash('sha256', $ip);
        foreach ([$accountKey => 5, $ipKey => 50] as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                throw new TooManyRequestsHttpException(RateLimiter::availableIn($key));
            }
        }

        // SessionGuard verifies the hash, uses its timebox for failed attempts,
        // and the Eloquent provider excludes soft-deleted accounts.
        if (! Auth::attempt(['email' => $email, 'password' => $password, 'status' => 'active'], false)) {
            RateLimiter::hit($accountKey, 900);
            RateLimiter::hit($ipKey, 900);
            throw ValidationException::withMessages(['form' => self::FAILURE]);
        }

        RateLimiter::clear($accountKey);
        Auth::user()?->forceFill(['last_login_at' => now()])->save();
    }
}
