<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('check_in_token_rotated_by_user_id')->nullable()->after('check_in_token')->constrained('users')->nullOnDelete();
            $table->timestamp('check_in_token_rotated_at')->nullable()->after('check_in_token_rotated_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('check_in_token_rotated_by_user_id');
            $table->dropColumn('check_in_token_rotated_at');
        });
    }
};
