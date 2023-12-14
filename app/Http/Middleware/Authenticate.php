<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Closure;
use Auth;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }

    public function handle($request, Closure $next, ...$guards)
    {

        if ($request->cookie('authcookie')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->cookie('authcookie'));
        }
        $this->authenticate($request, $guards);

        return $next($request);
    }



}
