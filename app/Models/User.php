<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\AccessControl;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'account_status', 'auth_method'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia
{
    protected $attributes = [
        'account_status' => 'active',
        'auth_method' => 'password',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithMedia, LogsActivity, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'first_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    public function farms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class)->withTimestamps();
    }

    public function accessibleFarmIdsQuery(): QueryBuilder
    {
        return $this->hasRole(AccessControl::SUPER_ADMIN_ROLE)
            ? DB::table('farms')->whereNull('deleted_at')->select('id')
            : DB::table('farm_user')
                ->join('farms', 'farm_user.farm_id', '=', 'farms.id')
                ->whereNull('farms.deleted_at')
                ->where('farm_user.user_id', $this->getKey())
                ->select('farm_user.farm_id');
    }

    public function canAccessFarm(?int $farmId): bool
    {
        if ($this->hasRole(AccessControl::SUPER_ADMIN_ROLE)) {
            return true;
        }

        return $farmId !== null && $this->farms()->whereKey($farmId)->exists();
    }

    public function canAccessUser(User $user): bool
    {
        if ($this->is($user)) {
            return true;
        }

        if ($this->hasRole(AccessControl::SUPER_ADMIN_ROLE)) {
            return ! $user->hasRole(AccessControl::SUPER_ADMIN_ROLE);
        }

        return ! $user->hasRole(AccessControl::SUPER_ADMIN_ROLE)
            && $user->farms()
                ->whereIn('farms.id', $this->accessibleFarmIdsQuery())
                ->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'account_status', 'auth_method'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('users');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile-photo')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('optimized')
            ->format('webp')
            ->quality(88)
            ->width(512)
            ->height(512)
            ->performOnCollections('profile-photo')
            ->nonQueued();
    }
}
