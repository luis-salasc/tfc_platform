<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_pre_registrations', function (Blueprint $table) {
            $table->foreignId('member_id')
                ->nullable()
                ->unique()
                ->constrained(indexName: 'mpr_member_fk')
                ->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_user_id')
                ->nullable()
                ->constrained('users', indexName: 'mpr_finalized_by_user_fk')
                ->nullOnDelete();
            $table->boolean('informed_consent_accepted')->default(false);
            $table->boolean('risk_assumption_accepted')->default(false);
            $table->string('consent_version', 50)->nullable();
            $table->timestamp('consent_accepted_at')->nullable();
            $table->string('member_signature_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('member_pre_registrations', function (Blueprint $table) {
            $table->dropForeign('mpr_member_fk');
            $table->dropForeign('mpr_finalized_by_user_fk');
            $table->dropUnique(['member_id']);
            $table->dropColumn([
                'member_id',
                'finalized_at',
                'finalized_by_user_id',
                'informed_consent_accepted',
                'risk_assumption_accepted',
                'consent_version',
                'consent_accepted_at',
                'member_signature_path',
            ]);
        });
    }
};
