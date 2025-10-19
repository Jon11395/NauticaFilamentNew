<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\ProjectTimesheetSelectorPolicy;
use App\Policies\GlobalConfigPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        'App\Filament\Pages\ProjectTimesheetSelector' => ProjectTimesheetSelectorPolicy::class,
        'App\Filament\Pages\GlobalConfig' => GlobalConfigPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        //
    }
}
