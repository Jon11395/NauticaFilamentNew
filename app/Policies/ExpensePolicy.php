<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Expense;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpensePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_temporal::expense');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Expense $expense): bool
    {
        return $user->can('view_temporal::expense');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_temporal::expense');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Expense $expense): bool
    {
        return $user->can('update_temporal::expense');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Expense $expense): bool
    {
        return $user->can('delete_temporal::expense');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_temporal::expense');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Expense $expense): bool
    {
        return $user->can('force_delete_temporal::expense');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_temporal::expense');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Expense $expense): bool
    {
        return $user->can('restore_temporal::expense');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_temporal::expense');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Expense $expense): bool
    {
        return $user->can('replicate_temporal::expense');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_temporal::expense');
    }
}
