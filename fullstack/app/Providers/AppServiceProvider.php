<?php

namespace App\Providers;

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
        \Inertia\Inertia::share('managerPendingPayments', function(){
            $user = auth()->user();
            if(!$user || !$user->isManager()) return 0;
            try {
                return \App\Models\Payment::where('status','paid')->count();
            } catch (\Throwable $e) { return 0; }
        });
    }
}
