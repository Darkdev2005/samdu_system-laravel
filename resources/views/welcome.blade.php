<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', "O'quv Qo'lanma") }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-gray-100 text-gray-900">
    <main class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-lg rounded-xl bg-white p-8 shadow-sm text-center">
            <h1 class="text-2xl font-semibold">O'quv Qo'lanma</h1>
            <p class="mt-2 text-sm text-gray-600">Laravel tizimi ishga tushdi.</p>
            <div class="mt-6 flex items-center justify-center gap-3">
                @auth
                    <a href="{{ url('/dashboard') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Login</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Register</a>
                    @endif
                @endauth
            </div>
        </div>
    </main>
</body>
</html>
