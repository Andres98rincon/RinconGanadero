<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnimalController;
use App\Http\Controllers\AnimalEventController;
use App\Http\Controllers\AnimalMovementController;
use App\Http\Controllers\AnimalValuationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\FinancialDashboardController;
use App\Http\Controllers\FinancialTransactionController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\MilkProductionController;
use App\Http\Controllers\MilkProductionRecordController;
use App\Http\Controllers\PaddockController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReproductiveEventController;
use App\Http\Controllers\WeightController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\Paddock;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

Route::redirect('/', '/dashboard');

Route::middleware(['guest', 'throttle:10,1'])->group(function (): void {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});

Route::middleware(['auth', EnsureAccountIsActive::class])->group(function (): void {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('admin/activity-log', [ActivityLogController::class, 'index'])
        ->middleware('can:activity-log.view')
        ->name('admin.activity-log.index');
    Route::get('admin/backups', [BackupController::class, 'index'])
        ->middleware('can:backups.manage')
        ->name('admin.backups.index');
    Route::post('admin/backups', [BackupController::class, 'store'])
        ->middleware('can:backups.manage')
        ->name('admin.backups.store');
    Route::get('admin/backups/{backupId}/download', [BackupController::class, 'download'])
        ->where('backupId', '[a-f0-9]{64}')
        ->middleware('can:backups.manage')
        ->name('admin.backups.download');

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('can:dashboard.view')
        ->name('dashboard');

    Route::resource('farms', FarmController::class)
        ->middlewareFor(['index'], 'can:viewAny,'.Farm::class)
        ->middlewareFor(['show'], 'can:view,farm')
        ->middlewareFor(['create', 'store'], 'can:create,'.Farm::class)
        ->middlewareFor(['edit', 'update'], 'can:update,farm')
        ->middlewareFor(['destroy'], 'can:delete,farm');
    Route::get('farms/{farm}/paddock-options', [PaddockController::class, 'options'])
        ->middleware('can:viewAny,'.Paddock::class)
        ->name('farms.paddock-options');
    Route::resource('paddocks', PaddockController::class)
        ->except(['show'])
        ->middlewareFor(['index'], 'can:viewAny,'.Paddock::class)
        ->middlewareFor(['create', 'store'], 'can:create,'.Paddock::class)
        ->middlewareFor(['edit', 'update'], 'can:update,paddock')
        ->middlewareFor(['destroy'], 'can:delete,paddock');
    Route::post('animals/import', [AnimalController::class, 'import'])
        ->middleware(['can:animals.import', 'throttle:5,1'])
        ->name('animals.import');
    Route::get('animals/export/{format}', [AnimalController::class, 'export'])
        ->whereIn('format', ['xlsx', 'csv'])
        ->middleware('can:animals.export')
        ->name('animals.export');
    Route::get('animals/import-template', [AnimalController::class, 'template'])
        ->middleware('can:animals.import')
        ->name('animals.import-template');
    Route::resource('animals', AnimalController::class)
        ->middlewareFor(['index'], 'can:viewAny,'.Animal::class)
        ->middlewareFor(['show'], 'can:view,animal')
        ->middlewareFor(['create', 'store'], 'can:create,'.Animal::class)
        ->middlewareFor(['edit', 'update'], 'can:update,animal')
        ->middlewareFor(['destroy'], 'can:delete,animal');

    Route::get('animal-movements', [AnimalMovementController::class, 'index'])
        ->middleware('can:animal-movements.view')
        ->name('animal-movements.index');
    Route::get('animal-movements/create', [AnimalMovementController::class, 'create'])
        ->middleware('can:animal-movements.create')
        ->name('animal-movements.create');
    Route::post('animal-movements', [AnimalMovementController::class, 'store'])
        ->middleware('can:animal-movements.create')
        ->name('animal-movements.store');
    Route::resource('reproduction', ReproductiveEventController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->middlewareFor(['index', 'show'], 'can:reproduction.view')
        ->middlewareFor(['create', 'store'], 'can:reproduction.create');

    Route::get('milk-production', [MilkProductionController::class, 'index'])
        ->middleware('can:milk-production.view')
        ->name('milk-production.index');
    Route::get('milk-production/create', [MilkProductionController::class, 'create'])
        ->middleware('can:milk-production.create')
        ->name('milk-production.create');
    Route::post('milk-production', [MilkProductionController::class, 'store'])
        ->middleware('can:milk-production.create')
        ->name('milk-production.store');
    Route::get('weights/estimate', [WeightController::class, 'estimateForm'])
        ->middleware('can:weights.view')
        ->name('weights.estimate');
    Route::post('weights/estimate', [WeightController::class, 'calculateEstimate'])
        ->middleware('can:weights.view')
        ->name('weights.estimate.calculate');
    Route::get('weights', [WeightController::class, 'index'])
        ->middleware('can:weights.view')
        ->name('weights.index');
    Route::get('weights/create', [WeightController::class, 'create'])
        ->middleware('can:weights.create')
        ->name('weights.create');
    Route::post('weights', [WeightController::class, 'store'])
        ->middleware('can:weights.create')
        ->name('weights.store');
    Route::get('finance', [FinancialDashboardController::class, 'index'])
        ->middleware('can:finance.view')
        ->name('finance.index');
    Route::get('reports/export', [ReportsController::class, 'export'])
        ->middleware('can:reports.export')
        ->name('reports.export');
    Route::get('reports', [ReportsController::class, 'index'])
        ->middleware('can:reports.view')
        ->name('reports.index');
    Route::get('finance/transactions/create', [FinancialTransactionController::class, 'create'])
        ->middleware('can:finance.transactions.create')
        ->name('finance.transactions.create');
    Route::post('finance/transactions', [FinancialTransactionController::class, 'store'])
        ->middleware('can:finance.transactions.create')
        ->name('finance.transactions.store');
    Route::get('finance/valuations/create', [AnimalValuationController::class, 'create'])
        ->middleware('can:finance.valuations.create')
        ->name('finance.valuations.create');
    Route::post('finance/valuations', [AnimalValuationController::class, 'store'])
        ->middleware('can:finance.valuations.create')
        ->name('finance.valuations.store');

    Route::get('animals/{animal}/production', [MilkProductionRecordController::class, 'index'])
        ->middleware('can:milk-production.view')
        ->name('animals.production.index');
    Route::post('animals/{animal}/production', [MilkProductionRecordController::class, 'store'])
        ->middleware('can:milk-production.create')
        ->name('animals.production.store');
    Route::get('animals/{animal}/production/{productionRecord}/edit', [MilkProductionRecordController::class, 'edit'])
        ->scopeBindings()
        ->middleware('can:milk-production.update')
        ->name('animals.production.edit');
    Route::put('animals/{animal}/production/{productionRecord}', [MilkProductionRecordController::class, 'update'])
        ->scopeBindings()
        ->middleware('can:milk-production.update')
        ->name('animals.production.update');
    Route::post('animals/{animal}/events', [AnimalEventController::class, 'store'])
        ->middleware('can:update,animal')
        ->name('animals.events.store');
    Route::delete('animals/{animal}/events/{event}', [AnimalEventController::class, 'destroy'])
        ->middleware('can:update,animal')
        ->name('animals.events.destroy');

    Route::resource('admin/users', UserController::class)
        ->except(['show'])
        ->parameters(['users' => 'user'])
        ->names('admin.users')
        ->middlewareFor(['index'], 'can:viewAny,'.User::class)
        ->middlewareFor(['create', 'store'], 'can:create,'.User::class)
        ->middlewareFor(['edit', 'update'], 'can:update,user')
        ->middlewareFor(['destroy'], 'can:delete,user');
    Route::resource('admin/roles', RoleController::class)
        ->except(['show'])
        ->parameters(['roles' => 'role'])
        ->names('admin.roles')
        ->middlewareFor(['index'], 'can:viewAny,'.Role::class)
        ->middlewareFor(['create', 'store'], 'can:create,'.Role::class)
        ->middlewareFor(['edit', 'update'], 'can:update,role')
        ->middlewareFor(['destroy'], 'can:delete,role');
    Route::resource('admin/permissions', PermissionController::class)
        ->only(['index', 'create', 'store', 'destroy'])
        ->parameters(['permissions' => 'permission'])
        ->names('admin.permissions')
        ->middlewareFor(['index'], 'can:viewAny,'.Permission::class)
        ->middlewareFor(['create', 'store'], 'can:create,'.Permission::class)
        ->middlewareFor(['destroy'], 'can:delete,permission');
});
