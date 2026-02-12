<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Override the redirect target to avoid resolving undefined web routes.
     */
    protected function redirectTo($request): ?string
    {
        return null;
    }
}
