<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_movement_id')->constrained('session_movements')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('payment_movement_id')->constrained('session_movements')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('quantity');
            $table->timestamp('settled_at');
            $table->foreignId('settled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('attendance_movement_id');
            $table->index('payment_movement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_settlements');
    }
};
