<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_pre_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 160)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('document_type', 20)->nullable();
            $table->string('document_number', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('client_type', 60)->nullable();
            $table->json('intake')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'last_name', 'first_name'], 'mpr_org_name_idx');
            $table->index(['organization_id', 'document_type', 'document_number'], 'mpr_org_document_idx');
        });

        Schema::create('member_pre_registration_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_pre_registration_id')
                ->constrained(indexName: 'mpr_review_pre_registration_fk')
                ->cascadeOnDelete();
            $table->string('decision', 30);
            $table->text('observations');
            $table->json('triggering_circumstances');
            $table->foreignId('reviewed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['member_pre_registration_id', 'reviewed_at'], 'mpr_review_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_pre_registration_reviews');
        Schema::dropIfExists('member_pre_registrations');
    }
};
