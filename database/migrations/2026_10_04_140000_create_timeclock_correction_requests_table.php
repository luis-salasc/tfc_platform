<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeclock_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->date('local_date');
            $table->string('correction_type', 20);
            $table->foreignId('original_event_id')->nullable()->constrained('timeclock_events')->restrictOnDelete();
            $table->string('proposed_event_type', 20)->nullable();
            $table->timestamp('proposed_occurred_at')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_comment')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'employee_user_id', 'local_date'], 'timeclock_correction_employee_date_idx');
            $table->index(['organization_id', 'status', 'local_date'], 'timeclock_correction_status_date_idx');
            $table->index(['original_event_id', 'status'], 'timeclock_correction_original_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeclock_correction_requests');
    }
};
