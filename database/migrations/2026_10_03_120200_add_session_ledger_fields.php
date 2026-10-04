<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_attendances', function (Blueprint $table) {
            $table->boolean('requires_settlement')->default(false)->after('checked_in_at');
        });

        Schema::table('member_payments', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->unique()->after('legacy_id');
        });
    }

    public function down(): void
    {
        Schema::table('member_payments', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });

        Schema::table('member_attendances', function (Blueprint $table) {
            $table->dropColumn('requires_settlement');
        });
    }
};
