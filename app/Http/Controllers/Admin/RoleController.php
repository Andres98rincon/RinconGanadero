<?php

namespace App\Http\Controllers\Admin;

use App\AccessControl;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()
                ->withCount('permissions')
                ->with('permissions')
                ->with('users:id,name,email')
                ->orderBy('name')
                ->get(),
            'systemRoles' => [
                AccessControl::ADMIN_ROLE,
                AccessControl::OPERATOR_ROLE,
                AccessControl::SUPER_ADMIN_ROLE,
            ],
        ]);
    }

    public function create(): View
    {
        $availablePermissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->keyBy('name');

        return view('admin.roles.form', [
            'role' => null,
            'permissionGroups' => $this->permissionGroups($availablePermissions),
            'availablePermissions' => $availablePermissions,
            'permissionIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $permissionIds = $validated['permissions'] ?? [];
        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(Permission::query()->whereIn('id', $permissionIds)->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('admin.roles.index')->with('success', 'El rol se creó correctamente.');
    }

    public function edit(Role $role): View
    {
        abort_if($this->isSystemRole($role), 404);
        $availablePermissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->keyBy('name');

        return view('admin.roles.form', [
            'role' => $role->load('permissions'),
            'permissionGroups' => $this->permissionGroups($availablePermissions),
            'availablePermissions' => $availablePermissions,
            'permissionIds' => $role->permissions->modelKeys(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($this->isSystemRole($role), 404);

        $validated = $request->validate($this->rules($role));
        $permissionIds = $validated['permissions'] ?? [];
        $role->update(['name' => $validated['name']]);
        $role->syncPermissions(Permission::query()->whereIn('id', $permissionIds)->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('admin.roles.index')->with('success', 'El rol se actualizó correctamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($this->isSystemRole($role), 404);

        if ($role->users()->exists()) {
            return back()->withErrors([
                'role' => 'No se puede eliminar un rol que todavía está asignado a usuarios.',
            ]);
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('admin.roles.index')->with('success', 'El rol se eliminó correctamente.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?Role $role = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9._-]*$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $actor = auth()->user();

                    // Nobody except the super administrator may grant a permission they do not hold.
                    if ($actor && ! $actor->hasRole(AccessControl::SUPER_ADMIN_ROLE)
                        && ! $actor->getAllPermissions()->contains('id', (int) $value)) {
                        $fail('No puedes otorgar un permiso que tu usuario no tiene.');
                    }
                },
            ],
        ];
    }

    private function isSystemRole(Role $role): bool
    {
        return in_array($role->name, [
            AccessControl::ADMIN_ROLE,
            AccessControl::OPERATOR_ROLE,
            AccessControl::SUPER_ADMIN_ROLE,
        ], true);
    }

    /**
     * @param  Collection<string, Permission>  $permissions
     * @return array<string, array<string, string>>
     */
    private function permissionGroups(Collection $permissions): array
    {
        $groups = [];

        foreach ($permissions as $permission) {
            $groupName = str($permission->name)->before('.')->replace('-', ' ')->title()->toString();
            $groups[$groupName][$permission->name] = AccessControl::PERMISSION_LABELS[$permission->name]
                ?? str($permission->name)->replace(['.', '-', '_'], ' ')->ucfirst()->toString();
        }

        ksort($groups);

        return $groups;
    }
}
