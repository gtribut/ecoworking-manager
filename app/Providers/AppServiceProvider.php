<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Booking;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Morph map : découple la base des namespaces PHP (data_model §5).
        // Alias courts stockés en colonnes `*_type`. enforceMorphMap interdit
        // tout type polymorphe non déclaré ici (filet anti-fuite de namespace).
        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'subscription' => Subscription::class,
            'purchase' => Purchase::class,
            'booking' => Booking::class,
        ]);
    }
}
