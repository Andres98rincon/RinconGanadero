<?php

namespace App\Http\Controllers;

use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function redirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        abort_unless($this->isConfigured(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException|GuzzleException $exception) {
            report($exception);

            return $this->reject('No se pudo validar la respuesta de Google. Intenta iniciar sesión nuevamente.');
        }

        $email = mb_strtolower(trim((string) $googleUser->getEmail()));
        $googleId = trim((string) $googleUser->getId());
        $isEmailVerified = filter_var($googleUser->getRaw()['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($email === '' || $googleId === '' || ! $isEmailVerified) {
            return $this->reject('Google no confirmó una dirección de correo válida.', $email ?: null);
        }

        $user = DB::transaction(function () use ($email, $googleId): ?User {
            $linkedUser = User::query()->where('google_id', $googleId)->lockForUpdate()->first();

            if ($linkedUser) {
                if (
                    mb_strtolower($linkedUser->email) !== $email
                    || ! $linkedUser->isActive()
                    || $linkedUser->auth_method !== 'google'
                ) {
                    return null;
                }

                $linkedUser->forceFill([
                    'first_login_at' => $linkedUser->first_login_at ?? now(),
                    'email_verified_at' => $linkedUser->email_verified_at ?? now(),
                ])->save();

                return $linkedUser;
            }

            $preregisteredUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();

            if (
                ! $preregisteredUser
                || $preregisteredUser->account_status !== 'pending'
                || $preregisteredUser->auth_method !== 'google'
                || $preregisteredUser->google_id !== null
            ) {
                return null;
            }

            $preregisteredUser->forceFill([
                'google_id' => $googleId,
                'account_status' => 'active',
                'auth_method' => 'google',
                'first_login_at' => $preregisteredUser->first_login_at ?? now(),
                'email_verified_at' => $preregisteredUser->email_verified_at ?? now(),
            ])->save();

            return $preregisteredUser;
        });

        if (! $user) {
            return $this->reject('Esta cuenta no está autorizada para ingresar. Solicita acceso al administrador.', $email);
        }

        Auth::login($user);
        $request->session()->regenerate();

        activity('authentication')
            ->causedBy($user)
            ->event('google_login')
            ->withProperties(['provider' => 'google'])
            ->log('Inicio de sesión mediante Google');

        return to_route('dashboard')->with('success', 'Sesión iniciada correctamente.');
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function reject(string $message, ?string $email = null): RedirectResponse
    {
        activity('authentication')
            ->event('google_login_rejected')
            ->withProperties(array_filter([
                'provider' => 'google',
                'email' => $email,
            ], fn (mixed $value): bool => $value !== null))
            ->log('Intento de inicio de sesión Google rechazado');

        return to_route('login')->with('error', $message);
    }
}
