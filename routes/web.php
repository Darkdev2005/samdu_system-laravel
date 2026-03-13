<?php

use App\Http\Controllers\LegacyProxyController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/assets/{path}', [LegacyProxyController::class, 'asset'])
    ->where('path', '.*')
    ->name('asset.legacy');

Route::middleware('legacy.auth')->group(function (): void {
    Route::get('/dashboard', fn () => redirect()->route('dashboard.legacy', ['path' => 'index.php']))
        ->name('dashboard');

    Route::any('/dashboard/{path?}', [LegacyProxyController::class, 'dashboard'])
        ->where('path', '.*')
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
        ->name('dashboard.legacy');

    // Old bridge URL for backward compatibility.
    Route::any('/legacy/dashboard/{path?}', fn (?string $path = null) => redirect('/dashboard/'.($path ?: 'index.php')))
        ->where('path', '.*');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Legacy sidebar uses GET /logout link.
Route::get('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout.get');

require __DIR__.'/auth.php';
