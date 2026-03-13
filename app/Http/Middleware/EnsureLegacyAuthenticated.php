<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegacyAuthenticated
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse|JsonResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            $request->session()->put('legacy_user', [
                'id' => (int) ($user?->id ?? 0),
                'username' => (string) ($user?->username ?? ''),
            ]);
            $request->session()->put('id', (int) ($user?->id ?? 0));
            $request->session()->put('username', (string) ($user?->username ?? ''));
            return $next($request);
        }

        $legacySession = $request->session()->get('legacy_user');
        if (is_array($legacySession) && !empty($legacySession['id'])) {
            /** @var \App\Models\User|null $user */
            $user = User::query()->find((int) $legacySession['id']);
            if ($user) {
                Auth::login($user);
                $request->session()->put('id', (int) $user->id);
                $request->session()->put('username', (string) $user->username);
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 1,
                'message' => "Avval tizimga kiring.",
            ], 401);
        }

        return redirect()->route('login');
    }
}
