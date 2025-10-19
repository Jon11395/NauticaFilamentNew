<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GlobalConfigPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the page.
     */
    public function view(User $user): bool
    {
        return $user->can('page_GlobalConfig');
    }

    /**
     * Determine whether the user can access the page.
     */
    public function access(User $user): bool
    {
        return $user->can('page_GlobalConfig');
    }

    /**
     * Determine whether the user can update global configurations.
     */
    public function update(User $user): bool
    {
        return $user->can('page_GlobalConfig') && $user->hasAnyRole(['admin', 'super_admin']);
    }
}
