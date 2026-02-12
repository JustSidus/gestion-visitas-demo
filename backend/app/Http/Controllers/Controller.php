<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use ValidatesRequests;

    /**
     * Wrapper simple para autorización basada en gates/policies.
     */
    protected function authorize(string $ability, $arguments = []): void
    {
        app(GateContract::class)->authorize($ability, $arguments);
    }
}
