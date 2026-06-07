<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Booking;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Morph map : découple la base des namespaces PHP (data_model §5).
        // Alias courts stockés en colonnes `*_type`. enforceMorphMap interdit
        // tout type polymorphe non déclaré ici (filet anti-fuite de namespace).
        // `invoice`/`payment` sont ajoutés car ils figurent comme `subject`
        // polymorphe de l'audit log (activity_log) — entités sensibles, C1.8.
        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'subscription' => Subscription::class,
            'purchase' => Purchase::class,
            'booking' => Booking::class,
            'invoice' => Invoice::class,
            'payment' => Payment::class,
        ]);

        // Dashboard Laravel Pulse (/pulse) réservé aux admins (C10.3, BRIEF §16).
        // Sans ce gate, Pulse refuse l'accès hors environnement local.
        Gate::define('viewPulse', fn (User $user): bool => $user->isAdmin());
    }
}
