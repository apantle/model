<?php

namespace Orchestra\Model;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Laravie\Dhosa\Concerns\Swappable;
use Orchestra\Contracts\Authorization\Authorizable;

class User extends Eloquent implements Authorizable, UserContract
{
    use Authenticatable,
        Concerns\CheckRoles,
        Concerns\Searchable,
        Swappable,
        SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Available user status as constant.
     */
    const UNVERIFIED = 0;
    const SUSPENDED = 63;
    const VERIFIED = 1;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * List of searchable attributes.
     *
     * @var array
     */
    protected $searchable = ['email', 'fullname'];

    /**
     * Get searchable rules.
     */
    public function getSearchableTerms(): array
    {
        return [
            'roles:[]' => static function (Builder $query, array $roles) {
                return $query->hasRoles($roles);
            },
        ];
    }

    /**
     * Search user based on keyword as roles.
     */
    public function scopeHasRoles(Builder $query, array $roles = []): Builder
    {
        $query->with('roles')->whereNotNull('users.id');

        if (! empty($roles)) {
            $query->whereHas('roles', static function ($query) use ($roles) {
                $query->whereIn(Role::column('name'), $roles);
            });
        }

        return $query;
    }

    /**
     * Search user based on keyword as roles id.
     */
    public function scopeHasRolesId(Builder $query, array $rolesId = []): Builder
    {
        $query->with('roles')->whereNotNull('users.id');

        if (! empty($rolesId)) {
            $query->whereHas('roles', static function ($query) use ($rolesId) {
                $query->whereIn(Role::column('id'), $rolesId);
            });
        }

        return $query;
    }

    /**
     * Has many and belongs to relationship with Role.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::hsFinder(), 'user_role', 'user_id', 'role_id')->withTimestamps();
    }

    /**
     * Set `password` mutator.
     */
    public function setPasswordAttribute(string $value): void
    {
        if (Hash::needsRehash($value)) {
            $value = Hash::make($value);
        }

        $this->attributes['password'] = $value;
    }

    /**
     * Activate current user.
     *
     * @return $this
     */
    public function activate()
    {
        $this->setAttribute('status', self::VERIFIED);
        $this->setAttribute('email_verified_at', now());

        return $this;
    }

    /**
     * Deactivate current user.
     *
     * @return $this
     */
    public function deactivate()
    {
        $this->setAttribute('status', self::UNVERIFIED);

        return $this;
    }

    /**
     * Suspend current user.
     *
     * @return $this
     */
    public function suspend()
    {
        $this->setAttribute('status', self::SUSPENDED);

        return $this;
    }

    /**
     * Determine if the current user account activated or not.
     */
    public function isActivated(): bool
    {
        return $this->getAttribute('status') == self::VERIFIED;
    }

    /**
     * Determine if the current user account suspended or not.
     */
    public function isSuspended(): bool
    {
        return $this->getAttribute('status') == self::SUSPENDED;
    }

    /**
     * Assign role to user.
     *
     * @param  \Orchestra\Model\Role|int|array  $roles
     *
     * @return $this
     */
    public function attachRole($roles)
    {
        return $this->attachRoles($roles);
    }

    /**
     * Un-assign role from user.
     *
     * @param  \Orchestra\Model\Role|int|array  $roles
     *
     * @return $this
     */
    public function detachRole($roles)
    {
        return $this->detachRoles($roles);
    }

    /**
     * Get roles name as an array.
     */
    public function getRoles(): Collection
    {
        // If the relationship is already loaded, avoid re-querying the
        // database and instead fetch the collection.
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        return $this->getRelation('roles')->pluck('name');
    }

    /**
     * Get Hot-swappable alias name.
     */
    final public static function hsAliasName(): string
    {
        return 'User';
    }
}
