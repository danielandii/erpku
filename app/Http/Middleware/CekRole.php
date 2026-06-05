<?php

namespace App\Http\Middleware;

use Closure;

class CekRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, ...$role)
    {
        if(!in_array(\Auth::user()->role_id, $role)){
            // dd($role);
            return redirect('login');
        }
        return $next($request);
    }
}
