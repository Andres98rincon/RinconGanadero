<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

use function activity;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower(trim($request->string('email')->toString()));
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            $password = $request->input('password');

            if (
                ! $user
                || ! $user->isActive()
                || $user->auth_method !== 'password'
                || ! is_string($password)
                || blank($user->password)
                || ! Hash::check($password, $user->password)
            ) {
                activity('authentication')
                    ->event('password_login_rejected')
                    ->withProperties(['provider' => 'password', 'email' => $email])
                    ->log('Intento de inicio de sesión rechazado');

                return null;
            }

            activity('authentication')
                ->causedBy($user)
                ->event('password_login')
                ->withProperties(['provider' => 'password'])
                ->log('Inicio de sesión mediante contraseña');

            return $user;
        });
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            'request' => $request,
        ]));

        RateLimiter::for('login', function (Request $request): Limit {
            $key = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });
    }
}
