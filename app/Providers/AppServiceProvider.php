<?php

namespace App\Providers;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\Paddock;
use App\Models\User;
use App\Policies\AnimalPolicy;
use App\Policies\FarmPolicy;
use App\Policies\PaddockPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole('superadministrador') ? true : null);
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                activity('authentication')
                    ->causedBy($event->user)
                    ->event('logout')
                    ->log('Cerró sesión');
            }
        });

        Gate::policy(Animal::class, AnimalPolicy::class);
        Gate::policy(Farm::class, FarmPolicy::class);
        Gate::policy(Paddock::class, PaddockPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
    }
}
