<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\UserVisibilityService;

class DocumentPolicy
{
    private const PRIVILEGED_ROLES = ['admin', 'manager', 'area_manager'];

    public function view(User $user, Document $document): bool
    {
        if (! $this->sameCompany($user, $document)) {
            return false;
        }

        if ((string) $user->id === (string) $document->user_id) {
            return true;
        }

        if (! $this->isPrivileged($user)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return app(UserVisibilityService::class)->canManageUserId($user, $document->user_id);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        if (! $this->sameCompany($user, $document)) {
            return false;
        }

        if (! $this->isPrivileged($user)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return app(UserVisibilityService::class)->canManageUserId($user, $document->user_id);
    }

    public function delete(User $user, Document $document): bool
    {
        if (! $this->sameCompany($user, $document)) {
            return false;
        }

        if ($this->isPrivileged($user)) {
            if ($user->hasRole('admin')) {
                return true;
            }

            return app(UserVisibilityService::class)->canManageUserId($user, $document->user_id);
        }

        return $user->id === $document->user_id && $document->status === Document::STATUS_PENDING;
    }

    public function adminList(User $user): bool
    {
        return $this->isPrivileged($user);
    }

    public function adminShow(User $user, Document $document): bool
    {
        return $this->sameCompany($user, $document) && $this->update($user, $document);
    }

    public function adminApprove(User $user, Document $document): bool
    {
        return $this->adminShow($user, $document);
    }

    public function adminReject(User $user, Document $document): bool
    {
        return $this->adminShow($user, $document);
    }

    public function resend(User $user, Document $document): bool
    {
        if (! $this->sameCompany($user, $document)) {
            return false;
        }

        return (string) $user->id === (string) $document->user_id
            && $document->status === Document::STATUS_REVIEW;
    }

    private function sameCompany(User $user, Document $document): bool
    {
        return $user->company_id === $document->company_id;
    }

    private function isPrivileged(User $user): bool
    {
        return $user->hasRole(self::PRIVILEGED_ROLES);
    }
}
