<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeclock_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 20);
            $table->timestamp('occurred_at');
            $table->string('source', 30)->default('web');
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'occurred_at'], 'timeclock_org_user_occurred_idx');
            $table->index(['user_id', 'occurred_at'], 'timeclock_user_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeclock_events');
    }
};
