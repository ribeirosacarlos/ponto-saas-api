<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UserVisibilityService
{
    private const MANAGERIAL_ROLES = ['manager', 'area_manager'];

    private const ADMINISTRATIVE_ROLES = ['admin', 'manager', 'area_manager'];

    public function canAccessAdministrativeUsers(User $actor): bool
    {
        return $actor->hasRole(self::ADMINISTRATIVE_ROLES);
    }

    public function canManageUser(User $actor, User $target): bool
    {
        if (! $this->belongsToSameCompany($actor, $target)) {
            return false;
        }

        if ($actor->hasRole('admin')) {
            return true;
        }

        if (! $actor->hasRole(self::MANAGERIAL_ROLES)) {
            return false;
        }

        return $this->managesArea($actor, $target->area_id);
    }

    public function canViewUser(User $actor, User $target): bool
    {
        if (! $this->belongsToSameCompany($actor, $target)) {
            return false;
        }

        if ((string) $actor->id === (string) $target->id) {
            return true;
        }

        return $this->canManageUser($actor, $target);
    }

    public function canManageUserId(User $actor, ?string $targetUserId): bool
    {
        if (! $targetUserId) {
            return false;
        }

        return $this->visibleUsersQuery($actor)->whereKey($targetUserId)->exists();
    }

    public function visibleUsersQuery(User $actor): Builder
    {
        $query = User::query();

        return $this->applyToUserQuery($query, $actor);
    }

    public function applyToUserQuery(Builder $query, User $actor): Builder
    {
        $companyColumn = $query->getModel()->qualifyColumn('company_id');
        $idColumn = $query->getModel()->qualifyColumn('id');
        $areaColumn = $query->getModel()->qualifyColumn('area_id');

        $query->where($companyColumn, $actor->company_id);

        if ($actor->hasRole('admin')) {
            return $query;
        }

        if ($actor->hasRole(self::MANAGERIAL_ROLES)) {
            $managedAreaIds = $this->managedAreaIds($actor);

            if (empty($managedAreaIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn($areaColumn, $managedAreaIds);
        }

        if ($actor->hasRole('employee')) {
            return $query->where($idColumn, $actor->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function applyToUserOwnedQuery(
        Builder $query,
        User $actor,
        string $userIdColumn = 'user_id',
        ?string $companyIdColumn = 'company_id'
    ): Builder {
        if ($companyIdColumn !== null) {
            $query->where($companyIdColumn, $actor->company_id);
        }

        if ($actor->hasRole('admin')) {
            return $query;
        }

        if ($actor->hasRole(self::MANAGERIAL_ROLES)) {
            $managedAreaIds = $this->managedAreaIds($actor);

            if (empty($managedAreaIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn($userIdColumn, function ($subQuery) use ($actor, $managedAreaIds) {
                $subQuery->select('id')
                    ->from('users')
                    ->where('company_id', $actor->company_id)
                    ->whereIn('area_id', $managedAreaIds);
            });
        }

        if ($actor->hasRole('employee')) {
            return $query->where($userIdColumn, $actor->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function managedAreaIds(User $actor): array
    {
        if ($actor->hasRole('admin')) {
            return [];
        }

        if (! $actor->hasRole(self::MANAGERIAL_ROLES)) {
            return [];
        }

        return $actor->managedAreas()
            ->where('areas.company_id', $actor->company_id)
            ->pluck('areas.id')
            ->unique()
            ->values()
            ->all();
    }

    private function managesArea(User $actor, ?string $areaId): bool
    {
        if (! $areaId) {
            return false;
        }

        return in_array($areaId, $this->managedAreaIds($actor), true);
    }

    private function belongsToSameCompany(User $actor, User $target): bool
    {
        return (string) $actor->company_id === (string) $target->company_id;
    }
}
