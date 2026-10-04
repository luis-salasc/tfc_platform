<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_training_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('objective')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedTinyInteger('sessions_per_week')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['member_id', 'status']);
        });

        Schema::create('member_training_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->constrained('member_training_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('session_name', 100)->nullable();
            $table->string('exercise', 180);
            $table->string('sets', 30)->nullable();
            $table->string('repetitions', 60)->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->string('intensity', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['training_plan_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_training_plan_items');
        Schema::dropIfExists('member_training_plans');
    }
};
