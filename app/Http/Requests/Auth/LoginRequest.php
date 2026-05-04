<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        /** @var \App\Models\User|null $user */
        $user = User::query()
            ->where('username', (string) $this->input('username'))
            ->first();

        $plainPassword = (string) $this->input('password');
        $storedHash = (string) ($user?->password ?? '');
        $isLegacyMd5 = preg_match('/^[a-f0-9]{32}$/i', $storedHash) === 1;

        $isValid = false;
        if ($storedHash !== '' && $plainPassword !== '') {
            if ($isLegacyMd5) {
                $isValid = hash_equals(strtolower($storedHash), md5($plainPassword));
            } else {
                try {
                    $isValid = Hash::check($plainPassword, $storedHash);
                } catch (\RuntimeException $e) {
                    $isValid = false;
                }
            }
        }

        if (! $user || ! $isValid) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        if (! (bool) ($user->is_active ?? true)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => "Foydalanuvchi vaqtincha faolsizlantirilgan.",
            ]);
        }

        // Legacy MD5 hash bilan kirilgan foydalanuvchini birinchi muvaffaqiyatli login paytida
        // xavfsiz hashga ko'chiramiz.
        if ($isLegacyMd5) {
            $user->forceFill([
                'password' => Hash::make($plainPassword),
            ])->save();
        }

        Auth::login($user, $this->boolean('remember'));
        $this->session()->put('legacy_user', [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'name' => (string) ($user->name ?? ''),
            'role' => (string) ($user->role ?? 'admin'),
            'kafedra_id' => (int) ($user->kafedra_id ?? 0),
        ]);
        $this->session()->put('id', (int) $user->id);
        $this->session()->put('username', (string) $user->username);
        $this->session()->put('name', (string) ($user->name ?? ''));
        $this->session()->put('role', (string) ($user->role ?? 'admin'));
        $this->session()->put('kafedra_id', (int) ($user->kafedra_id ?? 0));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}
