<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->uuid('check_in_token')->unique();
            $table->string('first_name', 120);
            $table->string('last_name', 160);
            $table->string('national_id', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('client_type', 60)->nullable();
            $table->string('status', 20)->default('active');
            $table->date('started_on');
            $table->unsignedInteger('sessions_remaining')->default(0);
            $table->json('intake')->nullable();
            $table->json('initial_metrics')->nullable();
            $table->boolean('informed_consent_accepted')->default(false);
            $table->boolean('risk_assumption_accepted')->default(false);
            $table->date('consent_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'legacy_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'last_name', 'first_name']);
            $table->index(['organization_id', 'national_id']);
        });

        Schema::create('member_progress_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->date('recorded_on');
            $table->json('metrics')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['member_id', 'legacy_id']);
            $table->index(['member_id', 'recorded_on']);
        });

        Schema::create('member_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_in_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamps();
            $table->unique(['organization_id', 'legacy_id']);
            $table->index(['member_id', 'checked_in_at']);
            $table->index(['organization_id', 'checked_in_at']);
        });

        Schema::create('member_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->unsignedInteger('sessions_purchased')->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
            $table->unique(['organization_id', 'legacy_id']);
            $table->index(['member_id', 'paid_at']);
            $table->index(['organization_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_payments');
        Schema::dropIfExists('member_attendances');
        Schema::dropIfExists('member_progress_entries');
        Schema::dropIfExists('members');
    }
};
