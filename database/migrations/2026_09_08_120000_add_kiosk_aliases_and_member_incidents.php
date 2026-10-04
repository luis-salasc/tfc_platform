<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('public_alias', 40)->nullable()->after('last_name');
            $table->unique(['organization_id', 'public_alias']);
        });

        Schema::create('member_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('member_attendances')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 60);
            $table->string('severity', 20)->default('warning');
            $table->string('status', 20)->default('open');
            $table->string('source', 20)->default('manual');
            $table->string('title', 180);
            $table->text('details')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'occurred_at']);
            $table->index(['member_id', 'status']);
            $table->index(['organization_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_incidents');
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'public_alias']);
            $table->dropColumn('public_alias');
        });
    }
};
