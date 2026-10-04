<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'informed_consent_accepted' => fn (Blueprint $table) => $table->boolean('informed_consent_accepted')->default(false),
            'risk_assumption_accepted' => fn (Blueprint $table) => $table->boolean('risk_assumption_accepted')->default(false),
            'consent_version' => fn (Blueprint $table) => $table->string('consent_version', 50)->nullable(),
            'consent_accepted_at' => fn (Blueprint $table) => $table->timestamp('consent_accepted_at')->nullable(),
            'member_signature_path' => fn (Blueprint $table) => $table->string('member_signature_path')->nullable(),
        ];

        Schema::table('member_pre_registrations', function (Blueprint $table) use ($columns): void {
            foreach ($columns as $column => $addColumn) {
                if (! Schema::hasColumn('member_pre_registrations', $column)) {
                    $addColumn($table);
                }
            }
        });
    }

    public function down(): void
    {
        // This is a repair migration for installations that ran 120000 before
        // the evidence columns were introduced. It intentionally does not
        // drop columns that may belong to the prior migration on fresh installs.
    }
};
