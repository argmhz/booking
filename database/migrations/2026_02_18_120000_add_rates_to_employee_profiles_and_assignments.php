<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->decimal('hourly_customer_rate', 10, 2)->nullable()->after('hourly_wage');
        });

        Schema::table('booking_assignments', function (Blueprint $table) {
            $table->decimal('worker_rate', 10, 2)->nullable()->after('cancelled_at');
            $table->decimal('customer_rate', 10, 2)->nullable()->after('worker_rate');
        });
    }

    public function down(): void
    {
        Schema::table('booking_assignments', function (Blueprint $table) {
            $table->dropColumn(['worker_rate', 'customer_rate']);
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('hourly_customer_rate');
        });
    }
};
