<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Admin = akses penuh (CLAUDE.md §23), tanpa perlu assign tiap permission satu-satu.
        Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);
    }
}
