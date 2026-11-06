<?php

namespace App\Http\Controllers\Admin;

use App\AccessControl;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index(): View
    {
        return view('admin.permissions.index', [
            'permissions' => Permission::query()
                ->withCount('roles')
                ->orderBy('name')
                ->get(),
            'labels' => AccessControl::PERMISSION_LABELS,
        ]);
    }

    public function create(): View
    {
        return view('admin.permissions.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*)+$/',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
        ]);

        Permission::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('admin.permissions.index')->with('success', 'El permiso se creó correctamente.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($permission->roles()->exists() || $permission->users()->exists()) {
            return back()->withErrors([
                'permission' => 'Quita el permiso de los roles y usuarios antes de eliminarlo.',
            ]);
        }

        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('admin.permissions.index')->with('success', 'El permiso se eliminó correctamente.');
    }
}
