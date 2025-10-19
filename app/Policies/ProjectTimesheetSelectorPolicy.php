<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectTimesheetSelectorPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the page.
     */
    public function view(User $user): bool
    {
        return $user->can('page_ProjectTimesheetSelector');
    }

    /**
     * Determine whether the user can access the page.
     */
    public function access(User $user): bool
    {
        return $user->can('page_ProjectTimesheetSelector');
    }

    /**
     * Determine whether the user can remove an employee from a project.
     */
    public function removeEmployeeFromProject(User $user): bool
    {
        return $user->can('update_project') || $user->can('delete_employee');
    }
}
