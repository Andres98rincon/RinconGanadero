<?php

namespace App\Http\Controllers\Admin;

use App\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', AccessControl::SUPER_ADMIN_ROLE))
            ->when(
                ! $request->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE),
                fn ($query) => $query->whereHas('farms', fn ($farmQuery) => $farmQuery
                    ->whereIn('farms.id', $request->user()->accessibleFarmIdsQuery())),
            )
            ->with(['roles', 'media'])
            ->orderBy('name')
            ->paginate(15);

        return view('admin.users.index', ['users' => $users]);
    }

    public function create(Request $request): View
    {
        return view('admin.users.form', [
            'managedUser' => null,
            'roles' => $this->assignableRoles($request->user()),
            'farms' => $this->assignableFarms($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $authMethod = $request->input('auth_method', 'password');
        $request->merge([
            'auth_method' => $authMethod,
            'account_status' => $request->input('account_status', $authMethod === 'google' ? 'pending' : 'active'),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                Rule::requiredIf($authMethod === 'password'),
                'nullable',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
            'auth_method' => ['required', Rule::in(['password', 'google'])],
            'account_status' => ['required', Rule::in(['pending', 'active', 'inactive'])],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'integer', 'distinct', Rule::in($this->assignableRoleIds($request->user()))],
            'farm_ids' => ['required', 'array', 'min:1'],
            'farm_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('farms', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->whereIn('id', $request->user()->accessibleFarmIdsQuery())),
            ],
        ]);

        if ($validated['auth_method'] === 'google' && $validated['account_status'] === 'active') {
            $validated['account_status'] = 'pending';
        }

        $roleIds = $validated['roles'];
        $farmIds = $validated['farm_ids'];
        unset($validated['roles']);
        unset($validated['farm_ids']);

        if (filled($validated['password'] ?? null)) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['email'] = mb_strtolower($validated['email']);
        $user = User::query()->create($validated);
        $user->syncRoles(Role::query()->whereIn('id', $roleIds)->get());
        $user->farms()->sync($farmIds);
        activity('users')
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('preregistered')
            ->log('Creó un prerregistro de usuario');

        return to_route('admin.users.index')->with('success', 'El usuario se creó correctamente.');
    }

    public function edit(Request $request, User $user): View
    {
        abort_if($user->hasRole(AccessControl::SUPER_ADMIN_ROLE), 404);

        return view('admin.users.form', [
            'managedUser' => $user->load('roles'),
            'roles' => $this->assignableRoles($request->user()),
            'farms' => $this->assignableFarms($request->user()),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->hasRole(AccessControl::SUPER_ADMIN_ROLE), 404);

        if (! $request->user()->is($user)) {
            $request->merge([
                'auth_method' => $request->input('auth_method', $user->auth_method),
                'account_status' => $request->input('account_status', $user->account_status),
            ]);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [
                Rule::requiredIf(fn (): bool => ($request->input('auth_method', $user->auth_method) === 'password') && blank($user->password)),
                'nullable',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
        ];

        if (! $request->user()->is($user)) {
            $rules['auth_method'] = ['required', Rule::in(['password', 'google'])];
            $rules['account_status'] = ['required', Rule::in(['pending', 'active', 'inactive'])];
            $rules['roles'] = ['required', 'array', 'min:1'];
            $rules['roles.*'] = ['required', 'integer', 'distinct', Rule::in($this->assignableRoleIds($request->user()))];
            $rules['farm_ids'] = ['required', 'array', 'min:1'];
            $rules['farm_ids.*'] = [
                'required',
                'integer',
                'distinct',
                Rule::exists('farms', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->whereIn('id', $request->user()->accessibleFarmIdsQuery())),
            ];
        }

        $previousStatus = $user->account_status;
        $previousRoleIds = $user->roles->modelKeys();
        $previousFarmIds = $user->farms()->pluck('farms.id')->all();
        $validated = $request->validate($rules);
        $roleIds = $validated['roles'] ?? null;
        $farmIds = $validated['farm_ids'] ?? null;
        unset($validated['roles']);
        unset($validated['farm_ids']);

        if (
            ($validated['auth_method'] ?? null) === 'google'
            && $user->google_id === null
            && ($validated['account_status'] ?? null) === 'active'
        ) {
            $validated['account_status'] = 'pending';
        }

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $validated['email'] = mb_strtolower($validated['email']);
        $user->update($validated);

        if ($roleIds !== null) {
            $user->syncRoles(Role::query()->whereIn('id', $roleIds)->get());

            if ($previousRoleIds !== $user->fresh()->roles->modelKeys()) {
                activity('users')
                    ->causedBy($request->user())
                    ->performedOn($user)
                    ->event('roles_changed')
                    ->withProperties(['previous_role_ids' => $previousRoleIds, 'new_role_ids' => $user->fresh()->roles->modelKeys()])
                    ->log('Actualizó los roles de un usuario');
            }
        }

        if ($farmIds !== null) {
            $user->farms()->sync($farmIds);

            if ($previousFarmIds !== array_map('intval', $farmIds)) {
                activity('users')
                    ->causedBy($request->user())
                    ->performedOn($user)
                    ->event('farms_changed')
                    ->withProperties(['previous_farm_ids' => $previousFarmIds, 'new_farm_ids' => array_map('intval', $farmIds)])
                    ->log('Actualizó las fincas asignadas a un usuario');
            }
        }

        if ($previousStatus !== $user->account_status) {
            activity('users')
                ->causedBy($request->user())
                ->performedOn($user)
                ->event($user->account_status === 'inactive' ? 'deactivated' : 'activated')
                ->log($user->account_status === 'inactive' ? 'Desactivó una cuenta' : 'Actualizó el estado de una cuenta');
        }

        return to_route('admin.users.index')->with('success', 'El usuario se actualizó correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if(
            $request->user()->is($user)
                || $user->hasAnyRole([AccessControl::ADMIN_ROLE, AccessControl::SUPER_ADMIN_ROLE]),
            403,
        );

        $user->delete();

        return to_route('admin.users.index')->with('success', 'El usuario se eliminó correctamente.');
    }

    private function assignableRoles(User $actor)
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->when(
                ! $actor->hasRole(AccessControl::SUPER_ADMIN_ROLE),
                fn ($query) => $query->where('name', AccessControl::OPERATOR_ROLE),
            )
            ->where('name', '!=', AccessControl::SUPER_ADMIN_ROLE)
            ->orderBy('name')
            ->get();
    }

    private function assignableFarms(User $actor)
    {
        return Farm::query()->orderBy('name')->get(['id', 'name', 'code']);
    }

    /**
     * @return list<int>
     */
    private function assignableRoleIds(User $actor): array
    {
        return $this->assignableRoles($actor)->modelKeys();
    }
}
