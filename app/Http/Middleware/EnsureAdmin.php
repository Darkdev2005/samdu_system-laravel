<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = trim((string) ($user?->role ?? 'admin'));

        if ($role === 'kafedra_mudiri') {
            abort(403, 'Ushbu bo‘lim faqat admin uchun.');
        }

        return $next($request);
    }
}
