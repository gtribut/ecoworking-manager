<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Booking;
use App\Models\Company;
use App\Models\DeskAbsence;
use App\Models\DeskOccupation;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use App\Policies\ActivityPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

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
        // Limiteur du groupe `api` (activé par throttleApi() dans bootstrap/app.php,
        // review sécurité M1) : par utilisateur authentifié, sinon par IP. 60/min
        // couvre largement l'usage SPA (~50 membres) tout en bloquant l'abus.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) ($request->user()?->id ?: $request->ip())));

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
            'desk_absence' => DeskAbsence::class,
            'member_profile' => MemberProfile::class,
            // `desk_occupation` : audité depuis le lot E (annulation d'un bureau
            // nomade external, restitution de ticket incluse).
            'desk_occupation' => DeskOccupation::class,
        ]);

        // Audit log (C12.8b, PRD §4.14) : le modèle Activity vit chez Spatie,
        // hors auto-discovery des Policies → enregistrement manuel. Lecture
        // admin-only, écriture/suppression interdites à tous (lecture seule).
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Dashboard Laravel Pulse (/pulse) réservé aux admins (C10.3, BRIEF §16).
        // Sans ce gate, Pulse refuse l'accès hors environnement local.
        Gate::define('viewPulse', fn (User $user): bool => $user->isAdmin());

        // Transport Brevo API (C8) : Laravel ne connaît pas le scheme `brevo`,
        // on enregistre le transport du bridge Symfony. Utilisé en prod
        // (MAIL_MAILER=brevo) ; en dev/test le mailer reste smtp/array, donc
        // ce transport n'est jamais instancié hors prod. Client HTTP/dispatcher
        // laissés par défaut (le bridge crée un HttpClient si null).
        Mail::extend('brevo', fn (): BrevoApiTransport => new BrevoApiTransport(
            (string) config('services.brevo.key'),
        ));
    }
}
