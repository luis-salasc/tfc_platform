<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_payments', function (Blueprint $table) {
            $table->string('payment_method', 30)->default('other')->after('amount');
            $table->string('payment_method_detail', 120)->nullable()->after('payment_method');
            $table->json('delivery_methods')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('member_payments', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_method_detail', 'delivery_methods']);
        });
    }
};
