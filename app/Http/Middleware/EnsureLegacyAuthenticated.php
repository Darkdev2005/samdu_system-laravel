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
            if (! (bool) ($user?->is_active ?? true)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'username' => "Foydalanuvchi vaqtincha faolsizlantirilgan.",
                ]);
            }
            $request->session()->put('legacy_user', [
                'id' => (int) ($user?->id ?? 0),
                'username' => (string) ($user?->username ?? ''),
                'name' => (string) ($user?->name ?? ''),
                'role' => (string) ($user?->role ?? 'admin'),
                'kafedra_id' => (int) ($user?->kafedra_id ?? 0),
            ]);
            $request->session()->put('id', (int) ($user?->id ?? 0));
            $request->session()->put('username', (string) ($user?->username ?? ''));
            $request->session()->put('name', (string) ($user?->name ?? ''));
            $request->session()->put('role', (string) ($user?->role ?? 'admin'));
            $request->session()->put('kafedra_id', (int) ($user?->kafedra_id ?? 0));
            return $next($request);
        }

        $legacySession = $request->session()->get('legacy_user');
        if (is_array($legacySession) && !empty($legacySession['id'])) {
            /** @var \App\Models\User|null $user */
            $user = User::query()->find((int) $legacySession['id']);
            if ($user && (bool) ($user->is_active ?? true)) {
                Auth::login($user);
                $request->session()->put('legacy_user', [
                    'id' => (int) $user->id,
                    'username' => (string) $user->username,
                    'name' => (string) ($user->name ?? ''),
                    'role' => (string) ($user->role ?? 'admin'),
                    'kafedra_id' => (int) ($user->kafedra_id ?? 0),
                ]);
                $request->session()->put('id', (int) $user->id);
                $request->session()->put('username', (string) $user->username);
                $request->session()->put('name', (string) ($user->name ?? ''));
                $request->session()->put('role', (string) ($user->role ?? 'admin'));
                $request->session()->put('kafedra_id', (int) ($user->kafedra_id ?? 0));
                return $next($request);
            }

            if ($user && ! (bool) ($user->is_active ?? true)) {
                $request->session()->forget(['legacy_user', 'id', 'username', 'name', 'role', 'kafedra_id']);
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
