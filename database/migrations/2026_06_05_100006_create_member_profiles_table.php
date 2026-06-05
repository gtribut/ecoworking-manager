<?php

declare(strict_types=1);

use App\Enums\MemberProfileStatus;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `member_profiles` — 1-1 optionnel avec `users`. Profil public (annuaire)
 * + rattachement entité + bureau attitré. data_model §4.1.
 *
 * `desk_id` : la colonne et l'index unique (bureau ↔ membre 1-1, §6.10) sont
 * posés ici ; la FK vers `resources` est différée (table créée en Phase 3,
 * cf. migration add_deferred_foreign_keys).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();

            // 1-1 avec users ; restrict (anonymiser, pas supprimer)
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();

            // Entité de rattachement. NULL toléré en base (staff sans entité) ;
            // NOT NULL au niveau app pour resident/additional/external.
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();

            // Bureau attitré (resource desk) — FK différée vers resources.
            $table->unsignedBigInteger('desk_id')->nullable()->unique();

            $table->string('status', 10)->default(MemberProfileStatus::Active->value);
            $table->date('arrival_date')->nullable();
            $table->date('departure_date')->nullable();

            // Profil public
            $table->string('photo_path')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('job_title', 150)->nullable();
            $table->text('bio')->nullable();
            $table->string('interests')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('website_url')->nullable();
            $table->boolean('show_in_directory')->default(false);
            $table->boolean('newsletter_opt_in')->default(false);

            $table->text('admin_notes')->nullable();

            $table->timestampsTz();

            $table->index('company_id');
            $table->index('status');
        });

        Check::enum('member_profiles', 'status', MemberProfileStatus::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
